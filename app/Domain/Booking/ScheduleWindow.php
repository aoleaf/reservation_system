<?php

namespace App\Domain\Booking;

use Carbon\CarbonImmutable;

/**
 * 月謝の日程を出せる期間（selectors.ts の scheduleWindow を、森下様の回答 Q3 に合わせて変えたもの）。
 * 受付開始：openMonthsBefore か月前の openDay 日 ／ 締め：前月の deadlineDay 日（両端を含む）。
 * 例（2か月前の1日〜前月15日）：1月分は 11/1〜12/15。月の前半は2か月分が同時に開く。
 */
final class ScheduleWindow
{
    /**
     * @param  string  $month  'YYYY-MM'（日程を出す月）
     * @param  string  $today  'YYYY-MM-DD'
     * @return array{opens: string, closes: string, state: 'before'|'open'|'closed'}
     */
    public static function of(string $month, int $openMonthsBefore, int $openDay, int $deadlineDay, string $today): array
    {
        $first = CarbonImmutable::parse($month.'-01');
        $opens = sprintf('%s-%02d', $first->subMonthsNoOverflow($openMonthsBefore)->format('Y-m'), $openDay);
        $closes = sprintf('%s-%02d', $first->subMonthNoOverflow()->format('Y-m'), $deadlineDay);
        $state = $today < $opens ? 'before' : ($today > $closes ? 'closed' : 'open');

        return ['opens' => $opens, 'closes' => $closes, 'state' => $state];
    }

    /** settings の値で */
    public static function forMonth(string $month, \App\Models\Setting $s, string $today): array
    {
        return self::of($month, $s->schedule_open_months_before, $s->schedule_open_day, $s->schedule_deadline_day, $today);
    }
}
