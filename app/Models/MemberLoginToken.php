<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 生徒のログイン用リンクのトークン（1回だけ・期限つき） */
class MemberLoginToken extends Model
{
    public const LIFETIME_MINUTES = 15;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** トークンを作り、ハッシュだけを保存する。返したトークンはメールに入れるだけ */
    public static function issue(Member $member): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        self::query()->create([
            'member_id' => $member->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => CarbonImmutable::now()->addMinutes(self::LIFETIME_MINUTES),
        ]);

        return $token;
    }

    /** まだ使えるトークン（未使用・期限内）を探す。無ければ null */
    public static function findUsable(string $token): ?self
    {
        $row = self::query()->where('token_hash', hash('sha256', $token))->first();

        return $row && $row->used_at === null && $row->expires_at->isFuture() ? $row : null;
    }
}
