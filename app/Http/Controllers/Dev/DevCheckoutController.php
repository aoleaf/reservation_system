<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentEventHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Contracts\View\View;

/**
 * 開発用の偽物の決済画面（Stripe のキーが無いときだけ。本番ではルートを作らない）。
 * 本物の Stripe の決済ページの代わりに、「テストで支払う」「支払わずに戻る」を選べる。
 */
class DevCheckoutController extends Controller
{
    public function show(string $id): View
    {
        $session = FakePaymentGateway::session($id);
        abort_unless($session, 404, 'この決済のページはありません（期限切れ）');

        return view('dev.checkout', ['id' => $id, 'session' => $session]);
    }

    /** 「テストで支払う」：Stripe と同じ形の通知を作り、本物の webhook と同じ処理に渡す */
    public function pay(string $id, PaymentEventHandler $handler): RedirectResponse
    {
        $session = FakePaymentGateway::session($id);
        abort_unless($session && ! $session['expired'], 404, 'この決済のページは使えません');
        $handler->handle([
            'id' => 'evt_fake_'.Str::random(12),
            'type' => 'checkout.session.completed',
            'sessionId' => $id,
            'paymentIntentId' => 'pi_fake_'.Str::random(12),
            'paid' => true,
        ]);

        return redirect()->away($session['success_url']);
    }
}
