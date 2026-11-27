<?php

namespace App\Models;

use App\Enums\CanceledBy;
use App\Enums\CancelReason;
use App\Enums\Channel;
use App\Enums\RefundStatus;
use App\Enums\ReservationKind;
use App\Enums\ReservationStatus;
use App\Jobs\NotifyAddedReservation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 予約（体験・月謝）。
 *
 * @property string $code
 * @property ReservationKind $kind
 * @property Channel $channel
 * @property int $party_size
 * @property ReservationStatus $status
 * @property int $amount
 * @property CarbonImmutable|null $expires_at
 */
class Reservation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['cancel_token_hash'];

    /** 予約番号に使う文字（0/O、1/I/L など見間違えやすい文字を除いた31文字） */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected function casts(): array
    {
        return [
            'kind' => ReservationKind::class,
            'channel' => Channel::class,
            'status' => ReservationStatus::class,
            'refund_status' => RefundStatus::class,
            'canceled_by' => CanceledBy::class,
            'cancel_reason' => CancelReason::class,
            'party_size' => 'integer',
            'amount' => 'integer',
            'refunded_amount' => 'integer',
            'expires_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
        ];
    }

    /** 予約が確定したら、LINE の追加の通知を考える（朝のまとめのあとに入った今日の予約だけが送られる） */
    protected static function booted(): void
    {
        static::saved(function (Reservation $r) {
            if ($r->status === ReservationStatus::Confirmed && ($r->wasRecentlyCreated || $r->wasChanged('status'))) {
                NotifyAddedReservation::dispatch($r)->afterCommit();
            }
        });
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * 席を使っている予約：confirmed と、期限内の pending。
     * FE の isActive に「期限内」を足したもの。
     */
    public function scopeHoldingSeat(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $now ??= CarbonImmutable::now();

        return $query->where(function (Builder $q) use ($now) {
            $q->where('status', ReservationStatus::Confirmed)
                ->orWhere(fn (Builder $p) => $p->where('status', ReservationStatus::Pending)->where('expires_at', '>', $now));
        });
    }

    public function holdsSeat(?CarbonInterface $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $this->status === ReservationStatus::Confirmed
            || ($this->status === ReservationStatus::Pending && $this->expires_at !== null && $this->expires_at->greaterThan($now));
    }

    /** 予約番号 'YYMM-XXXXXX'。年月は予約した月、後ろはランダム6文字（31^6 ≒ 8.9億通り） */
    public static function newCode(?CarbonInterface $at = null): string
    {
        $at ??= CarbonImmutable::now();
        do {
            $random = '';
            for ($i = 0; $i < 6; $i++) {
                $random .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $code = $at->format('ym').'-'.$random;
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * キャンセル用リンクのトークンを作り、ハッシュだけを保存する。
     * 返したトークンはメールに入れるだけで、どこにも保存しない。
     */
    public function issueCancelToken(): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->cancel_token_hash = self::hashToken($token);

        return $token;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByCancelToken(string $token): ?self
    {
        return self::query()->where('cancel_token_hash', self::hashToken($token))->first();
    }
}
