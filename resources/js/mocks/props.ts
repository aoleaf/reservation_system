// 画面名 → props を作る builder の表。builder は純粋関数にする。
// 本番ではコントローラーがこの役目を引き継ぎ、types/pages.ts と同じ形の props を返す。
// selectors を呼んでよいのはここと mocks/actions.ts だけ。
import type { Channel, Reservation, ReservationKind, Settings, Slot } from '@/types/models';
import type { AdminMemberShowProps, AdminMembersProps, AdminReservationNewProps, AdminSlotsProps, AdminTodayProps, PaymentKind, CalendarDay, MemberChangeProps, MemberIndexProps, MemberScheduleProps, PickDay, ReserveCancelProps, ReserveCompleteProps, ReserveConfirmProps, ReserveFormProps, ReserveIndexProps, SlotView } from '@/types/pages';
import { addDays, datesInMonth, dayLabel, isISODate, isMonth, isWeekendDate, monthLabel, monthOf, shiftMonth, weekdayIndex, weekdayJa } from './dates';
import type { MockData } from './seed';
import { canBook, changeRemaining, isActive, memberSessions, refundAmount, remaining, seatsUsed } from './selectors';

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

// ---- 生徒さん ----

const memberMonthOf = (s: MockData, memberId: string, month: string) => s.memberMonths.find((x) => x.memberId === memberId && x.month === month);
const hasSlots = (s: MockData, month: string) => s.slots.some((x) => x.date.startsWith(month));

/** 日程を選ぶカレンダー（月謝の残席・お休み・前日まで・追加の条件で選べるかを決める） */
function pickDays(s: MockData, month: string, ok: (slot: Slot) => boolean): PickDay[] {
    const tomorrow = addDays(s.today, 1);
    return datesInMonth(month).map((date) => {
        const raw = s.slots.filter((x) => x.date === date);
        const slots = raw.map((x) => slotView(x, s, 'monthly', date >= tomorrow && ok(x)));
        return {
            ...calendarDay(date, s.today),
            label: dayLabel(date),
            closed: raw.length > 0 && raw.every((x) => x.blocked),
            slots,
            selectable: slots.some((x) => x.bookable),
        };
    });
}

const memberIndex: Builder = (s): MemberIndexProps => {
    const member = s.members.find((m) => m.id === s.currentMemberId);
    const thisMonth = monthOf(s.today);
    const tomorrow = addDays(s.today, 1);
    return {
        member: member ? { id: member.id, name: member.name } : null,
        sessionsPerMonth: s.settings.sessionsPerMonth,
        months: !member
            ? []
            : [thisMonth, shiftMonth(thisMonth, 1)].map((month, i) => {
                  const mm = memberMonthOf(s, member.id, month);
                  const rest = changeRemaining(mm);
                  return {
                      month,
                      label: `${i === 0 ? '今月' : '来月'}（${monthLabel(month)}）`,
                      submitted: Boolean(mm?.submittedAt),
                      changeRemaining: rest,
                      canSchedule: !mm?.submittedAt && i === 1 && hasSlots(s, month),
                      sessions: memberSessions(member.id, month, s.reservations, s.slots).map(({ reservation, slot }) => ({
                          reservationId: reservation.id,
                          date: slot.date,
                          label: slotLabelOf(slot),
                          past: slot.date < tomorrow,
                          canChange: rest > 0 && slot.date >= tomorrow,
                      })),
                  };
              }),
    };
};

const memberSchedule: Builder = (s, sp): MemberScheduleProps => {
    const month = isMonth(sp.month) ? sp.month : shiftMonth(monthOf(s.today), 1);
    const mm = memberMonthOf(s, s.currentMemberId, month);
    let unavailable: string | null = null;
    if (!hasSlots(s, month)) unavailable = 'この月はまだ日程を選べません';
    else if (mm?.submittedAt) unavailable = 'この月の日程はもう提出済みです';
    const dates = datesInMonth(month);
    return {
        month,
        monthLabel: monthLabel(month),
        unavailable,
        required: s.settings.sessionsPerMonth,
        leadingBlanks: weekdayIndex(dates[0]),
        days: pickDays(s, month, () => !unavailable),
    };
};

