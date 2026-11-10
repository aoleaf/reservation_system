// 書き込みのインターフェース。画面は '@/lib/actions' からだけ書き込む。本番でも残す。
import type { Contact, SlotId } from '@/types/models';

export type Callbacks = {
    onError?: (errors: Record<string, string>) => void;
    onFinish?: () => void;
};

export type CreateWebReservationInput = { slotId: SlotId; partySize: number; contact: Contact };
export type CreateStaffReservationInput = { slotId: SlotId; partySize: number; contact: Contact; channel: 'phone' | 'dm' | 'staff' };
export type SubmitMonthlyScheduleInput = { month: string; slotIds: SlotId[] };
export type ChangeMonthlySessionInput = { reservationId: string; toSlotId: SlotId };
export type SetSlotBlockedInput = { slotId: SlotId; blocked: boolean };
export type CancelByTokenInput = { token: string };

export type Actions = {
    createWebReservation: (input: CreateWebReservationInput, cb?: Callbacks) => void;
    createStaffReservation: (input: CreateStaffReservationInput, cb?: Callbacks) => void;
    submitMonthlySchedule: (input: SubmitMonthlyScheduleInput, cb?: Callbacks) => void;
    changeMonthlySession: (input: ChangeMonthlySessionInput, cb?: Callbacks) => void;
    setSlotBlocked: (input: SetSlotBlockedInput, cb?: Callbacks) => void;
    cancelByToken: (input: CancelByTokenInput, cb?: Callbacks) => void;
};
