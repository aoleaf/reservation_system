<?php

namespace App\Mail;

use App\Http\Presenters\BookingPresenter;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** キャンセル受付のメール（お客様のキャンセル・スタッフのキャンセル） */
class CancellationReceipt extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Reservation $reservation)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "【つちのね】ご予約のキャンセルを承りました（予約番号 {$this->reservation->code}）");
    }

    public function content(): Content
    {
        $p = BookingPresenter::make();

        return new Content(text: 'mail.cancellation-receipt', with: ['slotLabel' => $p->slotLabel($this->reservation->slot), 'shopPhone' => $p->settings()->shop_phone]);
    }
}
