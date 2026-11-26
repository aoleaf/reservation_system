<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * お店の設定（1行だけ）。FE の Settings 型に対応する。
 */
class Setting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'trial_price' => 'integer',
            'stripe_rate' => 'float',
            'sessions_per_month' => 'integer',
            'weekend_monthly_limit' => 'integer',
            'schedule_open_day' => 'integer',
            'schedule_open_months_before' => 'integer',
            'schedule_deadline_day' => 'integer',
            'checkout_hold_minutes' => 'integer',
        ];
    }

    /** 今の設定。行が無ければ初期値で作る */
    public static function current(): self
    {
        return self::query()->first() ?? self::query()->create(self::defaults());
    }

    /** 初期値。shop_phone・checkout_hold_minutes は仮（森下様に確認中） */
    public static function defaults(): array
    {
        return [
            'trial_price' => 4500,
            'stripe_rate' => 0.036,
            'sessions_per_month' => 4,
            'weekend_monthly_limit' => 3,
            'shop_phone' => '000-0000-0000',
            'schedule_open_day' => 1,
            'schedule_open_months_before' => 2, // 毎月1日に翌々月の受付を始める（Q3）
            'schedule_deadline_day' => 15,
            'checkout_hold_minutes' => 60,
        ];
    }
}
