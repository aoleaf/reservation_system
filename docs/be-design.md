# つちのね予約システム BE 設計

2026年11月17日

FE モック（docs/mock-design.md）を、Laravel の BE・DB・Stripe・メールにつなぐための設計。【要決定】の付いた項目は、作り始める前に決める（最後の「決めること」にまとめた）。

## 方針

- **画面は書き直さない**。コントローラーは `resources/js/types/pages.ts` と同じ形の props を返し、書き込みは `lib/actions/http.ts` の送り先で受ける。モックの builder（`mocks/props.ts`）と mock 版 actions（`mocks/actions.ts`）が、そのままコントローラーとサービスの仕様書になる
- **エラーのキーをモックと同じにする**（`slotId`、`contact`、`slotIds`、`toSlotId`、`reservationId`、`token`、`date`、`memberId`）。画面の `onError` がそのまま動く
- **計算は1か所**。`mocks/selectors.ts` を PHP のドメインクラスに移し、`tests/fixtures/refund-cases.json` で JS と同じ結果になることを確かめる
- **少しずつ切り替える**。`withMockProps` は「builder が無いページはそのまま表示する」ので、コントローラーができたページから builder を消せば、そのページだけ本番の props になる。actions も、移したものから http 版にする（下の「移行の進め方」）

## 全体の流れ

```
画面（pages/*）── props ──< コントローラー ── Presenter（props の形を作る）
     │                          │
     └─ actions(http) ─> コントローラー ─> FormRequest（形の検査）
                                   └─> サービス（トランザクション・席の確認）─> モデル / DB
                                              ├─> Stripe（Checkout・返金）
                                              └─> メール（キュー）
Stripe ─ webhook ─> StripeWebhookController ─> サービス（確定・期限切れ）
スケジューラー ─> 期限切れの仮押さえを戻す・枠を作る
```

## テーブル

日付は `date`、時刻は `time` で持つ。お金は円の整数（`unsignedInteger`）。

### settings（1行だけ）
| 列 | 型 | メモ |
|---|---|---|
| trial_price | int | 4500 |
| stripe_rate | decimal(5,4) | 0.036。返金額の計算用（実際の手数料は Stripe 側） |
| sessions_per_month | tinyint | 4 |
| weekend_monthly_limit | tinyint | 3 |
| shop_phone | string | 仮の値。森下様にいただく |
| schedule_open_day | tinyint | 仮に 1。森下様に確認中 |
| schedule_deadline_day | tinyint | 15 |
| checkout_hold_minutes | smallint | 仮押さえの時間。Stripe Checkout の期限は最短 30 分なので 30 以上。仮に 60（森下様に確認中） |

### slots
| 列 | 型 | メモ |
|---|---|---|
| id | bigint | |
| date | date | |
| start_time / end_time | time | 10:00 / 13:00 / 15:30 |
| capacity | tinyint | 6 |
| trial_open | bool | 土日だけ true |
| monthly_limit | tinyint null | 土日は weekend_monthly_limit、平日は null |
| status | enum(open, paused, closed) | 営業／受付停止／お休み |

- unique(date, start_time)
- 枠は `php artisan slots:generate {month}` で作る（3枠×毎日、土日は体験あり・月謝の上限あり）。毎月、スケジューラーで翌々月分を作る

### members
| 列 | 型 | メモ |
|---|---|---|
| id | bigint | |
| user_id | fk null | ログイン用（生徒のログイン方法による） |
| name / phone | string | |
| email | string unique | |
| active | bool | 退会したら false |

### member_months
| 列 | 型 | メモ |
|---|---|---|
| member_id | fk | |
| month | char(7) | 'YYYY-MM' |
| submitted_at | datetime null | 日程を出した日時 |
| change_used | bool | 月1回の日程変更を使ったか |

- unique(member_id, month)

