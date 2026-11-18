<?php

namespace App\Enums;

/** 誰がキャンセルしたか */
enum CanceledBy: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case System = 'system';
}
