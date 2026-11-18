<?php

namespace App\Domain\Booking;

use Carbon\CarbonImmutable;

/**
 * 月謝の日程を出せる期間（selectors.ts の scheduleWindow を移したもの）。
 * 前月の openDay 日 〜 deadlineDay 日（両端を含む）。
 */
final class ScheduleWindow
{
    /**
     * @param  string  $month  'YYYY-MM'（日程を出す月）
     * @param  string  $today  'YYYY-MM-DD'
     * @return array{opens: string, closes: string, state: 'before'|'open'|'closed'}
     */
    public static function of(string $month, int $openDay, int $deadlineDay, string $today): array
    {
        $prev = CarbonImmutable::parse($month.'-01')->subMonthNoOverflow()->format('Y-m');
        $opens = sprintf('%s-%02d', $prev, $openDay);
        $closes = sprintf('%s-%02d', $prev, $deadlineDay);
        $state = $today < $opens ? 'before' : ($today > $closes ? 'closed' : 'open');

        return ['opens' => $opens, 'closes' => $closes, 'state' => $state];
    }
}
