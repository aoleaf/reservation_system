<?php

namespace App\Enums;

/** 予約の種類：体験／月謝 */
enum ReservationKind: string
{
    case Trial = 'trial';
    case Monthly = 'monthly';
}
