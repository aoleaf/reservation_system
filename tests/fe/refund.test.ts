import { refundAmount } from '@/mocks/selectors';
import { describe, expect, it } from 'vitest';
import fixture from '../fixtures/refund-cases.json';

describe('refundAmount（tests/fixtures/refund-cases.json）', () => {
    it.each(fixture.cases)('$note: $amount円 / $slotDate / 今日 $today → $expected円', ({ amount, slotDate, today, expected }) => {
        expect(refundAmount(amount, slotDate, today, fixture.rate)).toBe(expected);
    });
});
