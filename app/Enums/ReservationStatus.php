<?php

namespace App\Enums;

/** 予約の状態。Expired は仮押さえの期限切れ（席を戻した） */
enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Canceled = 'canceled';
    case Expired = 'expired';
    case Attended = 'attended';
    case NoShow = 'no_show';
}
