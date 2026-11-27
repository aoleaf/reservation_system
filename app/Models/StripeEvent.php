<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 処理済みの Stripe の webhook（同じ通知を2回処理しないため） */
class StripeEvent extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['processed_at' => 'immutable_datetime'];
    }
}
