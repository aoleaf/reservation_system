# tsuchinone-reservation

- 仕様は docs/mock-design.md（画面）と docs/be-design.md（BE・DB・Stripe・メール）。作業の前に必ず読む
- 画面（pages/・components/）は props だけを受け取る。props はコントローラーが作り、形は types/pages.ts に書く。画面で残席・金額・可否を計算しない
- 書き込みは '@/lib/actions'（http 版）経由。pages に router.post / router.visit を書かない（画面遷移は <Link> か actions）
- 残席・返金・受付期間の計算は app/Domain/Booking だけに置く。返金は tests/fixtures/refund-cases.json で確かめる
- 席を使う書き込みは、トランザクションの中で枠を lockForUpdate してから数え直す
- 日付は 'YYYY-MM-DD' の文字列。「今日」は日本時間（Asia/Tokyo）
- 金額・定員・締め日は settings テーブルと config/booking.php から読む。定数を直書きしない
- リンクは Ziggy の route('ルート名', params) で作る（Wayfinder は未導入）
- Composer / Artisan / テストは docker compose exec app で。npm は WSL 側で
- コミットは意思決定ごと、PR は機能ごと。git add はパスを明示し、コミットの前に php artisan test を通す
- コメントと説明は日本語
