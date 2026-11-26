<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 生徒ごと・月ごとの、日程の提出と日程変更 */
class MemberMonth extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'immutable_datetime',
            'change_used' => 'boolean',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** 日程変更の残り回数（月1回） */
    public function changeRemaining(): int
    {
        return $this->change_used ? 0 : 1;
    }
}
