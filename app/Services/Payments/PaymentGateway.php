<?php

namespace App\Services\Payments;

use App\Models\Reservation;
use Carbon\CarbonImmutable;

/**
 * 決済の窓口（体験の Web 予約）。予約の処理からはここだけを呼ぶ。
 * 本物は StripePaymentGateway。Stripe のキーが無い開発環境では FakePaymentGateway（本番では使わない）。
 */
interface PaymentGateway
{
    /**
     * 決済のページ（Checkout Session）を作る。同じ予約で2回呼んでも1つしか作らない（冪等キー）。
     *
     * @return array{id: string, url: string}
     */
    public function createCheckout(Reservation $reservation, string $productName, string $successUrl, string $cancelUrl, CarbonImmutable $expiresAt): array;

    /** 決済のページを使えなくする（払わずに戻ったとき）。もう使えないページなら何もしない */
    public function expireCheckout(string $sessionId): void;

    /** 返金する。同じキーなら2回返金しない。返金の ID を返す */
    public function refund(string $paymentIntentId, int $amount, string $idempotencyKey): string;

    /**
     * webhook の中身を、署名を確かめてから読む。署名が合わなければ例外。
     *
     * @return array{id: string, type: string, sessionId: ?string, paymentIntentId: ?string, paid: bool}
     */
    public function parseWebhook(string $payload, ?string $signature): array;
}
