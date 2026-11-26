// 書き込みの入口。VITE_MOCK のときはモック版、それ以外は http 版。
// BE に移した action は、モック中でも http 版を使う（docs/be-design.md の「移行の進め方」）。
// 本番へ移るときは http 版だけを export する。
import { mockActions } from '@/mocks/actions';
import { httpActions } from './http';
import type { Actions } from './types';

export type * from './types';

/** BE に移した action（PR 2：スタッフ画面） */
const ON_SERVER: (keyof Actions)[] = ['createStaffReservation', 'cancelByStaff', 'setSlotStatus', 'setDayStatus'];

const base: Actions = import.meta.env.VITE_MOCK === 'true' ? mockActions : httpActions;
const actions: Actions = { ...base, ...Object.fromEntries(ON_SERVER.map((k) => [k, httpActions[k]])) };

export const { createWebReservation, createStaffReservation, submitMonthlySchedule, changeMonthlySession, setSlotStatus, setDayStatus, cancelByToken, cancelByStaff } = actions;
