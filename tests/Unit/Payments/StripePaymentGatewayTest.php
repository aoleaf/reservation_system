<?php

namespace Tests\Unit\Payments;

use App\Services\Payments\StripePaymentGateway;
use Stripe\Exception\SignatureVerificationException;
use Tests\TestCase;

/** webhook の署名の確かめ方（Stripe と同じ作り方で署名して確かめる。通信はしない） */
class StripePaymentGatewayTest extends TestCase
{
    private function sign(string $payload, string $secret, ?int $t = null): string
    {
        $t ??= time();

        return "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", $secret);
    }

    private function payload(): string
    {
        return json_encode([
            'id' => 'evt_1', 'object' => 'event', 'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_1', 'object' => 'checkout.session', 'payment_intent' => 'pi_1', 'payment_status' => 'paid']],
        ]);
    }

    public function test_正しい署名なら読める(): void
    {
        $gw = new StripePaymentGateway('sk_test_x', 'whsec_test');
        $e = $gw->parseWebhook($this->payload(), $this->sign($this->payload(), 'whsec_test'));
        $this->assertSame(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'sessionId' => 'cs_test_1', 'paymentIntentId' => 'pi_1', 'paid' => true], $e);
    }

    public function test_署名が違えば読まない(): void
    {
        $gw = new StripePaymentGateway('sk_test_x', 'whsec_test');
        $this->expectException(SignatureVerificationException::class);
        $gw->parseWebhook($this->payload(), $this->sign($this->payload(), 'whsec_other'));
    }

    public function test_古い署名は読まない(): void
    {
        $gw = new StripePaymentGateway('sk_test_x', 'whsec_test');
        $this->expectException(SignatureVerificationException::class);
        $gw->parseWebhook($this->payload(), $this->sign($this->payload(), 'whsec_test', time() - 3600));
    }
}
