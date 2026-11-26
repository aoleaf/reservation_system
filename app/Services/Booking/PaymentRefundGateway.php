<?php

namespace App\Services\Booking;

use App\Enums\RefundStatus;
use App\Models\Reservation;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 返金を決済の窓口（Stripe）で行う。同じ予約は2回返金しない（冪等キー refund-{予約ID}）。
 * 支払いの記録が無い・失敗したときは refund_status を failed にして、スタッフが分かるようにする。
 */
class PaymentRefundGateway implements RefundGateway
{
    public function __construct(private readonly PaymentGateway $payments) {}

    public function refund(Reservation $reservation, int $amount): void
    {
        if (! $reservation->stripe_payment_intent_id) {
            Log::warning('支払いの記録が無いので返金できません（手で返金してください）', ['reservation' => $reservation->code, 'amount' => $amount]);
            $reservation->update(['refund_status' => RefundStatus::Failed]);

            return;
        }
        try {
            $this->payments->refund($reservation->stripe_payment_intent_id, $amount, "refund-{$reservation->id}");
            $reservation->update(['refund_status' => RefundStatus::Succeeded]);
        } catch (Throwable $e) {
            Log::error('返金に失敗しました', ['reservation' => $reservation->code, 'amount' => $amount, 'error' => $e->getMessage()]);
            $reservation->update(['refund_status' => RefundStatus::Failed]);
        }
    }
}
