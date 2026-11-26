<?php

namespace App\Enums;

/** キャンセルの理由。お店の都合（お休みにするためにお願いした等）は全額返金（森下様の回答 11/18） */
enum CancelReason: string
{
    case Customer = 'customer';
    case Shop = 'shop';
}