const memberChange: Builder = (s, sp): MemberChangeProps => {
    const r = s.reservations.find((x) => x.id === str(sp.reservationId) && x.memberId === s.currentMemberId && x.kind === 'monthly' && isActive(x));
    const from = r && s.slots.find((x) => x.id === r.slotId);
    if (!r || !from) return { reservation: null, monthLabel: '', changeRemaining: 0, blockedReason: null, leadingBlanks: 0, days: [] };
    const month = monthOf(from.date);
    const rest = changeRemaining(memberMonthOf(s, s.currentMemberId, month));
    let blockedReason: string | null = null;
    if (rest <= 0) blockedReason = 'この月の日程変更はもう使いました（月1回まで）';
    else if (from.date < addDays(s.today, 1)) blockedReason = '日程変更はレッスンの前日までです';
    const otherDates = new Set(
        memberSessions(s.currentMemberId, month, s.reservations, s.slots)
            .filter((x) => x.reservation.id !== r.id)
            .map((x) => x.slot.date),
    );
    return {
        reservation: { id: r.id, label: slotLabelOf(from) },
        monthLabel: monthLabel(month),
        changeRemaining: rest,
        blockedReason,
        leadingBlanks: weekdayIndex(`${month}-01`),
        days: pickDays(s, month, (x) => !blockedReason && x.id !== from.id && !otherDates.has(x.date) && canBook(x, s.reservations, 'monthly', 1)),
    };
};

// ---- スタッフ：今日の一覧・予約入力・生徒一覧 ----

const CHANNEL_LABEL: Record<Channel, string> = { web: 'Web', phone: '電話', dm: 'DM', member: '生徒', staff: 'スタッフ' };

/** 支払いの色分け：月謝は monthly、体験の web は支払済、それ以外の体験は当日払い */
const paymentOf = (r: Reservation): PaymentKind => (r.kind === 'monthly' ? 'monthly' : r.channel === 'web' ? 'paid' : 'onsite');

const dateParam = (s: MockData, v: unknown) => (isISODate(v) ? v : s.today);

const adminToday: Builder = (s, sp): AdminTodayProps => {
    const date = dateParam(s, sp.date);
    const names = new Map(s.members.map((m) => [m.id, m.name]));
    const raw = s.slots.filter((x) => x.date === date);
    return {
        date,
        label: dayLabel(date),
        isToday: date === s.today,
        prevDate: addDays(date, -1),
        nextDate: addDays(date, 1),
        closed: raw.length > 0 && raw.every((x) => x.blocked),
        slots: raw.map((slot) => ({
            slot: slotView(slot, s, 'trial'),
            add: slot.blocked
                ? null
                : remaining(slot, s.reservations, 'trial') > 0
                  ? { kind: 'trial' as const, remaining: remaining(slot, s.reservations, 'trial') }
                  : remaining(slot, s.reservations, 'monthly') > 0
                    ? { kind: 'monthly' as const, remaining: remaining(slot, s.reservations, 'monthly') }
                    : null,
            entries: s.reservations
                .filter((r) => r.slotId === slot.id && isActive(r))
                .sort((a, b) => (a.kind === b.kind ? a.createdAt.localeCompare(b.createdAt) : a.kind === 'monthly' ? -1 : 1))
                .map((r) => ({
                    id: r.id,
                    name: (r.memberId ? names.get(r.memberId) : r.contact?.name) ?? '（名前なし）',
                    kind: r.kind,
                    partySize: r.partySize,
                    payment: paymentOf(r),
                    channel: r.channel,
                    channelLabel: CHANNEL_LABEL[r.channel],
                })),
        })),
    };
};

