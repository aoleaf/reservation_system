<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 月謝の生徒さん */
class Member extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function months(): HasMany
    {
        return $this->hasMany(MemberMonth::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** その月の提出・日程変更の記録（無ければ未保存の新しい行） */
    public function monthOf(string $month): MemberMonth
    {
        return $this->months()->firstOrNew(['month' => $month], ['change_used' => false]);
    }
}
