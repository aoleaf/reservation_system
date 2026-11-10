import { isWeekendDate } from '@/mocks/dates';
import { createSeed, slotIdOf } from '@/mocks/seed';
import { memberSessions, remaining, seatsUsed } from '@/mocks/selectors';
import { describe, expect, it } from 'vitest';

const seed = createSeed();
const { slots, reservations: rs, members, memberMonths } = seed;
const slot = (date: string, start: string) => slots.find((s) => s.id === slotIdOf(date, start))!;

describe('seed', () => {
    it('同じ seed からは同じデータができる', () => {
        expect(createSeed()).toEqual(seed);
    });

    it('生徒は40名', () => {
        expect(members).toHaveLength(40);
        expect(new Set(members.map((m) => m.name)).size).toBe(40);
    });

    it('11月は全員が4回', () => {
        for (const m of members) expect(memberSessions(m.id, '2026-11', rs, slots)).toHaveLength(4);
    });

    it('12月の提出済みは25名で、m01 は未提出', () => {
        const dec = memberMonths.filter((x) => x.month === '2026-12');
        expect(dec.filter((x) => x.submittedAt).length).toBe(25);
        expect(dec.find((x) => x.memberId === 'm01')!.submittedAt).toBeNull();
        for (const x of dec) {
            expect(memberSessions(x.memberId, '2026-12', rs, slots)).toHaveLength(x.submittedAt ? 4 : 0);
        }
    });

    it('1人の月謝は同じ日に2回入らない', () => {
        for (const m of members) {
            for (const month of ['2026-11', '2026-12']) {
                const dates = memberSessions(m.id, month, rs, slots).map((x) => x.slot.date);
                expect(new Set(dates).size).toBe(dates.length);
            }
        }
    });

    it('定員超えの枠がない・お休みの枠に予約がない', () => {
        for (const s of slots) {
            expect(seatsUsed(s, rs)).toBeLessThanOrEqual(s.capacity);
            if (s.blocked) expect(seatsUsed(s, rs)).toBe(0);
        }
    });

    it('土日の月謝は各枠3名以下、平日に体験がない', () => {
        for (const s of slots) {
            if (isWeekendDate(s.date)) expect(seatsUsed(s, rs, 'monthly')).toBeLessThanOrEqual(3);
            else expect(seatsUsed(s, rs, 'trial')).toBe(0);
        }
    });

    it('11/21 13:00 は月謝3＋体験3で満席', () => {
        const s = slot('2026-11-21', '13:00');
        expect(seatsUsed(s, rs, 'monthly')).toBe(3);
        expect(seatsUsed(s, rs, 'trial')).toBe(3);
        expect(remaining(s, rs, 'trial')).toBe(0);
    });

    it('デモ用の体験予約（cancelToken demo）が 11/22 10:00 に 2名 9,000円', () => {
        const r = rs.find((x) => x.cancelToken === 'demo')!;
        expect(r.slotId).toBe(slotIdOf('2026-11-22', '10:00'));
        expect(r.partySize).toBe(2);
        expect(r.amount).toBe(9000);
        expect(r.channel).toBe('web');
    });

    it('お休みの日は3枠とも blocked', () => {
        for (const d of ['2026-11-04', '2026-11-10', '2026-11-17', '2026-11-26', '2026-12-02', '2026-12-08', '2026-12-16', '2026-12-24']) {
            expect(slots.filter((s) => s.date === d && s.blocked)).toHaveLength(3);
        }
    });
});
