<?php

namespace App\Services\Line;

use Illuminate\Support\Facades\Log;

/** トークンが無いとき（開発中など）：送らずにログに残す。webhook の署名はシークレットがあれば確かめる */
class LogLineMessenger implements LineMessenger
{
    public function __construct(private readonly string $secret = '') {}

    public function push(string $to, string $text, string $retryKey): void
    {
        Log::info("LINE（送らずにログだけ）to={$to}\n{$text}");
    }

    public function verifySignature(string $body, ?string $signature): bool
    {
        return $this->secret !== '' && $signature !== null
            && hash_equals(base64_encode(hash_hmac('sha256', $body, $this->secret, true)), $signature);
    }
}
