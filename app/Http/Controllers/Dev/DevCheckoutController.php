<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Services\Payments\FakePaymentGateway;
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
}
