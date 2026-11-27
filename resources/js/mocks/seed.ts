// 初期データを作る。乱数は mulberry32(20261120) で固定し、Math.random は使わない。
// seed やストアの形を変えたら SEED_VERSION を上げる（古い sessionStorage を読まないように）。
import type { Channel, ISODate, Member, MemberMonth, Reservation, Settings, Slot } from '@/types/models';
import { datesInMonth, isWeekendDate } from './dates';
import { canBook } from './selectors';

export const SEED_VERSION = 2;

export type Role = 'customer' | 'member' | 'staff';

export type MockData = {
    slots: Slot[];
    reservations: Reservation[];
    members: Member[];
    memberMonths: MemberMonth[];
    settings: Settings;
    today: ISODate;
    role: Role;
    currentMemberId: string;
    /** 予約 ID の連番 */
    seq: number;
};

export const SEED_TODAY = '2026-11-20';

export function mulberry32(a: number) {
    return function () {
        a |= 0;
        a = (a + 0x6d2b79f5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

const CAPACITY = 6;
const TIMES = [
    ['10:00', '12:00'],
    ['13:00', '15:00'],
    ['15:30', '17:30'],
] as const;
const CLOSED_DAYS = ['2026-11-04', '2026-11-10', '2026-11-17', '2026-11-26', '2026-12-02', '2026-12-08', '2026-12-16', '2026-12-24'];

const SURNAMES = ['佐藤', '鈴木', '高橋', '田中', '伊藤', '渡辺', '山本', '中村', '小林', '加藤', '吉田', '山田', '佐々木', '山口', '松本', '井上', '木村', '林', '斎藤', '清水'];
const GIVEN = ['陽子', '美咲', '恵子', '真由美', '由美', '直子', '智子', '裕子', '久美子', '麻衣', '健一', '誠', '大輔', '翔太', '美穂', '綾', '千尋', '和子', '花子', '結衣'];
const TRIAL_NAMES = ['青木 葵', '石川 遥', '前田 蓮', '藤田 紗希', '岡田 湊', '後藤 莉子', '長谷川 悠', '村上 七海', '近藤 樹', '石井 彩花', '坂本 陸', '遠藤 優奈'];

export const slotIdOf = (date: ISODate, start: string) => `s-${date.replaceAll('-', '')}-${start.replace(':', '')}`;

export function createSeed(): MockData {
    const rand = mulberry32(20261120);
    const pick = <T,>(xs: readonly T[]) => xs[Math.floor(rand() * xs.length)];
    const phone = () => `090-${String(1000 + Math.floor(rand() * 9000))}-${String(1000 + Math.floor(rand() * 9000))}`;

    const settings: Settings = { trialPrice: 4500, stripeRate: 0.036, sessionsPerMonth: 4, weekendMonthlyLimit: 3 };

    // 枠：11/1〜12/31 の全日に3枠
    const slots: Slot[] = [];
    for (const month of ['2026-11', '2026-12']) {
        for (const date of datesInMonth(month)) {
            const weekend = isWeekendDate(date);
            for (const [start, end] of TIMES) {
                slots.push({
                    id: slotIdOf(date, start),
                    date,
                    start,
                    end,
                    capacity: CAPACITY,
                    trialOpen: weekend,
                    monthlyLimit: weekend ? settings.weekendMonthlyLimit : null,
                    blocked: CLOSED_DAYS.includes(date),
                });
            }
        }
    }
    const slotById = new Map(slots.map((s) => [s.id, s]));

    const members: Member[] = Array.from({ length: 40 }, (_, i) => {
        const id = `m${String(i + 1).padStart(2, '0')}`;
        return {
            id,
            name: `${SURNAMES[i % 20]} ${GIVEN[(i * 3 + Math.floor(i / 20) * 7) % 20]}`,
            phone: phone(),
            email: `${id}@example.com`,
            active: true,
        };
    });

    const reservations: Reservation[] = [];
    let seq = 0;
    const add = (r: Omit<Reservation, 'id' | 'createdAt' | 'status'> & { createdAt?: string }) => {
        seq += 1;
        const res: Reservation = { status: 'confirmed', createdAt: '2026-11-01T09:00:00', ...r, id: `r${String(seq).padStart(4, '0')}` };
        reservations.push(res);
        return res;
    };
    const addMonthly = (memberId: string, slotId: string, createdAt: string) => {
        const slot = slotById.get(slotId)!;
        if (!canBook(slot, reservations, 'monthly', 1)) throw new Error(`seed: 月謝が入らない ${slotId}`);
        add({ slotId, kind: 'monthly', channel: 'member', partySize: 1, amount: 0, memberId, createdAt });
    };

    // 月謝の固定配置：11/21 13:00 に3名（体験3名と合わせて満席にする）、m01 は日程変更をデモできるよう未来の回を持つ
    const fixed: Record<string, string[]> = {
        m01: [slotIdOf('2026-11-05', '10:00'), slotIdOf('2026-11-12', '13:00'), slotIdOf('2026-11-25', '10:00'), slotIdOf('2026-11-27', '15:30')],
        m05: [slotIdOf('2026-11-21', '13:00')],
        m06: [slotIdOf('2026-11-21', '13:00')],
        m07: [slotIdOf('2026-11-21', '13:00')],
    };

    /** 月謝4回を、平日を中心に別々の日で入れる */
    const fillMonth = (memberId: string, month: string, preset: string[], createdAt: string) => {
        const usedDates = new Set<string>();
        for (const id of preset) {
            addMonthly(memberId, id, createdAt);
            usedDates.add(slotById.get(id)!.date);
        }
        const open = slots.filter((s) => s.date.startsWith(month) && !s.blocked);
        const weekdays = open.filter((s) => !isWeekendDate(s.date));
        const weekends = open.filter((s) => isWeekendDate(s.date));
        let guard = 0;
        while (usedDates.size < settings.sessionsPerMonth) {
            if (++guard > 1000) throw new Error(`seed: ${memberId} の ${month} が埋まらない`);
            const slot = rand() < 0.85 ? pick(weekdays) : pick(weekends);
            if (usedDates.has(slot.date) || !canBook(slot, reservations, 'monthly', 1)) continue;
            addMonthly(memberId, slot.id, createdAt);
            usedDates.add(slot.date);
        }
    };

    const memberMonths: MemberMonth[] = [];
    // 固定配置のある生徒を先に入れる（ランダムな配置に枠を取られないように）
    const novOrder = [...members.filter((m) => fixed[m.id]), ...members.filter((m) => !fixed[m.id])];
    for (const m of novOrder) {
        fillMonth(m.id, '2026-11', fixed[m.id] ?? [], '2026-10-20T10:00:00');
        memberMonths.push({ memberId: m.id, month: '2026-11', submittedAt: '2026-10-20T10:00:00', changeUsed: false });
    }
    // 12月：m02〜m26 の25名が提出済み。m01 と m27〜m40 は未提出
    for (const m of members) {
        const n = Number(m.id.slice(1));
        const submitted = n >= 2 && n <= 26;
        if (submitted) fillMonth(m.id, '2026-12', [], '2026-11-18T10:00:00');
        memberMonths.push({ memberId: m.id, month: '2026-12', submittedAt: submitted ? '2026-11-18T10:00:00' : null, changeUsed: false });
    }

    // 体験：月謝の後に入れる
    const addTrial = (slotId: string, partySize: number, channel: Channel, extra: Partial<Reservation> = {}) => {
        const slot = slotById.get(slotId)!;
        if (!canBook(slot, reservations, 'trial', partySize)) return false;
        const name = pick(TRIAL_NAMES);
        add({
            slotId,
            kind: 'trial',
            channel,
            partySize,
            amount: settings.trialPrice * partySize,
            contact: { name, phone: phone(), email: channel === 'web' ? 'guest@example.com' : undefined },
            cancelToken: channel === 'web' ? `t${seq + 1}x${Math.floor(rand() * 1e6)}` : undefined,
            createdAt: '2026-11-15T12:00:00',
            ...extra,
        });
        return true;
    };

    // デモ用の固定予約（DemoBar の 3日前・前日・当日 はこの予約が基準）
    addTrial(slotIdOf('2026-11-22', '10:00'), 2, 'web', {
        cancelToken: 'demo',
        contact: { name: '森下 体験', phone: '090-0000-0000', email: 'demo@example.com' },
    });
    // 11/21 13:00 を 月謝3＋体験3 で満席に
    addTrial(slotIdOf('2026-11-21', '13:00'), 2, 'web');
    addTrial(slotIdOf('2026-11-21', '13:00'), 1, 'phone');
    // 11/21〜22 のそのほか
    addTrial(slotIdOf('2026-11-21', '10:00'), 1, 'web');
    addTrial(slotIdOf('2026-11-21', '15:30'), 2, 'dm');
    addTrial(slotIdOf('2026-11-22', '13:00'), 1, 'phone');
    addTrial(slotIdOf('2026-11-22', '15:30'), 1, 'web');
    // 11月末と12月の土日にまばらに
    const channels: Channel[] = ['web', 'web', 'phone', 'dm'];
    for (const s of slots) {
        if (s.date < '2026-11-28' || !s.trialOpen || s.blocked) continue;
        if (rand() < 0.3) addTrial(s.id, 1 + Math.floor(rand() * 2), pick(channels));
    }

    return { slots, reservations, members, memberMonths, settings, today: SEED_TODAY, role: 'customer', currentMemberId: 'm01', seq };
}
