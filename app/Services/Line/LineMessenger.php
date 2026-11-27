<?php

namespace App\Services\Line;

/** LINE に送る窓口。本物は LineMessagingApi、トークンが無いときは LogLineMessenger（送らずにログだけ） */
interface LineMessenger
{
    /**
     * テキストを1通送る。retryKey が同じなら、やり直しても2回は届かない。
     * 送れなければ例外。
     */
    public function push(string $to, string $text, string $retryKey): void;

    /** LINE からの通知（webhook）の署名が正しいか */
    public function verifySignature(string $body, ?string $signature): bool;
}
