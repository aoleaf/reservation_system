// 画面名 → props を作る builder の表。builder は純粋関数にする。
// 本番ではコントローラーがこの役目を引き継ぎ、types/pages.ts と同じ形の props を返す。
// selectors を呼んでよいのはここと mocks/actions.ts だけ。
import type { ReservationKind, Settings, Slot } from '@/types/models';
import type { AvailabilityMark, CalendarDay, ReserveCancelProps, ReserveCompleteProps, ReserveConfirmProps, ReserveFormProps, ReserveIndexProps, SlotView } from '@/types/pages';
import { addDays, datesInMonth, dayLabel, isMonth, monthLabel, monthOf, shiftMonth, weekdayIndex, weekdayJa } from './dates';
import type { MockData } from './seed';
import { isActive, refundAmount, remaining, seatsUsed } from './selectors';

export type ServerProps = Record<string, unknown>;
export type Builder = (state: MockData, serverProps: ServerProps) => object;

// ---- 共通の小道具 ----

/** クエリーの値を文字列に（配列や undefined は空文字） */
export const str = (v: unknown) => (typeof v === 'string' ? v : '');

/** n が数字でない・1 未満なら 1 に丸める（NaN を画面に出さない） */
export const toPartySize = (v: unknown) => {
    const n = Math.floor(Number(v));
    return Number.isFinite(n) && n >= 1 ? n : 1;
};

export function slotView(slot: Slot, s: MockData, kind: ReservationKind, extraOk = true): SlotView {
    const rest = remaining(slot, s.reservations, kind);
    return {
        id: slot.id,
        date: slot.date,
        weekday: weekdayJa(slot.date),
        start: slot.start,
        end: slot.end,
        capacity: slot.capacity,
        used: { monthly: seatsUsed(slot, s.reservations, 'monthly'), trial: seatsUsed(slot, s.reservations, 'trial') },
        remaining: rest,
        bookable: slot.status === 'open' && rest > 0 && extraOk,
        status: slot.status,
    };
}

export const slotLabelOf = (slot: Slot | undefined) => (slot ? `${dayLabel(slot.date)} ${slot.start}〜${slot.end}` : '');

export const cancelPolicyOf = (settings: Settings) => [
    `2日前まで：お支払い額から決済手数料（${Math.round(settings.stripeRate * 1000) / 10}%）を引いて返金`,
    '前日：お支払い額の50%を返金',
    '当日・無断のキャンセル：返金なし',
];

export const cancelGuideOf = (settings: Settings) =>
    `キャンセルは、予約確認メールに記載のキャンセル用リンクから、またはお電話（${settings.shopPhone}）で承ります。`;

/** △（残りわずか）にする、その日の体験の残席の合計 */
const FEW_SEATS = 3;

/** 体験の予約を受け付ける最初の日（前日まで受付） */
const firstTrialDate = (s: MockData) => addDays(s.today, 1);

/** 体験で選べる枠か（存在し、土日で、受付期間内） */
function trialSlot(s: MockData, slotId: unknown): Slot | undefined {
    const slot = s.slots.find((x) => x.id === str(slotId));
    if (!slot || !slot.trialOpen || slot.date < firstTrialDate(s)) return undefined;
    return slot;
}

// ---- お客様：体験予約 ----

const reserveIndex: Builder = (s, sp): ReserveIndexProps => {
    const from = firstTrialDate(s);
    const firstMonth = monthOf(from);
    const lastMonth = s.slots.length ? monthOf(s.slots[s.slots.length - 1].date) : firstMonth;
    const wanted = isMonth(sp.month) ? sp.month : firstMonth;
    const month = wanted < firstMonth ? firstMonth : wanted > lastMonth ? lastMonth : wanted;
    const dates = datesInMonth(month);
    const days = dates.map((date) => {
        const raw = s.slots.filter((x) => x.date === date);
        // 受付停止・お休みだけの日は、お客様には「受付なし（−）」と見せる（満席の × とは分ける）
        const open = date >= from && raw.some((x) => x.trialOpen && x.status === 'open');
        const slots = open ? raw.map((x) => slotView(x, s, 'trial')) : [];
        const rest = slots.reduce((sum, x) => sum + (x.bookable ? x.remaining : 0), 0);
        const mark: AvailabilityMark = !open ? 'none' : rest === 0 ? 'full' : rest <= FEW_SEATS ? 'few' : 'ok';
        return { ...calendarDay(date, s.today), label: dayLabel(date), mark, slots };
    });
    return {
        unitPrice: s.settings.trialPrice,
        note: '体験レッスンは土曜・日曜のみ。ご予約は前日まで受け付けています。',
        month,
        monthLabel: monthLabel(month),
        prevMonth: month > firstMonth ? shiftMonth(month, -1) : null,
        nextMonth: month < lastMonth ? shiftMonth(month, 1) : null,
        leadingBlanks: weekdayIndex(dates[0]),
        days,
        initialDate: days.find((d) => d.mark === 'ok' || d.mark === 'few')?.date ?? null,
    };
};

