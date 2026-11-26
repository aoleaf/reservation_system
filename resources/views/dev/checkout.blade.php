<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>開発用の決済画面</title>
    <style>
        body { font-family: sans-serif; background: #f6f4f0; color: #2b2a28; max-width: 420px; margin: 0 auto; padding: 24px 16px; }
        .note { background: #f8ead3; color: #9a5b13; padding: 8px 12px; border-radius: 6px; font-size: 13px; }
        .card { background: #fff; border: 1px solid #dcd7cf; border-radius: 8px; padding: 16px; margin: 16px 0; }
        button, a.back { display: block; width: 100%; box-sizing: border-box; padding: 12px; border-radius: 8px; font-size: 16px; font-weight: bold; text-align: center; margin-top: 12px; }
        button { background: #2f4a6b; color: #fff; border: 0; }
        a.back { background: #fff; color: #2b2a28; border: 1px solid #dcd7cf; text-decoration: none; }
    </style>
</head>
<body>
    <p class="note">開発用の決済画面です（Stripe のキーが無いため）。本番では Stripe の決済ページになります。お金は動きません。</p>
    <div class="card">
        <p>{{ $session['product'] }}</p>
        <p><strong>{{ number_format($session['amount']) }}円</strong></p>
    </div>
    @if ($session['expired'])
        <p>この決済のページは使えません（中止または期限切れ）。</p>
    @else
        <form method="post" action="{{ route('dev.checkout.pay', $id) }}">
            @csrf
            <button type="submit">テストで支払う</button>
        </form>
        <a class="back" href="{{ $session['cancel_url'] }}">支払わずに戻る</a>
    @endif
</body>
</html>
