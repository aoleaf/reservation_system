<?php

namespace App\Services\Booking;

use App\Models\Reservation;

/**
 * 返金を実際に行うところ。PR 4 で Stripe の返金（Refund API）につなぐ。
 * 返したら reservation の refund_status を succeeded / failed にする。
 */
interface RefundGateway
{
    public function refund(Reservation $reservation, int $amount): void;
}
