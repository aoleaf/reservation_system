<?php

namespace App\Domain\Booking;

use App\Enums\ReservationKind;
use App\Models\Reservation;
use App\Models\Slot;
use Carbon\CarbonInterface;

/**
 * 残席の計算（resources/js/mocks/selectors.ts の seatsUsed / remaining / canBook を移したもの）。
 * DB を触らない。予約は呼ぶ側で読み込んで渡す。
 * FE との違いは1つ：pending は期限内のものだけを数える。
 */
final class Seats
{
    /**
     * 使っている席の数。kind を渡すとその種類だけ。
     *
     * @param  iterable<Reservation>  $reservations
     */
    public static function used(Slot $slot, iterable $reservations, ?ReservationKind $kind, CarbonInterface $now): int
    {
        $sum = 0;
        foreach ($reservations as $r) {
            if ($r->slot_id === $slot->id && $r->holdsSeat($now) && ($kind === null || $r->kind === $kind)) {
                $sum += $r->party_size;
            }
        }

        return $sum;
    }

    /**
     * その種類で、あと何席入れられるか。
     * 営業していない枠は 0。体験は体験を受ける枠だけ。月謝は土日の上限（monthly_limit）も見る。
     *
     * @param  iterable<Reservation>  $reservations
     */
    public static function remaining(Slot $slot, iterable $reservations, ReservationKind $kind, CarbonInterface $now): int
    {
        if (! $slot->isOpen()) {
            return 0;
        }
        $reservations = is_array($reservations) ? $reservations : iterator_to_array($reservations, false);
        $free = $slot->capacity - self::used($slot, $reservations, null, $now);
        if ($kind === ReservationKind::Trial) {
            return $slot->trial_open ? max(0, $free) : 0;
        }
        if ($slot->monthly_limit === null) {
            return max(0, $free);
        }

        return max(0, min($free, $slot->monthly_limit - self::used($slot, $reservations, ReservationKind::Monthly, $now)));
    }

    /** @param  iterable<Reservation>  $reservations */
    public static function canBook(Slot $slot, iterable $reservations, ReservationKind $kind, int $partySize, CarbonInterface $now): bool
    {
        return $partySize >= 1 && self::remaining($slot, $reservations, $kind, $now) >= $partySize;
    }
}
