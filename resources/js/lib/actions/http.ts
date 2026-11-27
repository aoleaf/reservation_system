// 本番用：router で送る版。送り先のルートは BE期に作る（今はまだ存在しない）。
import { router } from '@inertiajs/react';
import type { Actions, Callbacks } from './types';

const opts = (cb?: Callbacks) => ({ onError: cb?.onError, onFinish: cb?.onFinish, preserveScroll: true });

export const httpActions: Actions = {
    createWebReservation: (input, cb) => router.post('/reserve', input, opts(cb)),
    createStaffReservation: (input, cb) => router.post('/admin/reservations', input, opts(cb)),
    submitMonthlySchedule: (input, cb) => router.post(`/member/schedule/${input.month}`, { slotIds: input.slotIds }, opts(cb)),
    changeMonthlySession: (input, cb) => router.post(`/member/change/${input.reservationId}`, { toSlotId: input.toSlotId }, opts(cb)),
    setSlotBlocked: (input, cb) => router.patch(`/admin/slots/${input.slotId}`, { blocked: input.blocked }, opts(cb)),
    cancelByToken: (input, cb) => router.post(`/r/${input.token}/cancel`, {}, opts(cb)),
};
