<?php

namespace Tests\Unit\Domain;

use App\Domain\Booking\ScheduleWindow;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** 日程の受付期間：2か月前の1日〜前月15日（森下様の回答 Q3） */
class ScheduleWindowTest extends TestCase
{
    public static function days(): array
    {
        return [
            '受付開始の前日' => ['2026-09-30', 'before'],
            '受付開始（2か月前の1日）' => ['2026-10-01', 'open'],
            '前月の締めの日' => ['2026-11-15', 'open'],
            '締めの次の日' => ['2026-11-16', 'closed'],
        ];
    }

    #[DataProvider('days')]
    public function test_12月分の受付期間(string $today, string $state): void
    {
        $this->assertSame($state, ScheduleWindow::of('2026-12', 2, 1, 15, $today)['state']);
    }

    public function test_1月分は11月1日から12月15日_年をまたぐ(): void
    {
        $this->assertSame(['opens' => '2026-11-01', 'closes' => '2026-12-15', 'state' => 'open'], ScheduleWindow::of('2027-01', 2, 1, 15, '2026-11-26'));
    }

    public function test_月の前半は2か月分が同時に開く(): void
    {
        $this->assertSame('open', ScheduleWindow::of('2027-01', 2, 1, 15, '2026-12-01')['state']);
        $this->assertSame('open', ScheduleWindow::of('2027-02', 2, 1, 15, '2026-12-01')['state']);
    }

    public function test_3月分は2月が前月_月末の繰り上がりが起きない(): void
    {
        $this->assertSame('2027-02-15', ScheduleWindow::of('2027-03', 2, 1, 15, '2027-02-01')['closes']);
    }
}
