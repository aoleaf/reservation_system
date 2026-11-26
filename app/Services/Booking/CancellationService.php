<?php

namespace App\Services\Booking;

use App\Domain\Booking\Refund;
use App\Enums\CanceledBy;
use App\Enums\CancelReason;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * キャンセル。返金は先に払っている Web の体験だけが対象。
 * お客様の都合：お客様のキャンセルと同じ規定（2日前まで手数料を引く・前日50%・当日0）
 * お店の都合：全額返金（決済手数料はお店の負担。森下様の回答 11/18）
 */
class CancellationService
{
    public function __construct(private readonly RefundGateway $refunds) {}

    public function cancelByStaff(Reservation $reservation, CancelReason $reason): Reservation
    {
        return $this->cancel($reservation, CanceledBy::Staff, $reason);
    }

    /** 返金額（先に払っていない予約は 0） */
    public static function refundFor(Reservation $r, CancelReason $reason, string $today, float $rate): int
    {
        if (! Refund::isPrepaid($r)) {
            return 0;
        }

        return $reason === CancelReason::Shop ? $r->amount : Refund::amount($r->amount, $r->slot->dateString(), $today, $rate);
    }

    private function cancel(Reservation $reservation, CanceledBy $by, CancelReason $reason): Reservation
    {
        [$r, $refund] = DB::transaction(function () use ($reservation, $by, $reason) {
            $now = CarbonImmutable::now();
            $r = Reservation::query()->lockForUpdate()->with('slot')->findOrFail($reservation->id);
            if (! $r->holdsSeat($now)) {
                throw ValidationException::withMessages(['reservationId' => 'この予約はキャンセル済みです']);
            }
            $refund = self::refundFor($r, $reason, $now->format('Y-m-d'), Setting::current()->stripe_rate);
            $r->update([
                'status' => ReservationStatus::Canceled,
                'canceled_at' => $now,
                'canceled_by' => $by,
                'cancel_reason' => $reason,
                'refunded_amount' => $refund,
                'refund_status' => $refund > 0 ? RefundStatus::Pending : RefundStatus::None,
            ]);

            return [$r, $refund];
        });

        // お金を動かすのはトランザクションの外で（失敗しても予約のキャンセルは残し、refund_status で分かるようにする）
        if ($refund > 0) {
            $this->refunds->refund($r, $refund);
        }

        return $r;
    }
}
