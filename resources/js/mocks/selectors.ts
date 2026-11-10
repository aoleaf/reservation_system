// 残席・予約可否・返金額の計算（すべて純粋関数）。
// 呼んでよいのは mocks/props.ts と mocks/actions.ts だけ。BE期に PHP へ移植し、同じ fixture で照合する。
import { differenceInCalendarDays, parseISO } from 'date-fns';
import type { ISODate, MemberMonth, Reservation, ReservationKind, Slot } from '@/types/models';

/** 席を占有している予約か（pending / confirmed） */
export const isActive = (r: Reservation) => r.status === 'pending' || r.status === 'confirmed';

export function seatsUsed(slot: Slot, rs: Reservation[], kind?: ReservationKind): number {
    let sum = 0;
    for (const r of rs) {
        if (r.slotId === slot.id && isActive(r) && (!kind || r.kind === kind)) sum += r.partySize;
    }
    return sum;
}

export function remaining(slot: Slot, rs: Reservation[], kind: ReservationKind): number {
    if (slot.blocked) return 0;
    const free = slot.capacity - seatsUsed(slot, rs);
    if (kind === 'trial') return slot.trialOpen ? Math.max(0, free) : 0;
    if (slot.monthlyLimit === null) return Math.max(0, free);
    return Math.max(0, Math.min(free, slot.monthlyLimit - seatsUsed(slot, rs, 'monthly')));
}

export const canBook = (slot: Slot, rs: Reservation[], kind: ReservationKind, n: number) => n >= 1 && remaining(slot, rs, kind) >= n;

export function refundAmount(amount: number, slotDate: ISODate, today: ISODate, rate: number): number {
    const diff = differenceInCalendarDays(parseISO(slotDate), parseISO(today));
    if (diff >= 2) return amount - Math.round(amount * rate);
    if (diff === 1) return Math.round(amount * 0.5);
    return 0;
}

/** その月の月謝予約（canceled を除く）を日付順で返す */
export function memberSessions(memberId: string, month: string, rs: Reservation[], slots: Slot[]): { reservation: Reservation; slot: Slot }[] {
    const byId = new Map(slots.map((s) => [s.id, s]));
    const out: { reservation: Reservation; slot: Slot }[] = [];
    for (const r of rs) {
        if (r.memberId !== memberId || r.kind !== 'monthly' || r.status === 'canceled') continue;
        const slot = byId.get(r.slotId);
        if (slot && slot.date.startsWith(month)) out.push({ reservation: r, slot });
    }
    return out.sort((a, b) => (a.slot.date + a.slot.start).localeCompare(b.slot.date + b.slot.start));
}

export const changeRemaining = (mm: MemberMonth | undefined) => (mm?.changeUsed ? 0 : 1);
