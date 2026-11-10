// 画面名 → props を作る builder の表。builder は純粋関数にする。
// 本番ではコントローラーがこの役目を引き継ぎ、types/pages.ts と同じ形の props を返す。
// selectors を呼んでよいのはここと mocks/actions.ts だけ。
import type { ReservationKind, Settings, Slot } from '@/types/models';
import type { AdminSlotsProps, CalendarDay, ReserveCancelProps, ReserveCompleteProps, ReserveConfirmProps, ReserveFormProps, ReserveIndexProps, SlotView } from '@/types/pages';
import { addDays, datesInMonth, dayLabel, isMonth, isWeekendDate, monthLabel, monthOf, shiftMonth, weekdayIndex, weekdayJa } from './dates';
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
        bookable: !slot.blocked && rest > 0 && extraOk,
        blocked: slot.blocked,
    };
}

export const slotLabelOf = (slot: Slot | undefined) => (slot ? `${dayLabel(slot.date)} ${slot.start}〜${slot.end}` : '');

export const cancelPolicyOf = (settings: Settings) => [
    `2日前まで：お支払い額から決済手数料（${Math.round(settings.stripeRate * 1000) / 10}%）を引いて返金`,
    '前日：お支払い額の50%を返金',
    '当日・無断のキャンセル：返金なし',
];

/** 体験の予約を受け付ける最初の日（前日まで受付） */
const firstTrialDate = (s: MockData) => addDays(s.today, 1);

/** 体験で選べる枠か（存在し、土日で、受付期間内） */
function trialSlot(s: MockData, slotId: unknown): Slot | undefined {
    const slot = s.slots.find((x) => x.id === str(slotId));
    if (!slot || !slot.trialOpen || slot.date < firstTrialDate(s)) return undefined;
    return slot;
}

// ---- お客様：体験予約 ----

const reserveIndex: Builder = (s): ReserveIndexProps => {
    const from = firstTrialDate(s);
    const dates = [...new Set(s.slots.filter((x) => x.date >= from && isWeekendDate(x.date)).map((x) => x.date))].sort();
    return {
        unitPrice: s.settings.trialPrice,
        note: '体験レッスンは土曜・日曜のみ。ご予約は前日まで受け付けています。',
        days: dates.map((date) => ({
            date,
            label: dayLabel(date),
            slots: s.slots.filter((x) => x.date === date).map((x) => slotView(x, s, 'trial')),
        })),
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
    return {
        slot: slot ? slotView(slot, s, 'trial') : null,
        slotLabel: slotLabelOf(slot),
        partySize,
        contact: { name: str(sp.name), phone: str(sp.phone), email: str(sp.email) },
        unitPrice: s.settings.trialPrice,
        amount: s.settings.trialPrice * partySize,
        cancelPolicy: cancelPolicyOf(s.settings),
    };
};

const reserveComplete: Builder = (s, sp): ReserveCompleteProps => {
    const r = s.reservations.find((x) => x.id === str(sp.reservationId) && x.kind === 'trial');
    const slot = r && s.slots.find((x) => x.id === r.slotId);
    return {
        reservation:
            r && slot
                ? { id: r.id, partySize: r.partySize, amount: r.amount, contact: r.contact ?? { name: '', phone: '' }, cancelToken: r.cancelToken ?? null }
                : null,
        slotLabel: slotLabelOf(slot),
        cancelPolicy: cancelPolicyOf(s.settings),
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
    };
};

// ---- スタッフ：月カレンダー ----

const calendarDay = (date: string, today: string): CalendarDay => ({ date, day: Number(date.slice(8, 10)), weekdayIndex: weekdayIndex(date), isToday: date === today });

const adminSlots: Builder = (s, sp): AdminSlotsProps => {
    const month = isMonth(sp.month) ? sp.month : monthOf(s.today);
    const dates = datesInMonth(month);
    const days = dates.map((date) => {
        const slots = s.slots.filter((x) => x.date === date).map((x) => slotView(x, s, 'monthly'));
        return { ...calendarDay(date, s.today), label: dayLabel(date), closed: slots.length > 0 && slots.every((x) => x.blocked), slots };
    });
    return {
        month,
        monthLabel: monthLabel(month),
        prevMonth: shiftMonth(month, -1),
        nextMonth: shiftMonth(month, 1),
        leadingBlanks: weekdayIndex(dates[0]),
        initialDate: monthOf(s.today) === month ? s.today : dates[0],
        days,
    };
};

export const builders: Record<string, Builder> = {
    'admin/slots': adminSlots,
    'reserve/index': reserveIndex,
    'reserve/form': reserveForm,
    'reserve/confirm': reserveConfirm,
    'reserve/complete': reserveComplete,
    'reserve/cancel': reserveCancel,
};
