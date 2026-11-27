<?php

namespace App\Services\Payments;

use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;
use Stripe\Webhook;

/** 本物の Stripe（ホスト型 Checkout）。金額は円（JPY は小数なし） */
class StripePaymentGateway implements PaymentGateway
{
    private StripeClient $client;

    public function __construct(string $secret, private readonly string $webhookSecret)
    {
        $this->client = new StripeClient($secret);
    }

    public function createCheckout(Reservation $reservation, string $productName, string $successUrl, string $cancelUrl, CarbonImmutable $expiresAt): array
    {
        $meta = ['reservation_id' => (string) $reservation->id, 'code' => $reservation->code];
        $session = $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => 'jpy',
                    'unit_amount' => intdiv($reservation->amount, max(1, $reservation->party_size)),
                    'product_data' => ['name' => $productName],
                ],
                'quantity' => $reservation->party_size,
            ]],
            'customer_email' => $reservation->contact_email,
            'client_reference_id' => $reservation->code,
            'metadata' => $meta,
            'payment_intent_data' => ['metadata' => $meta],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'expires_at' => $expiresAt->getTimestamp(),
        ], ['idempotency_key' => "checkout-{$reservation->id}"]);

        return ['id' => $session->id, 'url' => $session->url];
    }

    public function expireCheckout(string $sessionId): void
    {
        try {
            $this->client->checkout->sessions->expire($sessionId);
        } catch (InvalidRequestException) {
            // もう期限切れか、支払いが済んだページ
        }
    }

    public function refund(string $paymentIntentId, int $amount, string $idempotencyKey): string
    {
        return $this->client->refunds->create(['payment_intent' => $paymentIntentId, 'amount' => $amount], ['idempotency_key' => $idempotencyKey])->id;
    }

    public function parseWebhook(string $payload, ?string $signature): array
    {
        $event = Webhook::constructEvent($payload, (string) $signature, $this->webhookSecret);
        $object = $event->data->object;
        $isSession = str_starts_with($event->type, 'checkout.session.');

        return [
            'id' => $event->id,
            'type' => $event->type,
            'sessionId' => $isSession ? $object->id : null,
            'paymentIntentId' => $isSession ? ($object->payment_intent ?? null) : null,
            'paid' => $isSession && ($object->payment_status ?? null) === 'paid',
        ];
    }
}
