# 公開の手順（本番）

サーバーはまだ決まっていない。どのサーバーでも必要なことをまとめる。

## 1. 用意するもの

| もの | 使うところ | メモ |
|---|---|---|
| サーバー（PHP 8.3・MySQL 8・HTTPS） | アプリ本体 | Docker で動かすなら docker-compose.yml をもとにする |
| ドメイン | お客様の予約ページ・メールの送信元 | |
| Stripe のアカウント | 体験の Web 予約の決済 | 最初はテストモードで確かめる |
| Resend のアカウント | メール | 送信元ドメインの DNS 設定（SPF・DKIM）が要る |
| お店の電話番号 | キャンセルの案内・メール | 今は仮の 000-0000-0000（settings.shop_phone） |
| 生徒さんの一覧と予約の CSV | 最初のデータ | docs/be-design.md の「CSV の取り込み」 |

## 2. 本番の .env

| キー | 値 |
|---|---|
| APP_ENV | production |
| APP_DEBUG | false |
| APP_URL | https://（ドメイン） |
| APP_TIMEZONE | Asia/Tokyo |
| DB_CONNECTION ほか | MySQL の接続先 |
| QUEUE_CONNECTION | database |
| MAIL_MAILER | resend |
| RESEND_KEY | Resend の API キー |
| MAIL_FROM_ADDRESS | Resend で確認したドメインのアドレス |
| MAIL_FROM_NAME | つちのね |
| STRIPE_SECRET | Stripe のシークレットキー（本番は sk_live_…） |
| STRIPE_WEBHOOK_SECRET | webhook の署名シークレット（whsec_…） |

- 本番で STRIPE_SECRET が空だと、決済のところでエラーにして止まる（開発用の偽物の決済画面は本番では出ない）
- APP_DEBUG は必ず false（エラー画面に設定の値が出てしまうため）

## 3. 公開の手順

```
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # public/hot が無いことを確かめる
php artisan key:generate         # 初回だけ
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan slots:generate 2026-12 && php artisan slots:generate 2027-01   # 最初の枠（以降は毎月1日に自動）
php artisan staff:create （お店のメールアドレス） --name=森下
php artisan import:members 生徒.csv --dry-run && php artisan import:members 生徒.csv
php artisan import:monthly 12月の予約.csv --dry-run && php artisan import:monthly 12月の予約.csv
```

- お休みの日は、スタッフの管理画面のカレンダーで設定する
- 開発用のデータ（BookingDemoSeeder）は本番では入らない（`db:seed` を本番で流しても入れない）

## AWS（EC2 とロードバランサー）で気をつけること

- HTTPS はロードバランサー（ALB）で受け、EC2 には HTTP で渡す形を想定している。アプリはロードバランサーの `X-Forwarded-*` を信用する設定にしてある（bootstrap/app.php の trustProxies）
- そのため、**EC2 のセキュリティグループは、ロードバランサーからだけ 80 番を受けるように絞る**こと。EC2 に直接届くと、IP を偽ったヘッダーで回数制限をすり抜けられる
- APP_URL は https の URL にする

## 4. ずっと動かしておくもの

| もの | コマンド | 止まると |
|---|---|---|
| キューのワーカー | `php artisan queue:work --tries=3 --backoff=30` | 予約確認・キャンセル受付のメールが届かない |
| スケジューラー | `php artisan schedule:run` を毎分（cron）か `schedule:work` | 期限切れの仮押さえが状態に残る・翌々月の枠が作られない |

## 5. Stripe

1. ダッシュボードで webhook を登録：`https://（ドメイン）/stripe/webhook`、イベントは `checkout.session.completed`・`checkout.session.async_payment_succeeded`・`checkout.session.expired`
2. テストモードのキーで、テスト用カード（4242 4242 4242 4242）を使い、予約 → 支払い → 予約確認メール → キャンセル → 返金 までを通す
3. 問題が無ければ本番のキーに入れ替える

## 6. 公開の前に確かめること

- [ ] お客様：予約 → 支払い → 予約確認メールが届く → メールのリンクからキャンセル → 返金される
- [ ] 支払わずに戻ると、席が戻る
- [ ] 生徒さん：ログインのメールが届く → マイページ → 日程の提出・変更
- [ ] スタッフ：ログイン → 予約一覧 → 電話予約の入力 → キャンセル（お店の都合は全額返金）→ カレンダーでお休み
- [ ] お店の電話番号が本物になっている（管理画面にはまだ設定の画面が無いので、DB の settings を直接変える）

## まだ決まっていないこと

- サーバーとドメイン
- お店の電話番号・仮押さえの時間（今は60分）
- 色（シック・和風）の差し替え
