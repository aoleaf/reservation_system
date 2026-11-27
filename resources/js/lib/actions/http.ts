// 本番用：router で送る版。送り先のルートは BE期に作る（今はまだ存在しない）。
import { router } from '@inertiajs/react';
import type { Actions, Callbacks } from './types';

const opts = (cb?: Callbacks) => ({ onError: cb?.onError, onFinish: cb?.onFinish, preserveScroll: true });

export const httpActions: Actions = {
    createWebReservation: (input, cb) => router.post('/reserve', input, opts(cb)),
    createStaffReservation: (input, cb) => router.post('/admin/reservations', input, opts(cb)),
    submitMonthlySchedule: (input, cb) => router.post(`/member/schedule/${input.month}`, { slotIds: input.slotIds }, opts(cb)),
    changeMonthlySession: (input, cb) => router.post(`/member/change/${input.reservationId}`, { toSlotId: input.toSlotId }, opts(cb)),
    setDayStatus: (input, cb) => router.patch(`/admin/days/${input.date}`, { status: input.status }, opts(cb)),
    setSlotStatus: (input, cb) => router.patch(`/admin/slots/${input.slotId}`, { status: input.status }, opts(cb)),
    resolveRefund: (input, cb) => router.post(`/admin/reservations/${input.reservationId}/refund-resolved`, {}, opts(cb)),
    updateSettings: (input, cb) => router.patch('/admin/settings', input, opts(cb)),
    requestMemberLogin: (input, cb) => router.post('/member/login', input, opts(cb)),
    confirmMemberLogin: (input, cb) => router.post(`/member/login/${input.token}`, {}, opts(cb)),
    cancelByToken: (input, cb) => router.post(`/r/${input.token}/cancel`, {}, opts(cb)),
    cancelByStaff: (input, cb) => router.post(`/admin/reservations/${input.reservationId}/cancel`, { reason: input.reason }, opts(cb)),
};
