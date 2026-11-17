// ドメインの型。本番でも残す。日付はすべて 'YYYY-MM-DD' の文字列で持ち、Date にしない。

export type ISODate = string; // 'YYYY-MM-DD'
export type SlotId = string; // 中身をパースしない

/** 枠の状態：営業／受付停止（今ある予約は残し、新しい予約だけ止める）／お休み（予約がない枠だけ） */
export type SlotStatus = 'open' | 'paused' | 'closed';

export type Slot = {
    id: SlotId;
    date: ISODate;
    start: string; // '10:00'
    end: string;
    capacity: number; // 6
    trialOpen: boolean; // 土日だけ true
    monthlyLimit: number | null; // 土日は settings.weekendMonthlyLimit、平日は null
    status: SlotStatus;
};

export type ReservationKind = 'trial' | 'monthly';
/** 予約の経路。walkin は来店（口頭） */
export type Channel = 'web' | 'phone' | 'dm' | 'walkin' | 'member' | 'staff';
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
    /** お店の電話番号（キャンセルの案内に出す） */
    shopPhone: string;
    /** 来月の日程の受付開始日（前月の何日から。仮の値・森下様に確認中） */
    scheduleOpenDay: number;
    /** 来月の日程の締め日（前月の何日まで） */
    scheduleDeadlineDay: number;
};
