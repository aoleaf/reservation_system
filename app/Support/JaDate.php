<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * 日付の表示と計算（resources/js/mocks/dates.ts と同じ形の文字列を返す）。
 * 日付は 'YYYY-MM-DD'、月は 'YYYY-MM' の文字列でやりとりする。
 */
final class JaDate
{
    private const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /** 今日（日本時間） */
    public static function today(): string
    {
        return CarbonImmutable::now()->format('Y-m-d');
    }

    public static function isDate(mixed $v): bool
    {
        if (! is_string($v) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    public static function isMonth(mixed $v): bool
    {
        return is_string($v) && (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v);
    }

    public static function weekday(string $date): string
    {
        return self::WEEKDAYS[self::weekdayIndex($date)];
    }

    /** 0=日 〜 6=土 */
    public static function weekdayIndex(string $date): int
    {
        return CarbonImmutable::parse($date)->dayOfWeek;
    }

    public static function addDays(string $date, int $n): string
    {
        return CarbonImmutable::parse($date)->addDays($n)->format('Y-m-d');
    }

    public static function monthOf(string $date): string
    {
        return substr($date, 0, 7);
    }

    public static function shiftMonth(string $month, int $n): string
    {
        return CarbonImmutable::parse($month.'-01')->addMonthsNoOverflow($n)->format('Y-m');
    }

    /** @return list<string> */
    public static function datesInMonth(string $month): array
    {
        $first = CarbonImmutable::parse($month.'-01');

        return array_map(fn (int $d) => $first->addDays($d)->format('Y-m-d'), range(0, $first->daysInMonth - 1));
    }

    /** '11月21日（土）' */
    public static function dayLabel(string $date): string
    {
        $d = CarbonImmutable::parse($date);

        return "{$d->month}月{$d->day}日（".self::weekday($date).'）';
    }

    /** '2026年12月' */
    public static function monthLabel(string $month): string
    {
        $d = CarbonImmutable::parse($month.'-01');

        return "{$d->year}年{$d->month}月";
    }
}
