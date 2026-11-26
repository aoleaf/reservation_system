<?php

namespace App\Services\Booking;

use App\Models\Reservation;
use Illuminate\Support\Facades\Log;

/**
 * Stripe につなぐまでの返金：返金額を記録して refund_status を pending のままにする（実際のお金は動かない）。
 * PR 4 で Stripe の実装に差し替える。
 */
class PendingRefundGateway implements RefundGateway
{
    public function refund(Reservation $reservation, int $amount): void
    {
        Log::info('返金（未接続）', ['reservation' => $reservation->code, 'amount' => $amount]);
    }
}