### reservations
| 列 | 型 | メモ |
|---|---|---|
| id | bigint | |
| code | string unique | 画面に出す予約番号。`YYMM-` ＋ 見間違えにくい英数字6文字（例 `2612-K7Q3M9`）。番号だけでは個人情報を見られないようにする（下の「予約番号と個人情報」） |
| slot_id | fk | |
| kind | enum(trial, monthly) | |
| channel | enum(web, phone, dm, walkin, member, staff) | |
| party_size | tinyint | 月謝は 1 |
| status | enum(pending, confirmed, canceled, expired, attended, no_show) | モックに `expired` を足す |
| amount | int | 体験は単価×人数、月謝は 0 |
| refunded_amount | int null | |
| refund_status | enum(none, pending, succeeded, failed) | 返金の結果。failed はスタッフに見せる |
| expires_at | datetime null | pending の期限 |
| cancel_token_hash | char(64) unique null | キャンセル用リンクのトークンの SHA-256。トークンそのものは保存しない |
| contact_name / contact_phone / contact_email | string null | 体験のお客様 |
| member_id | fk null | 月謝 |
| created_by | fk(users) null | スタッフが入れた予約 |
| stripe_checkout_session_id | string unique null | |
| stripe_payment_intent_id | string null | 返金に使う |
| canceled_at | datetime null | |
| canceled_by | enum(customer, staff, system) null | |
| timestamps | | |

- index(slot_id, status)

### stripe_events
| 列 | 型 | メモ |
|---|---|---|
| id | string（主キー） | Stripe のイベント ID。同じ通知を2回処理しない |
| type | string | |
| processed_at | datetime | |

### users（スターターキットのもの）
- `role` enum(staff, member) を足す。スタッフの新規登録画面は閉じ、アカウントは artisan コマンドで作る

## 席の数え方とダブルブッキングの防止

- 使っている席 = その枠の予約のうち、`confirmed` と「期限内の `pending`」（`expires_at > now`）の人数の合計。モックの `seatsUsed` に「期限内」を足したもの
- 席を使う書き込み（Web 予約、スタッフの予約、月謝の提出・変更）はすべて、`DB::transaction` の中で枠を `lockForUpdate()` してから残席を数え直す。モックの `canBook` の確認を、ここでもう一度行う
- 本番の DB は **MySQL**。開発とテストは今の SQLite のまま進め、本番の準備（PR 5）で MySQL のコンテナと pdo_mysql を足す。マイグレーションは両方で動く書き方にする

## ドメイン（selectors の移植）

`app/Domain/Booking/` に置く。どれも純粋な計算で、DB を触らない。

| PHP | 元の JS | テスト |
|---|---|---|
| Seats::used / remaining / canBook | seatsUsed / remaining / canBook | 枠の状態・土日の上限・期限切れの pending |
| Refund::amount | refundAmount | `tests/fixtures/refund-cases.json` を読んで7件を照合（JS と同じ表） |
| ScheduleWindow::of | scheduleWindow | 前月1日〜15日、年またぎ |
| Payment::isPrepaid | isPrepaid | Web の体験だけ返金の対象 |

- 四捨五入：JS の `Math.round` と PHP の `round` は、正の数の .5 ではどちらも切り上げで同じ。表の「2250.5 → 2251」で確かめる

## ルート

ページ（GET）は今のルート名のまま、クロージャをコントローラーに移す。書き込みは `lib/actions/http.ts` の送り先に合わせる。

| メソッド・URL | 名前 | コントローラー | 認証 |
|---|---|---|---|
| GET /reserve ほか予約の流れ | reserve.* | Reserve\* | なし |
| POST /reserve | reserve.store | ReserveController@store | なし |
| POST /r/{token}/cancel | reserve.cancel.store | CancelController@store | なし（トークン） |
| POST /stripe/webhook | stripe.webhook | StripeWebhookController | Stripe の署名（CSRF の対象外） |
| GET /member ほか | member.* | Member\* | 生徒 |
| POST /member/schedule/{month} | member.schedule.store | MemberScheduleController@store | 生徒 |
| POST /member/change/{reservation} | member.change.store | MemberChangeController@store | 生徒 |
| GET /admin ほか | admin.* | Admin\* | スタッフ |
| POST /admin/reservations | admin.reservations.store | Admin\ReservationController@store | スタッフ |
| POST /admin/reservations/{reservation}/cancel | admin.reservations.cancel | Admin\ReservationController@cancel | スタッフ |
| PATCH /admin/slots/{slot} | admin.slots.update | Admin\SlotController@update | スタッフ |
| PATCH /admin/days/{date} | admin.days.update | Admin\DayController@update | スタッフ |

- `/`（demo）は本番では消すか、お店のサイトへのリンクにする

## actions とサービスの対応

