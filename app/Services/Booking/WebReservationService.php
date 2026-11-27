<?php

namespace App\Services\Booking;

use App\Domain\Booking\Seats;
use App\Enums\Channel;
use App\Enums\ReservationKind;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Slot;
use App\Services\Payments\PaymentGateway;
use App\Support\JaDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * お客様の体験の Web 予約：仮押さえ（pending）を作り、決済のページへ。
 * 確定は Stripe の通知（PaymentEventHandler）で行う。払わずに戻ったら abandon で席を戻す。
 */
class WebReservationService
{
    public function __construct(private readonly PaymentGateway $payments) {}

    /**
     * @param  array{slotId: int|string, partySize: int, contact: array{name: string, phone: string, email: string}}  $input
     * @return string 決済のページの URL
     */
    public function start(array $input): string
    {
        $settings = Setting::current();
        $reservation = DB::transaction(function () use ($input, $settings) {
            $now = CarbonImmutable::now();
            $slot = Slot::query()->lockForUpdate()->find($input['slotId']);
            if (! $slot || ! $slot->trial_open || $slot->dateString() < JaDate::addDays($now->format('Y-m-d'), 1)) {
                throw ValidationException::withMessages(['slotId' => '枠が見つかりません']);
            }
            $n = (int) $input['partySize'];
            if (! Seats::canBook($slot, $slot->holdingReservations($now)->get(), ReservationKind::Trial, $n, $now)) {
                throw ValidationException::withMessages(['slotId' => StaffReservationService::FULL]);
            }

            return Reservation::query()->create([
                'code' => Reservation::newCode($now),
                'slot_id' => $slot->id,
                'kind' => ReservationKind::Trial,
                'channel' => Channel::Web,
                'party_size' => $n,
                'status' => ReservationStatus::Pending,
                'amount' => $settings->trial_price * $n,
                'expires_at' => $now->addMinutes($settings->checkout_hold_minutes),
                'contact_name' => $input['contact']['name'],
                'contact_phone' => $input['contact']['phone'],
                'contact_email' => mb_strtolower($input['contact']['email']),
            ]);
        });

        $slot = $reservation->slot;
        $label = JaDate::dayLabel($slot->dateString()).' '.$slot->startHm().'〜'.$slot->endHm();
        try {
            $checkout = $this->payments->createCheckout(
                $reservation,
                "体験レッスン {$label}",
                URL::temporarySignedRoute('reserve.complete', $reservation->expires_at->addDay(), ['code' => $reservation->code]),
                URL::temporarySignedRoute('reserve.abandon', $reservation->expires_at, ['code' => $reservation->code]),
                $reservation->expires_at,
            );
        } catch (Throwable $e) {
            Log::error('決済のページを作れませんでした', ['reservation' => $reservation->code, 'error' => $e->getMessage()]);
            $reservation->update(['status' => ReservationStatus::Expired]);
            throw ValidationException::withMessages(['slotId' => '決済の準備ができませんでした。時間をおいて、もう一度お試しください']);
        }
        $reservation->update(['stripe_checkout_session_id' => $checkout['id']]);

        return $checkout['url'];
    }

    /** 決済のページで「戻る」を押した：仮押さえを解除して、決済のページも使えなくする */
    public function abandon(Reservation $reservation): void
    {
        $released = DB::transaction(function () use ($reservation) {
            $r = Reservation::query()->lockForUpdate()->find($reservation->id);
            if ($r?->status !== ReservationStatus::Pending) {
                return false;
            }
            $r->update(['status' => ReservationStatus::Expired]);

            return true;
        });
        if ($released && $reservation->stripe_checkout_session_id) {
            $this->payments->expireCheckout($reservation->stripe_checkout_session_id);
        }
    }
}
