<?php

namespace App\Services\Line;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** LINE Messaging API（push メッセージ）。通数は月の無料枠を使うので、送るのは朝のまとめと追加の予約だけ */
class LineMessagingApi implements LineMessenger
{
    private const PUSH_URL = 'https://api.line.me/v2/bot/message/push';

    /** LINE のテキストは 1通 5,000 文字まで */
    private const MAX_TEXT = 5000;

    public function __construct(private readonly string $token, private readonly string $secret) {}

    public function push(string $to, string $text, string $retryKey): void
    {
        $res = Http::withToken($this->token)
            ->withHeaders(['X-Line-Retry-Key' => self::uuidFrom($retryKey)])
            ->timeout(10)
            ->post(self::PUSH_URL, ['to' => $to, 'messages' => [['type' => 'text', 'text' => mb_substr($text, 0, self::MAX_TEXT)]]]);

        // 409 は同じ retryKey で送り済み（2回は届かない）
        if ($res->failed() && $res->status() !== 409) {
            throw new RuntimeException("LINE に送れませんでした（{$res->status()}）：".$res->body());
        }
    }

    /** やり直しのキーは UUID の形でないといけないので、文字列から決まった UUID を作る（同じ文字列なら同じ UUID） */
    public static function uuidFrom(string $key): string
    {
        $h = md5($key);

        return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20, 12));
    }

    public function verifySignature(string $body, ?string $signature): bool
    {
        if (! $signature || $this->secret === '') {
            return false;
        }

        return hash_equals(base64_encode(hash_hmac('sha256', $body, $this->secret, true)), $signature);
    }
}
