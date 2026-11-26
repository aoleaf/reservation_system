// 書き込みのインターフェース。画面は '@/lib/actions' からだけ書き込む。本番でも残す。
import type { Contact, ISODate, SlotId, SlotStatus } from '@/types/models';
import type { CancelReason } from '@/types/pages';

export type Callbacks = {
    onError?: (errors: Record<string, string>) => void;
    onFinish?: () => void;
};

export type CreateWebReservationInput = { slotId: SlotId; partySize: number; contact: Contact };
/** スタッフの予約入力。体験は人数と連絡先、月謝は生徒を指定する（1名） */
export type CreateStaffReservationInput =
    | { kind: 'trial'; slotId: SlotId; channel: 'phone' | 'dm' | 'walkin' | 'staff'; partySize: number; contact: Contact }
    | { kind: 'monthly'; slotId: SlotId; channel: 'phone' | 'dm' | 'walkin' | 'staff'; memberId: string };
export type SubmitMonthlyScheduleInput = { month: string; slotIds: SlotId[] };
export type ChangeMonthlySessionInput = { reservationId: string; toSlotId: SlotId };
export type SetSlotStatusInput = { slotId: SlotId; status: SlotStatus };
/** 1日分をまとめて受付停止／再開する（お休みの枠はそのまま） */
export type SetDayStatusInput = { date: ISODate; status: 'open' | 'paused' };
export type CancelByTokenInput = { token: string };
/** スタッフのキャンセル入力（電話でのキャンセル、お店都合でお願いしたとき） */
export type UpdateSettingsInput = { trialPrice: number };
export type ResolveRefundInput = { reservationId: string };
export type RequestMemberLoginInput = { email: string };
export type ConfirmMemberLoginInput = { token: string };
export type CancelByStaffInput = { reservationId: string; reason: CancelReason };

export type Actions = {
    createWebReservation: (input: CreateWebReservationInput, cb?: Callbacks) => void;
    createStaffReservation: (input: CreateStaffReservationInput, cb?: Callbacks) => void;
    submitMonthlySchedule: (input: SubmitMonthlyScheduleInput, cb?: Callbacks) => void;
    changeMonthlySession: (input: ChangeMonthlySessionInput, cb?: Callbacks) => void;
    setSlotStatus: (input: SetSlotStatusInput, cb?: Callbacks) => void;
    setDayStatus: (input: SetDayStatusInput, cb?: Callbacks) => void;
    cancelByToken: (input: CancelByTokenInput, cb?: Callbacks) => void;
    cancelByStaff: (input: CancelByStaffInput, cb?: Callbacks) => void;
    resolveRefund: (input: ResolveRefundInput, cb?: Callbacks) => void;
    updateSettings: (input: UpdateSettingsInput, cb?: Callbacks) => void;
    requestMemberLogin: (input: RequestMemberLoginInput, cb?: Callbacks) => void;
    confirmMemberLogin: (input: ConfirmMemberLoginInput, cb?: Callbacks) => void;
};