const adminReservationNew: Builder = (s, sp): AdminReservationNewProps => {
    const kind: ReservationKind = str(sp.kind) === 'monthly' ? 'monthly' : 'trial';
    const date = dateParam(s, sp.date);
    const month = monthOf(date);
    const raw = s.slots.filter((x) => x.date === date);
    const slots = raw.map((x) => slotView(x, s, kind));
    const dateOptions = [...new Set(s.slots.filter((x) => x.date >= s.today && !x.blocked && (kind === 'monthly' || x.trialOpen)).map((x) => x.date))]
        .slice(0, kind === 'monthly' ? 21 : 12)
        .map((d) => ({ date: d, label: dayLabel(d) }));
    let dateNote: string | null = null;
    if (raw.length === 0) dateNote = 'この日は枠がありません';
    else if (raw.every((x) => x.blocked)) dateNote = 'この日はお休みです';
    else if (kind === 'trial' && !raw.some((x) => x.trialOpen)) dateNote = '体験レッスンは土曜・日曜だけです。上から日を選んでください';
    const wanted = slots.find((x) => x.id === str(sp.slotId) && x.bookable);
    const members =
        kind === 'monthly'
            ? s.members.map((m) => {
                  const sessions = memberSessions(m.id, month, s.reservations, s.slots);
                  return { id: m.id, name: m.name, count: sessions.length, dates: sessions.map((x) => x.slot.date) };
              })
            : [];
    return {
        kind,
        monthLabel: monthLabel(month),
        required: s.settings.sessionsPerMonth,
        members,
        initialMemberId: members.find((m) => m.id === str(sp.member))?.id ?? null,
        date,
        label: dayLabel(date),
        dateNote,
        dateOptions,
        slots,
        initialSlotId: wanted?.id ?? null,
        channels: [
            { value: 'phone', label: '電話' },
            { value: 'dm', label: 'DM' },
        ],
    };
};

const adminMembers: Builder = (s, sp): AdminMembersProps => {
    const month = shiftMonth(monthOf(s.today), 1);
    const filter = str(sp.filter) === 'unsubmitted' ? 'unsubmitted' : 'all';
    const all = s.members.map((m) => ({ id: m.id, name: m.name, phone: m.phone, submitted: Boolean(memberMonthOf(s, m.id, month)?.submittedAt) }));
    const unsubmitted = all.filter((m) => !m.submitted);
    return {
        month,
        monthLabel: monthLabel(month),
        filter,
        counts: { all: all.length, unsubmitted: unsubmitted.length },
        members: filter === 'unsubmitted' ? unsubmitted : all,
    };
};

const adminMemberShow: Builder = (s, sp): AdminMemberShowProps => {
    const m = s.members.find((x) => x.id === str(sp.memberId));
    const thisMonth = monthOf(s.today);
    return {
        member: m ? { id: m.id, name: m.name, phone: m.phone, email: m.email } : null,
        months: !m
            ? []
            : [thisMonth, shiftMonth(thisMonth, 1)].map((month) => {
                  const mm = memberMonthOf(s, m.id, month);
                  return {
                      month,
                      label: monthLabel(month),
                      submitted: Boolean(mm?.submittedAt),
                      changeRemaining: changeRemaining(mm),
                      sessions: memberSessions(m.id, month, s.reservations, s.slots).map((x) => slotLabelOf(x.slot)),
                  };
              }),
    };
};

export const builders: Record<string, Builder> = {
    'admin/today': adminToday,
    'admin/reservation-new': adminReservationNew,
    'admin/members': adminMembers,
    'admin/member-show': adminMemberShow,
    'member/index': memberIndex,
    'member/schedule': memberSchedule,
    'member/change': memberChange,
    'admin/slots': adminSlots,
    'reserve/index': reserveIndex,
    'reserve/form': reserveForm,
    'reserve/confirm': reserveConfirm,
    'reserve/complete': reserveComplete,
    'reserve/cancel': reserveCancel,
};
