// 書き込みの入口。すべての書き込みを BE に移したので、http 版だけを使う。
import { httpActions } from './http';

export type * from './types';

export const {
    createWebReservation,
    createStaffReservation,
    submitMonthlySchedule,
    changeMonthlySession,
    setSlotStatus,
    setDayStatus,
    cancelByToken,
    cancelByStaff,
    resolveRefund,
    updateSettings,
    chooseLineGroup,
    testLine,
    requestMemberLogin,
    confirmMemberLogin,
} = httpActions;