| action | サービス | サーバーで確かめること（モックの mock actions と同じ） |
|---|---|---|
| createWebReservation | ReservationService::createWeb | 枠がある・体験を受ける枠・前日まで・名前と電話とメール・残席。通ったら pending と Checkout Session を作る（次の章） |
| createStaffReservation | ReservationService::createByStaff | 体験：残席。月謝：同じ日に2回不可・月の回数まで・月謝の残席。4回目で提出済みにする |
| submitMonthlySchedule | MonthlyService::submit | 受付期間内・未提出・ちょうど4枠・同じ日に2枠不可・全枠の残席 |
| changeMonthlySession | MonthlyService::change | 本人の予約・月1回・前日まで・同じ月・同じ日に予約がない・残席 |
| setSlotStatus | SlotService::setStatus | お休みは予約がない枠だけ |
| setDayStatus | SlotService::setDayStatus | 営業と受付停止の枠だけを切り替える。お休みの枠はそのまま |
| cancelByToken | ReservationService::cancelByToken | トークンが合う・まだ有効。返金額を計算して Stripe で返金 |
| cancelByStaff | ReservationService::cancelByStaff | まだ有効。返金はお客様と同じ計算（森下様の回答 Q1 しだいで「全額」を足す） |

## Stripe（体験の Web 予約）

- ライブラリは `stripe/stripe-php`。Laravel Cashier は定期課金向けなので使わない
- 決済画面は **ホスト型の Checkout（Stripe のページへ移る）**。いちばん軽く、FE に Stripe のライブラリも決済のページも要らない。Checkout Session の URL へ `Inertia::location()` で移るだけ。あとで埋め込み（Embedded Checkout）に変えても、Session の作り方は同じで、違いは FE だけ

### 流れ
1. 確認画面の「支払う」→ `POST /reserve`
   - トランザクションで枠をロックし、残席を確かめる
   - `pending` の予約を作る（`expires_at = now + checkout_hold_minutes`）
   - Checkout Session を作る：金額＝単価×人数、`expires_at` を同じ期限に、`metadata.reservation_id`、冪等キー＝`checkout-{予約ID}`
   - Checkout Session の URL へ移る（`Inertia::location()`）
2. お客様が払う → Stripe が `checkout.session.completed` を webhook で送る
   - `stripe_events` に同じ ID があれば何もしない
   - 予約が `pending` なら `confirmed` にし、`payment_intent` を保存し、予約確認メールを送る
3. 決済から戻る → `/reserve/complete/{code}`
   - webhook がまだ届いていなければ「お支払いを確認中」を出し、数秒ごとに読み直す（Inertia の partial reload）
4. 払わずに期限が来た → `checkout.session.expired` で `expired` にして席を戻す
   - 念のため、スケジューラーでも1分ごとに期限切れの `pending` を `expired` にする

### 二重請求・すれ違いを防ぐ
- Checkout Session は予約1件に1つ。作るときの冪等キーで、ボタンの連打やリトライでも2つ作らない
- webhook は `stripe_events` で重複を捨て、状態が `pending` のときだけ確定する
- **期限切れのあとに支払いが届いた**（遅れて完了の通知が来た）：席が空いていれば確定し、空いていなければ全額を自動で返金して、お客様にメールで知らせる
- 返金は `Refund::create(payment_intent, amount)`。冪等キー＝`refund-{予約ID}`。失敗したら `refund_status = failed` にして、スタッフの予約一覧に出す

### FE に足すもの
- `ReserveCompleteProps` に `paymentStatus: 'pending' | 'confirmed' | 'expired'` を足し、「確認中」「時間切れで席を戻しました」を出す
- 決済から戻る URL（`success_url`）は署名付きにする（下の「予約番号と個人情報」）

## メール

- Laravel の Mailable を、キュー（今の `QUEUE_CONNECTION=database`）で送る
- 送信サービスは **Resend**（無料プランがあり、Laravel に送信の仕組みが最初からある）。開発中は今の `MAIL_MAILER=log` のまま。本番では送信元ドメインの DNS 設定が要る
- 送るもの
  | メール | いつ | 中身 |
  |---|---|---|
  | 予約確認 | 決済の確定（webhook） | 日時・人数・金額・キャンセル規定・キャンセル用リンク・お店の電話 |
  | キャンセル受付 | お客様・スタッフのキャンセル | 返金額と、返金の目安の日数 |
  | 支払いが間に合わなかった | 期限切れのあとに支払いが届き、席が無かった | 全額返金したこと |
  | お店への通知【要決定】 | Web 予約が入ったとき | 要るかどうか森下様に確認 |
