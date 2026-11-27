<?php

namespace App\Enums;

/** 枠の状態：営業／受付停止（今の予約は残し、新しい予約だけ止める）／お休み */
enum SlotStatus: string
{
    case Open = 'open';
    case Paused = 'paused';
    case Closed = 'closed';
}
