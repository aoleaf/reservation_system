<?php

namespace App\Services\Payments;

use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * 開発用の偽物の決済（Stripe のキーが無いとき）。本番では使わない（AppServiceProvider で止める）。
 * 決済のページは /dev/checkout/{id}（DevCheckoutController）。webhook は Stripe と同じ形の JSON を、署名なしで読む。
 */
class FakePaymentGateway implements PaymentGateway
{
    public function createCheckout(Reservation $reservation, string $productName, string $successUrl, string $cancelUrl, CarbonImmutable $expiresAt): array
    {
        $id = 'cs_fake_'.$reservation->code;
        Cache::put("fake-checkout:{$id}", [
            'reservation_id' => $reservation->id, 'product' => $productName, 'amount' => $reservation->amount,
            'success_url' => $successUrl, 'cancel_url' => $cancelUrl, 'expired' => false,
        ], $expiresAt);

        return ['id' => $id, 'url' => route('dev.checkout.show', $id)];
    }

    public function expireCheckout(string $sessionId): void
    {
        $s = Cache::get("fake-checkout:{$sessionId}");
        if ($s) {
            Cache::put("fake-checkout:{$sessionId}", ['expired' => true] + $s, now()->addHour());
        }
    }

    public function refund(string $paymentIntentId, int $amount, string $idempotencyKey): string
    {
        return Cache::rememberForever("fake-refund:{$idempotencyKey}", fn () => 're_fake_'.Str::random(12));
    }

    public function parseWebhook(string $payload, ?string $signature): array
    {
        $event = json_decode($payload, true);
        if (! is_array($event) || ! isset($event['id'], $event['type'])) {
            throw new InvalidArgumentException('webhook の形が違います');
        }
        $object = $event['data']['object'] ?? [];
        $isSession = str_starts_with($event['type'], 'checkout.session.');

        return [
            'id' => $event['id'],
            'type' => $event['type'],
            'sessionId' => $isSession ? ($object['id'] ?? null) : null,
            'paymentIntentId' => $isSession ? ($object['payment_intent'] ?? null) : null,
            'paid' => $isSession && ($object['payment_status'] ?? null) === 'paid',
        ];
    }

    /** 開発用の決済画面が使う：決済のページの中身 */
    public static function session(string $id): ?array
    {
        return Cache::get("fake-checkout:{$id}");
    }
}
