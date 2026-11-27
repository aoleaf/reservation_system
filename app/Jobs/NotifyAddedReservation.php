<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Services\Line\LineNotices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** 朝のまとめのあとに入った今日の予約を、お店の LINE グループに送る（送るかどうかは LineNotices が決める） */
class NotifyAddedReservation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly Reservation $reservation) {}

    public function handle(LineNotices $notices): void
    {
        $notices->notifyIfAddedAfterDigest($this->reservation);
    }
}