const reserveForm: Builder = (s, sp): ReserveFormProps => {
    const slot = trialSlot(s, sp.slotId);
    const view = slot ? slotView(slot, s, 'trial') : null;
    const maxParty = view?.remaining ?? 0;
    return {
        slot: view,
        slotLabel: slotLabelOf(slot),
        unitPrice: s.settings.trialPrice,
        maxParty,
        options: Array.from({ length: maxParty }, (_, i) => ({ n: i + 1, amount: s.settings.trialPrice * (i + 1) })),
        initial: {
            partySize: Math.min(toPartySize(sp.n), Math.max(1, maxParty)),
            contact: { name: str(sp.name), phone: str(sp.phone), email: str(sp.email) },
        },
    };
};

const reserveConfirm: Builder = (s, sp): ReserveConfirmProps => {
    const slot = trialSlot(s, sp.slotId);
    const partySize = toPartySize(sp.n);
    const contact = { name: str(sp.name), phone: str(sp.phone), email: str(sp.email) };
    return {
        slot: slot ? slotView(slot, s, 'trial') : null,
        slotLabel: slotLabelOf(slot),
        partySize,
        contact,
        unitPrice: s.settings.trialPrice,
        amount: s.settings.trialPrice * partySize,
        cancelPolicy: cancelPolicyOf(s.settings),
        cancelGuide: cancelGuideOf(s.settings),
        contactError: contact.name.trim() && contact.phone.trim() && contact.email.trim() ? null : 'お名前・電話番号・メールアドレスを入力してください',
    };
};

const reserveComplete: Builder = (s, sp): ReserveCompleteProps => {
    const r = s.reservations.find((x) => x.id === str(sp.reservationId) && x.kind === 'trial');
    const slot = r && s.slots.find((x) => x.id === r.slotId);
    return {
        reservation:
            r && slot
                ? { id: r.id, partySize: r.partySize, amount: r.amount, contact: r.contact ?? { name: '', phone: '' } }
                : null,
        slotLabel: slotLabelOf(slot),
        cancelPolicy: cancelPolicyOf(s.settings),
        cancelGuide: cancelGuideOf(s.settings),
    };
};

const reserveCancel: Builder = (s, sp): ReserveCancelProps => {
    const token = str(sp.token);
    const r = token ? s.reservations.find((x) => x.cancelToken === token && x.kind === 'trial') : undefined;
    const slot = r && s.slots.find((x) => x.id === r.slotId);
    const refund = r && slot ? refundAmount(r.amount, slot.date, s.today, s.settings.stripeRate) : 0;
    let refundReason = '';
    if (slot) {
        if (slot.date >= addDays(s.today, 2)) refundReason = '2日前までのため、決済手数料を引いて返金します';
        else if (slot.date === addDays(s.today, 1)) refundReason = '前日のため、50%を返金します';
        else refundReason = '当日以降のため、返金はありません';
    }
    return {
        reservation:
            r && slot
                ? {
                      id: r.id,
                      partySize: r.partySize,
                      amount: r.amount,
                      name: r.contact?.name ?? '',
                      canceled: !isActive(r),
                      refundedAmount: r.refundedAmount ?? null,
                  }
                : null,
        slotLabel: slotLabelOf(slot),
        token,
        todayLabel: dayLabel(s.today),
        refund,
        refundReason,
        cancelPolicy: cancelPolicyOf(s.settings),
        shopPhone: s.settings.shopPhone,
    };
};

// ---- 共通：カレンダーの1日 ----

const calendarDay = (date: string, today: string): CalendarDay => ({ date, day: Number(date.slice(8, 10)), weekdayIndex: weekdayIndex(date), isToday: date === today });

// 生徒さんの画面（member/*）は BE のコントローラーが props を作る（app/Http/Controllers/Member）
// スタッフの画面（admin/*）は BE のコントローラーが props を作る（app/Http/Controllers/Admin）

export const builders: Record<string, Builder> = {
    'reserve/index': reserveIndex,
    'reserve/form': reserveForm,
    'reserve/confirm': reserveConfirm,
    'reserve/complete': reserveComplete,
    'reserve/cancel': reserveCancel,
};
