<?php

namespace App\Models;

use App\Enums\ReservationKind;
use App\Enums\ReservationStatus;
use App\Support\MemberNumber;
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

    /** 会員番号を書かずに作ったときは、次の番号を振る */
    protected static function booted(): void
    {
        static::creating(function (Member $m) {
            $m->member_no ??= MemberNumber::next();
        });
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

    /**
     * その月の月謝の回（キャンセルと期限切れを除く）を日付順で。mocks の memberSessions に当たる
     *
     * @return \Illuminate\Support\Collection<int, Reservation>
     */
    public function sessionsIn(string $month): \Illuminate\Support\Collection
    {
        return $this->reservations()
            ->where('kind', ReservationKind::Monthly)
            ->whereNotIn('status', [ReservationStatus::Canceled, ReservationStatus::Expired])
            ->whereHas('slot', fn ($q) => $q->where('date', 'like', $month.'-%'))
            ->with('slot')
            ->get()
            ->sortBy(fn (Reservation $r) => $r->slot->dateString().$r->slot->start_time)
            ->values();
    }

    /** その月の提出・日程変更の記録（無ければ未保存の新しい行） */
    public function monthOf(string $month): MemberMonth
    {
        return $this->months()->firstOrNew(['month' => $month], ['change_used' => false]);
    }
}
