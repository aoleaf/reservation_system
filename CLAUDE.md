# tsuchinone-reservation（FEモック期）

- 仕様は docs/mock-design.md。作業の前に必ず読む
- pages/ と components/ は props だけを受け取る。mocks/ を import しない。画面で残席・金額・可否を計算しない
- 書き込みは '@/lib/actions' 経由。pages に router.post / router.visit を書かない（画面遷移は <Link> か actions）
- selectors を呼んでよいのは mocks/props.ts と mocks/actions.ts だけ
- 日付は 'YYYY-MM-DD' の文字列。今日はストアの today。new Date() を使わない
- 金額・定員は settings から読む。定数を直書きしない
- リンクは Ziggy の route('ルート名', params) で作る（Wayfinder は未導入）
- Composer / Artisan は docker compose exec app で。npm は WSL 側で
- コメントと説明は日本語
