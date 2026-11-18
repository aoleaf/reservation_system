<?php

namespace App\Enums;

/** ログインする人の立場 */
enum UserRole: string
{
    case Staff = 'staff';
    case Member = 'member';
}
