// 画面ごとの props 型。BE期のコントローラーの仕様書になる。
// 残席・金額・「押せるか」は builder（本番ではコントローラー）が計算済みの値で渡す。画面では計算しない。
import type { Channel, Contact, ISODate, ReservationKind, SlotId, SlotStatus } from './models';

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
    status: SlotStatus;
};

export type DayView = { date: ISODate; label: string; slots: SlotView[] };

/** 人数ごとの金額（画面で 単価×人数 を計算しないため） */
export type PartyOption = { n: number; amount: number };

// ---- お客様 ----

/** 空き状況の記号：ok=○ 空きあり / few=△ 残りわずか / full=× 満席 / none=受付なし（平日・過去・お休み） */
export type AvailabilityMark = 'ok' | 'few' | 'full' | 'none';

export type ReserveIndexProps = {
    unitPrice: number;
    note: string;
    month: string;
    monthLabel: string;
    /** 前後の月へ移れないときは null */
    prevMonth: string | null;
    nextMonth: string | null;
    leadingBlanks: number;
    days: (CalendarDay & { label: string; mark: AvailabilityMark; slots: SlotView[] })[];
    /** 最初に選んでおく日（○△ の最初の日） */
    initialDate: ISODate | null;
};

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
    cancelGuide: string;
    /** 連絡先が足りないとき（URL を直接いじられたときなど）の説明。null なら支払える */
    contactError: string | null;
};

export type ReserveCompleteProps = {
    reservation: { id: string; partySize: number; amount: number; contact: Contact } | null;
    slotLabel: string;
    cancelPolicy: string[];
    /** キャンセルの案内（リンクは確認メールで送るので、画面には出さない） */
    cancelGuide: string;
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
    shopPhone: string;
};

// ---- スタッフ ----

export type PaymentKind = 'paid' | 'onsite' | 'monthly';

/** スタッフが入力するときの経路 */
export type StaffChannel = 'phone' | 'dm' | 'walkin';

/** スタッフ画面の予約1件（予約一覧とカレンダーのパネルで使う） */
export type AdminEntry = {
    id: string;
    name: string;
    /** 連絡用の電話番号 */
    phone: string | null;
    kind: ReservationKind;
    partySize: number;
    payment: PaymentKind;
    channel: Channel;
    channelLabel: string;
    /** 今キャンセルしたときの返金額と説明 */
    cancel: { refund: number; note: string };
};

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
        entries: AdminEntry[];
    }[];
};

/** 月カレンダーの1日分（MonthCalendar 用の共通部分） */
export type CalendarDay = { date: ISODate; day: number; weekdayIndex: number; isToday: boolean };

/** スタッフのカレンダーの1枠。埋まり具合の濃さと、お休みにできるか */
export type AdminSlotCell = SlotView & {
    /** 入っている席の合計（月謝＋体験） */
    usedTotal: number;
    /** 埋まり具合の濃さ（0=空き 〜 4=満席） */
    fill: 0 | 1 | 2 | 3 | 4;
    /** 体験だけ受けられる空き（土日で月謝が上限に達した枠の空き席。それ以外は 0） */
    trialOnlyFree: number;
    /** 枠の印。満席を優先し、満席でなく月謝が上限なら monthlyFull。どちらでもなければ null */
    mark: 'full' | 'monthlyFull' | null;
    /** 予約の件数（お休みにできない理由の表示用） */
    reservationCount: number;
    /** 予約が無く、お休みにできるか */
    canClose: boolean;
};

export type AdminSlotsProps = {
    /** 表示する2か月の最初の月 */
    month: string;
    prevMonth: string;
    nextMonth: string;
    months: {
        month: string;
        label: string;
        leadingBlanks: number;
        days: (CalendarDay & { label: string; closed: boolean; slots: AdminSlotCell[] })[];
    }[];
    /** 選んでいる日（?date=）。パネルに出す。選んでいなければ null */
    selected: {
        date: ISODate;
        label: string;
        slots: (AdminSlotCell & { entries: AdminEntry[] })[];
        /** 1日まとめての操作。営業の枠があれば pause、受付停止だけなら resume、どちらもなければ null */
        dayAction: 'pause' | 'resume' | null;
    } | null;
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
        /** 未提出で、受付期間中なら true */
        canSchedule: boolean;
        /** 受付期間の案内（締切、受付前、締め切り後の連絡先など） */
        scheduleNote: string | null;
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
    channels: { value: StaffChannel; label: string }[];
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
