// 書き込みの入口。VITE_MOCK のときはモック版、それ以外は http 版。
// 本番へ移るときは http 版だけを export する。
import { mockActions } from '@/mocks/actions';
import { httpActions } from './http';
import type { Actions } from './types';

export type * from './types';

const actions: Actions = import.meta.env.VITE_MOCK === 'true' ? mockActions : httpActions;

export const { createWebReservation, createStaffReservation, submitMonthlySchedule, changeMonthlySession, setSlotBlocked, cancelByToken } = actions;
