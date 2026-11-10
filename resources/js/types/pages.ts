// 画面ごとの props 型。BE期のコントローラーの仕様書になる。
// 残席・金額・「押せるか」は builder（本番ではコントローラー）が計算済みの値で渡す。画面では計算しない。
import type { Channel, Contact, ISODate, ReservationKind, SlotId } from './models';

export type SlotView = {
    id: SlotId;
    date: ISODate;
    weekday: string;
    start: string;
    end: string;
    capacity: number;
    used: { monthly: number; trial: number }; // SeatMeter 用
    remaining: number; // その画面の kind での残席
    bookable: boolean; // その画面で押せるか
    blocked: boolean;
};

export type DayView = { date: ISODate; label: string; slots: SlotView[] };

/** 人数ごとの金額（画面で 単価×人数 を計算しないため） */
export type PartyOption = { n: number; amount: number };

// ---- お客様 ----

export type ReserveIndexProps = { days: DayView[]; unitPrice: number; note: string };

export type ReserveFormProps = {
    slot: SlotView | null;
    slotLabel: string;
    unitPrice: number;
    maxParty: number;
    options: PartyOption[];
    /** 確認画面から戻ってきたときの入力値 */
    initial: { partySize: number; contact: Contact };
};

export type ReserveConfirmProps = {
    slot: SlotView | null;
    slotLabel: string;
    partySize: number;
    contact: Contact;
    unitPrice: number;
    amount: number;
    cancelPolicy: string[];
};

export type ReserveCompleteProps = {
    reservation: { id: string; partySize: number; amount: number; contact: Contact; cancelToken: string | null } | null;
    slotLabel: string;
    cancelPolicy: string[];
};

export type ReserveCancelProps = {
    reservation: { id: string; partySize: number; amount: number; name: string; canceled: boolean; refundedAmount: number | null } | null;
    slotLabel: string;
    token: string;
    todayLabel: string;
    /** 今キャンセルしたときの返金額と、その理由 */
    refund: number;
    refundReason: string;
    cancelPolicy: string[];
};

// ---- スタッフ ----

export type PaymentKind = 'paid' | 'onsite' | 'monthly';

export type AdminTodayProps = {
    date: ISODate;
    label: string;
    isToday: boolean;
    prevDate: ISODate;
    nextDate: ISODate;
    closed: boolean;
    slots: {
        slot: SlotView;
        entries: { id: string; name: string; kind: ReservationKind; partySize: number; payment: PaymentKind; channel: Channel; channelLabel: string }[];
    }[];
};
