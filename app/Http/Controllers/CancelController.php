<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Refund;
use App\Enums\ReservationKind;
use App\Http\Presenters\BookingPresenter;
use App\Models\Reservation;
use App\Services\Booking\CancellationService;
use App\Support\JaDate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** キャンセル用リンク（/r/{token}/cancel）。ReserveCancelProps。リンクは予約確認メールだけに入る */
class CancelController extends Controller
{
    public function show(string $token): Response
    {
        $p = BookingPresenter::make();
        $r = $this->find($token, $p);
        $date = $r?->slot->dateString();
        $reason = '';
        if ($date) {
            $reason = $date >= JaDate::addDays($p->today(), 2) ? '2日前までのため、決済手数料を引いて返金します'
                : ($date === JaDate::addDays($p->today(), 1) ? '前日のため、50%を返金します' : '当日のため、返金はありません');
        }

        return Inertia::render('reserve/cancel', [
            'reservation' => $r ? [
                'id' => $r->code, 'partySize' => $r->party_size, 'amount' => $r->amount, 'name' => (string) $r->contact_name,
                'canceled' => ! $r->holdsSeat($p->now()), 'refundedAmount' => $r->refunded_amount,
            ] : null,
            'slotLabel' => $r ? $p->slotLabel($r->slot) : '',
            'token' => $token,
            'todayLabel' => JaDate::dayLabel($p->today()),
            'refund' => $r ? Refund::amount($r->amount, $date, $p->today(), $p->settings()->stripe_rate) : 0,
            'refundReason' => $reason,
            'cancelPolicy' => $p->cancelPolicy(),
            'shopPhone' => $p->settings()->shop_phone,
        ]);
    }

    public function store(string $token, CancellationService $service): RedirectResponse
    {
        $r = $this->find($token, BookingPresenter::make());
        abort_unless($r, 404);
        $service->cancelByCustomer($r);

        return redirect()->route('reserve.cancel', $token);
    }

    /** トークンに合う Web の体験予約。レッスンの日を過ぎたリンクは使えない */
    private function find(string $token, BookingPresenter $p): ?Reservation
    {
        $r = Reservation::findByCancelToken($token);
        if (! $r || $r->kind !== ReservationKind::Trial) {
            return null;
        }
        $r->load('slot');

        return $r->slot->dateString() >= $p->today() ? $r : null;
    }
}
