// モック版の actions：canBook で確かめてからストアを書き換え、router.visit で次の画面へ。
// 本番でこの確認を担うのはサーバー側のバリデーション。
import type { Actions, Callbacks } from '@/lib/actions/types';
import { router } from '@inertiajs/react';
import { addDays, monthOf } from './dates';
import { canBook, changeRemaining, isActive, memberSessions, refundAmount, seatsUsed } from './selectors';
import { useMockStore } from './store';

const FULL = '満席になりました。別の枠をお選びください';

/** エラーを返して終わる */
function fail(cb: Callbacks | undefined, errors: Record<string, string>) {
    cb?.onError?.(errors);
    cb?.onFinish?.();
}

const st = () => useMockStore.getState();

export const mockActions: Actions = {
    createWebReservation({ slotId, partySize, contact }, cb) {
        const s = st();
        const slot = s.slots.find((x) => x.id === slotId);
        if (!slot) return fail(cb, { slotId: '枠が見つかりません' });
        if (!contact.name.trim() || !contact.phone.trim()) return fail(cb, { contact: 'お名前と電話番号を入力してください' });
        if (!canBook(slot, s.reservations, 'trial', partySize)) return fail(cb, { slotId: FULL });
        // モックでは決済を省略し、いきなり confirmed にする
        const res = s.addReservation({
            slotId,
            kind: 'trial',
            channel: 'web',
            partySize,
            status: 'confirmed',
            amount: s.settings.trialPrice * partySize,
            contact,
        });
        st().updateReservation(res.id, { cancelToken: `c${res.id}${slotId.slice(-8)}` });
        cb?.onFinish?.();
        router.visit(route('reserve.complete', res.id));
    },

    createStaffReservation({ slotId, partySize, contact, channel }, cb) {
        const s = st();
        const slot = s.slots.find((x) => x.id === slotId);
        if (!slot) return fail(cb, { slotId: '枠が見つかりません' });
        if (!contact.name.trim()) return fail(cb, { contact: 'お名前を入力してください' });
        if (!canBook(slot, s.reservations, 'trial', partySize)) return fail(cb, { slotId: FULL });
        s.addReservation({
            slotId,
            kind: 'trial',
            channel,
            partySize,
            status: 'confirmed',
            amount: s.settings.trialPrice * partySize,
            contact,
        });
        cb?.onFinish?.();
        router.visit(route('admin.today', { date: slot.date }));
    },

    submitMonthlySchedule({ month, slotIds }, cb) {
        const s = st();
        const memberId = s.currentMemberId;
        const mm = s.memberMonths.find((x) => x.memberId === memberId && x.month === month);
        if (mm?.submittedAt) return fail(cb, { slotIds: 'この月はもう提出済みです' });
        if (slotIds.length !== s.settings.sessionsPerMonth) return fail(cb, { slotIds: `${s.settings.sessionsPerMonth}つ選んでください` });
        const picked = slotIds.map((id) => s.slots.find((x) => x.id === id));
        if (picked.some((x) => !x || monthOf(x.date) !== month)) return fail(cb, { slotIds: '選べない枠が含まれています' });
        const slots = picked as NonNullable<(typeof picked)[number]>[];
        if (new Set(slots.map((x) => x.date)).size !== slots.length) return fail(cb, { slotIds: '同じ日に2つの枠は選べません' });
        if (slots.some((x) => !canBook(x, s.reservations, 'monthly', 1))) return fail(cb, { slotIds: '満席になった枠があります。選び直してください' });

        for (const slot of slots) {
            st().addReservation({ slotId: slot.id, kind: 'monthly', channel: 'member', partySize: 1, status: 'confirmed', amount: 0, memberId });
        }
        st().upsertMemberMonth({ memberId, month, submittedAt: `${s.today}T12:00:00`, changeUsed: mm?.changeUsed ?? false });
        cb?.onFinish?.();
        router.visit(route('member.index'));
    },

    changeMonthlySession({ reservationId, toSlotId }, cb) {
        const s = st();
        const memberId = s.currentMemberId;
        const r = s.reservations.find((x) => x.id === reservationId);
        const from = r && s.slots.find((x) => x.id === r.slotId);
        if (!r || !from || r.memberId !== memberId || r.kind !== 'monthly' || !isActive(r)) return fail(cb, { reservationId: '予約が見つかりません' });
        const month = monthOf(from.date);
        const mm = s.memberMonths.find((x) => x.memberId === memberId && x.month === month);
        if (changeRemaining(mm) <= 0) return fail(cb, { reservationId: 'この月の日程変更はもう使いました' });
        if (from.date < addDays(s.today, 1)) return fail(cb, { reservationId: '日程変更は前日までです' });
        const to = s.slots.find((x) => x.id === toSlotId);
        if (!to || monthOf(to.date) !== month || to.date < addDays(s.today, 1)) return fail(cb, { toSlotId: '選べない枠です' });
        const otherDates = memberSessions(memberId, month, s.reservations, s.slots)
            .filter((x) => x.reservation.id !== r.id)
            .map((x) => x.slot.date);
        if (otherDates.includes(to.date)) return fail(cb, { toSlotId: '同じ日にもう予約があります' });
        if (!canBook(to, s.reservations, 'monthly', 1)) return fail(cb, { toSlotId: FULL });

        st().updateReservation(r.id, { status: 'canceled' });
        st().addReservation({ slotId: to.id, kind: 'monthly', channel: 'member', partySize: 1, status: 'confirmed', amount: 0, memberId });
        st().upsertMemberMonth({ memberId, month, submittedAt: mm?.submittedAt ?? `${s.today}T12:00:00`, changeUsed: true });
        cb?.onFinish?.();
        router.visit(route('member.index'));
    },

    setSlotBlocked({ slotId, blocked }, cb) {
        const s = st();
        const slot = s.slots.find((x) => x.id === slotId);
        if (!slot) return fail(cb, { slotId: '枠が見つかりません' });
        if (blocked && seatsUsed(slot, s.reservations) > 0) return fail(cb, { slotId: '予約が入っている枠はお休みにできません' });
        s.setSlotBlocked(slotId, blocked);
        cb?.onFinish?.();
    },

    cancelByToken({ token }, cb) {
        const s = st();
        const r = s.reservations.find((x) => x.cancelToken === token && x.kind === 'trial');
        const slot = r && s.slots.find((x) => x.id === r.slotId);
        if (!r || !slot) return fail(cb, { token: '予約が見つかりません' });
        if (!isActive(r)) return fail(cb, { token: 'この予約はキャンセル済みです' });
        const refunded = refundAmount(r.amount, slot.date, s.today, s.settings.stripeRate);
        s.updateReservation(r.id, { status: 'canceled', refundedAmount: refunded });
        cb?.onFinish?.();
    },
};
