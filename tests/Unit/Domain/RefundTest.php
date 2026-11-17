<?php

namespace Tests\Unit\Domain;

use App\Domain\Booking\Refund;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** FE（tests/fe/refund.test.ts）と同じ表で、返金額が一致することを確かめる */
class RefundTest extends TestCase
{
    public static function cases(): array
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../../fixtures/refund-cases.json'), true);
        $out = [];
        foreach ($fixture['cases'] as $c) {
            $out[$c['note']] = [$c['amount'], $c['slotDate'], $c['today'], (float) $fixture['rate'], $c['expected']];
        }

        return $out;
    }

    #[DataProvider('cases')]
    public function test_返金額が_FE_と同じ(int $amount, string $slotDate, string $today, float $rate, int $expected): void
    {
        $this->assertSame($expected, Refund::amount($amount, $slotDate, $today, $rate));
    }

    public function test_表は7件以上ある(): void
    {
        $this->assertGreaterThanOrEqual(7, count(self::cases()));
    }
}
