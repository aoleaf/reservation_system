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
        /** この枠に予約を入れるリンク（体験が入れば体験、平日など体験が無理なら月謝。どちらも無理なら null） */
        add: { kind: ReservationKind; remaining: number } | null;
        entries: { id: string; name: string; kind: ReservationKind; partySize: number; payment: PaymentKind; channel: Channel; channelLabel: string }[];
    }[];
};

/** 月カレンダーの1日分（MonthCalendar 用の共通部分） */
export type CalendarDay = { date: ISODate; day: number; weekdayIndex: number; isToday: boolean };

export type AdminSlotsProps = {
    month: string;
    monthLabel: string;
    prevMonth: string;
    nextMonth: string;
    leadingBlanks: number;
    initialDate: ISODate;
    days: (CalendarDay & { label: string; closed: boolean; slots: SlotView[] })[];
};

// ---- 生徒さん ----

export type MemberSessionView = {
    reservationId: string;
    date: ISODate;
    label: string; // '11月25日（水） 10:00〜12:00'
    past: boolean;
    canChange: boolean;
};

export type MemberIndexProps = {
    member: { id: string; name: string } | null;
    months: {
        month: string;
        label: string; // '今月（2026年11月）'
        submitted: boolean;
        sessions: MemberSessionView[];
        changeRemaining: number;
        /** 未提出で、選びに行ける月なら true */
        canSchedule: boolean;
    }[];
    sessionsPerMonth: number;
};

/** 日程を選ぶカレンダーの1日分 */
export type PickDay = CalendarDay & { label: string; closed: boolean; slots: SlotView[]; selectable: boolean };

export type MemberScheduleProps = {
    month: string;
    monthLabel: string;
    /** 選べない月・提出済みのときの理由（null なら選べる） */
    unavailable: string | null;
    required: number;
    leadingBlanks: number;
    days: PickDay[];
};

export type MemberChangeProps = {
    reservation: { id: string; label: string } | null;
    monthLabel: string;
    changeRemaining: number;
    /** 変更できないときの理由（null なら変更できる） */
    blockedReason: string | null;
    leadingBlanks: number;
    days: PickDay[];
};

export type AdminReservationNewProps = {
    /** 体験か、月謝の生徒か */
    kind: ReservationKind;
    date: ISODate;
    label: string;
    /** 選べない日（体験の平日、お休みなど）のときの説明 */
    dateNote: string | null;
    /** 月謝のときだけ：その日の月での各生徒の回数と、もう入っている日 */
    monthLabel: string;
    required: number;
    members: { id: string; name: string; count: number; dates: ISODate[] }[];
    initialMemberId: string | null;
    dateOptions: { date: ISODate; label: string }[];
    slots: SlotView[];
    initialSlotId: SlotId | null;
    channels: { value: 'phone' | 'dm'; label: string }[];
};

export type AdminMembersProps = {
    month: string;
    monthLabel: string;
    filter: 'all' | 'unsubmitted';
    counts: { all: number; unsubmitted: number };
    members: { id: string; name: string; phone: string; submitted: boolean }[];
};

export type AdminMemberShowProps = {
    member: { id: string; name: string; phone: string; email: string } | null;
    months: { month: string; label: string; submitted: boolean; changeRemaining: number; sessions: string[] }[];
};
