<?php

namespace App\Mail;

use App\Http\Presenters\BookingPresenter;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** 仮押さえの期限が切れてから支払いが届き、席が無かったとき（全額返金のお知らせ） */
class PaymentTooLate extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Reservation $reservation)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '【つちのね】ご予約をお取りできませんでした（全額返金）');
    }

    public function content(): Content
    {
        $p = BookingPresenter::make();

        return new Content(text: 'mail.payment-too-late', with: ['slotLabel' => $p->slotLabel($this->reservation->slot), 'shopPhone' => $p->settings()->shop_phone]);
    }
}
