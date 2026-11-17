<?php

namespace Tests\Unit\Domain;

use App\Domain\Booking\ScheduleWindow;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** FE（tests/fe/seed.test.ts の scheduleWindow）と同じ場合を確かめる */
class ScheduleWindowTest extends TestCase
{
    public static function days(): array
    {
        return [
            '前月末より前' => ['2026-10-31', 'before'],
            '受付開始の日' => ['2026-11-01', 'open'],
            '締めの日' => ['2026-11-15', 'open'],
            '締めの次の日' => ['2026-11-16', 'closed'],
        ];
    }

    #[DataProvider('days')]
    public function test_12月分の受付期間(string $today, string $state): void
    {
        $this->assertSame($state, ScheduleWindow::of('2026-12', 1, 15, $today)['state']);
    }

    public function test_1月分は12月が前月_年をまたぐ(): void
    {
        $this->assertSame(['opens' => '2026-12-01', 'closes' => '2026-12-15', 'state' => 'open'], ScheduleWindow::of('2027-01', 1, 15, '2026-12-15'));
    }

    public function test_3月分は2月が前月_月末の繰り上がりが起きない(): void
    {
        $this->assertSame('2027-02-15', ScheduleWindow::of('2027-03', 1, 15, '2027-02-01')['closes']);
    }
}
