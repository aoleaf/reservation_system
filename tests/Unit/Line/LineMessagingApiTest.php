<?php

namespace Tests\Unit\Line;

use App\Services\Line\LineMessagingApi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/** LINE Messaging API への送り方（通信はしない） */
class LineMessagingApiTest extends TestCase
{
    public function test_push_はトークンとやり直しのキーを付けて送る(): void
    {
        Http::fake(['api.line.me/*' => Http::response([], 200)]);
        (new LineMessagingApi('token-x', 'secret'))->push('Cgroup', 'こんにちは', 'key-1');
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.line.me/v2/bot/message/push'
            && $r->hasHeader('Authorization', 'Bearer token-x')
            && $r->hasHeader('X-Line-Retry-Key', LineMessagingApi::uuidFrom('key-1'))
            && (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', LineMessagingApi::uuidFrom('key-1'))
            && $r['to'] === 'Cgroup' && $r['messages'][0]['text'] === 'こんにちは');
    }

    public function test_同じキーで送り済み_409_は成功として扱い_ほかの失敗は例外(): void
    {
        Http::fake(['api.line.me/*' => Http::sequence()->push([], 409)->push(['message' => 'bad'], 400)]);
        $api = new LineMessagingApi('t', 's');
        $api->push('C', 'a', 'k'); // 409：例外にしない
        $this->expectException(RuntimeException::class);
        $api->push('C', 'a', 'k2');
    }

    public function test_webhook_の署名を確かめる(): void
    {
        $api = new LineMessagingApi('t', 'channel-secret');
        $body = '{"events":[]}';
        $sig = base64_encode(hash_hmac('sha256', $body, 'channel-secret', true));
        $this->assertTrue($api->verifySignature($body, $sig));
        $this->assertFalse($api->verifySignature($body, base64_encode(hash_hmac('sha256', $body, 'other', true))));
        $this->assertFalse($api->verifySignature($body, null));
    }
}
