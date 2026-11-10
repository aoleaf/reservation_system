// ドメインの型。本番でも残す。日付はすべて 'YYYY-MM-DD' の文字列で持ち、Date にしない。

export type ISODate = string; // 'YYYY-MM-DD'
export type SlotId = string; // 中身をパースしない

export type Slot = {
    id: SlotId;
    date: ISODate;
    start: string; // '10:00'
    end: string;
    capacity: number; // 6
    trialOpen: boolean; // 土日だけ true
    monthlyLimit: number | null; // 土日は settings.weekendMonthlyLimit、平日は null
    blocked: boolean; // お休み
};

export type ReservationKind = 'trial' | 'monthly';
export type Channel = 'web' | 'phone' | 'dm' | 'member' | 'staff';
export type ReservationStatus = 'pending' | 'confirmed' | 'canceled' | 'attended' | 'no_show';
export type Contact = { name: string; phone: string; email?: string };

export type Reservation = {
    id: string;
    slotId: SlotId;
    kind: ReservationKind;
    channel: Channel;
    partySize: number;
    status: ReservationStatus;
    amount: number;
    refundedAmount?: number;
    expiresAt?: string;
    cancelToken?: string;
    contact?: Contact;
    memberId?: string;
    createdAt: string;
};

export type Member = { id: string; name: string; phone: string; email: string; active: boolean };

export type MemberMonth = {
    memberId: string;
    month: string; // 'YYYY-MM'
    submittedAt: string | null;
    changeUsed: boolean;
};

export type Settings = {
    trialPrice: number;
    stripeRate: number;
    sessionsPerMonth: number;
    weekendMonthlyLimit: number;
};
