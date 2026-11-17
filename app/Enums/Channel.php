<?php

namespace App\Enums;

/** 予約の経路。Walkin は来店（口頭） */
enum Channel: string
{
    case Web = 'web';
    case Phone = 'phone';
    case Dm = 'dm';
    case Walkin = 'walkin';
    case Member = 'member';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Phone => '電話',
            self::Dm => 'DM',
            self::Walkin => '来店',
            self::Member => '生徒',
            self::Staff => 'スタッフ',
        };
    }
}
