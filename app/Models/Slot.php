<?php

namespace App\Models;

use App\Enums\SlotStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 1枠（ある日の 10:00-12:00 など）。
 *
 * @property CarbonImmutable $date
 * @property string $start_time 'HH:MM:SS'
 * @property string $end_time
 * @property int $capacity
 * @property bool $trial_open
 * @property int|null $monthly_limit
 * @property SlotStatus $status
 */
class Slot extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'capacity' => 'integer',
            'trial_open' => 'boolean',
            'monthly_limit' => 'integer',
            'status' => SlotStatus::class,
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** 席を使っている予約（confirmed と、期限内の pending） */
    public function holdingReservations(?CarbonImmutable $now = null): HasMany
    {
        return $this->reservations()->holdingSeat($now);
    }

    /** 'YYYY-MM-DD'（FE の ISODate） */
    public function dateString(): string
    {
        return $this->date->format('Y-m-d');
    }

    /** '10:00'（FE と同じ形。DB には秒まで入る） */
    public function startHm(): string
    {
        return substr($this->start_time, 0, 5);
    }

    public function endHm(): string
    {
        return substr($this->end_time, 0, 5);
    }

    public function isOpen(): bool
    {
        return $this->status === SlotStatus::Open;
    }

    /** お休みにできるのは、席を使っている予約が無い枠だけ */
    public function hasHoldingReservations(?CarbonImmutable $now = null): bool
    {
        return $this->holdingReservations($now)->exists();
    }
}
