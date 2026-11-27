<?php

namespace App\Domain\Booking;

use App\Models\Reservation;
use Carbon\CarbonImmutable;

/**
 * 返金額の計算（selectors.ts の refundAmount / isPrepaid を移したもの）。
 * tests/fixtures/refund-cases.json で FE と同じ結果になることを確かめる。
 */
final class Refund
{
    /**
     * 日数差（レッスンの日 − 今日）が 2 以上：お支払い額 − 決済手数料（四捨五入）
     * 1（前日）：50%（四捨五入）／ 0 以下（当日以降）：0
     * 四捨五入：PHP の round と JS の Math.round は、正の数の .5 ではどちらも切り上げで同じ。
     */
    public static function amount(int $amount, string $slotDate, string $today, float $rate): int
    {
        $diff = (int) round(CarbonImmutable::parse($today)->startOfDay()->diffInDays(CarbonImmutable::parse($slotDate)->startOfDay(), false));
        if ($diff >= 2) {
            return $amount - (int) round($amount * $rate);
        }
        if ($diff === 1) {
            return (int) round($amount * 0.5);
        }

        return 0;
    }

    /** 先に支払い済みの予約か（Web で予約した体験）。返金の対象になる */
    public static function isPrepaid(Reservation $r): bool
    {
        return $r->kind === \App\Enums\ReservationKind::Trial && $r->channel === \App\Enums\Channel::Web;
    }
}
