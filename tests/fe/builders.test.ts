import { builders } from '@/mocks/props';
import { createSeed } from '@/mocks/seed';
import { describe, expect, it } from 'vitest';

const seed = createSeed();
const garbage = [
    {},
    { slotId: 'xxx', n: 'abc', reservationId: 'nope', token: 'nope', month: '2026-13', date: '2026-02-30', memberId: 'zz', filter: 'x' },
    { slotId: ['a'], n: '-3', month: 'abc', date: '' },
    { n: '0' },
];

describe('builder は不正な URL のパラメーターでも落ちず、NaN を出さない', () => {
    for (const [name, build] of Object.entries(builders)) {
        it.each(garbage)(`${name} %o`, (sp) => {
            const props = build(seed, sp as Record<string, unknown>);
            expect(JSON.stringify(props)).not.toMatch(/NaN|null,"?undefined/);
        });
    }
});

describe('n は 1 に丸める', () => {
    it.each(['abc', '0', '-2', ''])('n=%s', (n) => {
        const p = builders['reserve/confirm'](seed, { slotId: 's-20261128-1000', n }) as { partySize: number; amount: number };
        expect(p.partySize).toBe(1);
        expect(p.amount).toBe(seed.settings.trialPrice);
    });
});
