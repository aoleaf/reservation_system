<?php

namespace App\Mail;

use App\Http\Presenters\BookingPresenter;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** 予約確認メール（決済が確定したとき）。キャンセル用のリンクはこのメールだけに入る */
class ReservationConfirmed extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Reservation $reservation, public readonly string $cancelUrl)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "【つちのね】体験レッスンのご予約（予約番号 {$this->reservation->code}）");
    }

    public function content(): Content
    {
        $p = BookingPresenter::make();

        return new Content(text: 'mail.reservation-confirmed', with: [
            'slotLabel' => $p->slotLabel($this->reservation->slot),
            'cancelPolicy' => $p->cancelPolicy(),
            'shopPhone' => $p->settings()->shop_phone,
        ]);
    }
}