- キャンセル用リンク：`/r/{token}/cancel`。トークンは32バイトの乱数で、DB にはハッシュだけを持つ。レッスンの日を過ぎたら使えない

## ログイン

- **スタッフ**：スターターキットのログイン（メール＋パスワード）を使い、`role = staff` だけが `/admin` に入れる。新規登録の画面は閉じる
- **生徒**：**メールのリンクでログイン**（パスワードなし）。40名の生徒さんにパスワードを覚えてもらわずに済む。パスワード方式も「パスワードを忘れた」のメールで入れるので、強さは同じ
  - リンクは1回だけ使え、15分で切れる（トークンはハッシュで保存）
  - リンクを開いただけではログインしない。開いた画面の「ログインする」ボタン（POST）でログインする（メールのウイルスチェックがリンクを先に開いても使われないように）
  - 送信は、同じメールアドレス・同じ IP から回数を制限する。登録されていないメールアドレスでも同じ表示にする（生徒かどうかを当てられないように）
  - ログインしたままにする期間を長めにとる（remember）
- 生徒の画面は、ログインしている生徒の `members.id` で絞る（モックの `currentMemberId` に当たる）

## 予約番号と個人情報

- 予約番号は推測されにくい形（年月＋ランダム6文字）にするが、**番号だけで個人情報を見られる作りにはしない**
- 完了画面（`/reserve/complete/{code}`）は、決済から戻るときの署名付き URL（有効期限つき）でだけ開ける。期限が切れたら「予約確認メールをご覧ください」とだけ出す
- キャンセルは、番号ではなく別のトークン（メールのリンク）で行う

## スケジューラー

| コマンド | 間隔 | 内容 |
|---|---|---|
| reservations:expire-holds | 1分 | 期限切れの pending を expired にして席を戻す |
| slots:generate | 毎月1日 | 翌々月の枠を作る |
| （公開後）日程のリマインド | 15日の前 | 未提出の生徒にメール |

## 移行の進め方（PR の単位）

| PR | 中身 | 終わったら |
|---|---|---|
| 1. BE の土台 | マイグレーション、モデル、settings、開発用の seeder（モックの seed を移植）、ドメインと PHPUnit（refund-cases.json） | `php artisan test` が通る。画面はまだモックのまま |
| 2. スタッフ画面 | スタッフのログイン、admin のコントローラーと Presenter、admin 系の書き込み | admin のページの builder を `mocks/props.ts` から消し、admin の actions を http 版にする |
| 3. 生徒画面 | 生徒のログイン、member のコントローラーと書き込み | member の builder と actions を本番に |
| 4. お客様画面と決済 | reserve のコントローラー、Stripe（テストモード）、webhook、メール | reserve の builder と actions を本番に |
| 5. 仕上げ | `mocks/` を消す、`app.tsx` を戻す、`lib/actions/index.ts` を http 版だけにする、スケジューラー、本番の設定 | VITE_MOCK を消して動く |

- PR 2〜4 の間、`lib/actions/index.ts` は「移したものは http、残りは mock」にする
- 各 PR で、ページの props が `types/pages.ts` と同じ形になっていることを Feature テスト（`assertInertia`）で確かめる

## 決めること

### 決めたこと（11/17）
1. 生徒のログイン：メールのリンク
2. Stripe：ホスト型の Checkout（あとで埋め込みに変えられる）
3. メール：Resend
4. 本番の DB：MySQL（開発・テストは SQLite）
5. 予約番号：`YYMM-` ＋ ランダム6文字。番号だけでは個人情報を見られない
6. 仮押さえの時間：仮に 60 分（settings で変えられる。森下様に確認）

### 森下様に聞く（mock-design.md の「まだ確認が必要」の続き）
- Q1 お店の都合でキャンセルしたときは全額返金にするか → スタッフのキャンセルに「全額返金」の選択を足すか決まる（PR 2 より前に）
- Q2 お休みの枠から移ってもらった生徒の変更回数を数えるか → 今の作りは「数えない」
- Q3 日程の受付開始日、お店の電話番号
- 仮押さえの時間（払わずに離れた人の席がふさがる時間。6席の枠なので短めがよいかも）
- Web 予約が入ったとき、お店に通知メールが要るか
- 料金・締め日などを、お店が自分で変えられる画面が要るか（今は settings を直接変える）
- 出欠（来た／来なかった）を記録するか（status には attended・no_show を用意してある）
