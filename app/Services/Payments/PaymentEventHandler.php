<?php

namespace App\Services\Payments;

use App\Domain\Booking\Seats;
use App\Enums\CanceledBy;
use App\Enums\RefundStatus;
use App\Enums\ReservationKind;
use App\Enums\ReservationStatus;
use App\Mail\PaymentTooLate;
use App\Mail\ReservationConfirmed;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\StripeEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stripe の通知（webhook）の処理。開発用の決済画面の「テストで支払う」も、ここを通る。
 * ・同じ通知は2回処理しない（stripe_events）
 * ・確定するのは pending のときだけ。期限が切れてから支払いが届いたら、席が空いていれば確定、無ければ全額返金
 */
class PaymentEventHandler
{
    public function __construct(private readonly PaymentGateway $payments) {}

    /** @param  array{id: string, type: string, sessionId: ?string, paymentIntentId: ?string, paid: bool}  $event */
    public function handle(array $event): void
    {
        try {
            StripeEvent::query()->create(['id' => $event['id'], 'type' => $event['type'], 'processed_at' => CarbonImmutable::now()]);
        } catch (UniqueConstraintViolationException) {
            return; // もう処理した通知
        }

        match ($event['type']) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $event['paid'] ? $this->paid($event['sessionId'], $event['paymentIntentId']) : null,
            'checkout.session.expired' => $this->expired($event['sessionId']),
            default => null,
        };
    }

    private function paid(?string $sessionId, ?string $paymentIntentId): void
    {
        $result = DB::transaction(function () use ($sessionId, $paymentIntentId) {
            $now = CarbonImmutable::now();
            $r = Reservation::query()->where('stripe_checkout_session_id', $sessionId)->lockForUpdate()->first();
            if (! $r) {
                Log::warning('支払いの通知に合う予約がありません', ['session' => $sessionId]);

                return null;
            }
            if ($r->status === ReservationStatus::Confirmed) {
                return null; // もう確定している
            }
            $r->stripe_payment_intent_id = $paymentIntentId;

            if (! $r->holdsSeat($now)) {
                // 期限が切れてから支払いが届いた：席が空いていれば確定、無ければ全額返金
                $slot = Slot::query()->lockForUpdate()->find($r->slot_id);
                $others = $slot->holdingReservations($now)->where('id', '!=', $r->id)->get();
                if (! Seats::canBook($slot, $others, ReservationKind::Trial, $r->party_size, $now)) {
                    $r->fill(['status' => ReservationStatus::Expired, 'canceled_at' => $now, 'canceled_by' => CanceledBy::System,
                        'refunded_amount' => $r->amount, 'refund_status' => RefundStatus::Pending])->save();

                    return ['tooLate', $r, null];
                }
            }
            $token = $r->issueCancelToken();
            $r->fill(['status' => ReservationStatus::Confirmed, 'expires_at' => null])->save();

            return ['confirmed', $r, $token];
        });

        if (! $result) {
            return;
        }
        [$kind, $r, $token] = $result;
        if ($kind === 'confirmed') {
            Mail::to($r->contact_email)->send(new ReservationConfirmed($r, route('reserve.cancel', $token)));

            return;
        }
        // 間に合わなかった支払いは全額返金して、お知らせする
        try {
            $this->payments->refund($r->stripe_payment_intent_id, $r->amount, "refund-{$r->id}");
            $r->update(['refund_status' => RefundStatus::Succeeded]);
        } catch (Throwable $e) {
            Log::error('間に合わなかった支払いの返金に失敗しました', ['reservation' => $r->code, 'error' => $e->getMessage()]);
            $r->update(['refund_status' => RefundStatus::Failed]);
        }
        Mail::to($r->contact_email)->send(new PaymentTooLate($r));
    }

    private function expired(?string $sessionId): void
    {
        Reservation::query()
            ->where('stripe_checkout_session_id', $sessionId)
            ->where('status', ReservationStatus::Pending)
            ->update(['status' => ReservationStatus::Expired]);
    }
}
