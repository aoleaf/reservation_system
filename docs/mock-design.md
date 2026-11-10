# つちのね予約システム FEモック 設計書と実装手順

2026年11月11日

> **実装メモ（この文書からの変更）**
> スターターキットには Wayfinder ではなく Ziggy が入っているため、リンクは Ziggy の `route('reserve.form', { slot })` で作る。ルート名はこの文書のとおり。

## ゴールと前提

今日 11:00 の meet で、森下様（講師）に優先度Aの12画面をスマホ幅で触ってもらえる状態にする。データはブラウザの中だけで動き、BE・DB・決済は使わない。

- 対象：下の「ルートと画面」で A の画面。B は間に合わなければ口頭で説明する
- 合格ライン：各画面の「こう動けばOK」を満たすこと。見た目の作り込みは後回し
- 作業時間：約7時間＋リハーサル15分。完成を優先する
- **絶対に守るもの**：画面は props だけを受け取り、書き込みは actions 経由で行う（次の章）。ここを崩すと、BE期にすべての画面を書き直すことになる
- この文書は docs/mock-design.md としてリポジトリに置き、AIへの指示では毎回これを読ませる

## アーキテクチャ

画面は props だけを受け取り、書き込みは必ず actions を通す。props を作るのは、モック中は `mocks/props.ts` の builder、本番ではコントローラー。この形なら、本番へ移るときに画面を書き直さなくて済む。

書き込むとストアが更新される。それを withMockProps が購読しているので、一覧や残席にすぐ反映される。

本番へ移るときにやることは3つだけ。

1. `mocks/` を消す
2. `app.tsx` の resolve をスターターキットの形に戻す
3. `lib/actions/index.ts` が http 版だけを export するようにし、`routes/web.php` のクロージャをコントローラーに移す。コントローラーは `types/pages.ts` と同じ形の props を返す

## ディレクトリと責務

`mocks/` の中は本番で丸ごと消す。それ以外は本番でもそのまま使う。パスは `resources/js/` からの相対パス。

| パス | 役割 | 本番では |
|---|---|---|
| types/models.ts | ドメインの型（Slot, Reservation など） | 残す |
| types/pages.ts | 画面ごとの props 型。BE期のコントローラーの仕様書になる | 残す |
| lib/actions/types.ts | 書き込みのインターフェース | 残す |
| lib/actions/http.ts | router.post で送る版 | 使う |
| lib/actions/index.ts | `import.meta.env.VITE_MOCK` で http 版と mock 版を切り替える | http 版だけを export |
| mocks/seed.ts | 初期データを作る（乱数を固定） | 消す |
| mocks/store.ts | zustand + persist（sessionStorage） | 消す |
| mocks/selectors.ts | 残席・予約可否・返金額の計算（純粋関数） | 消す。PHP に移植し、同じ fixture で照合する |
| mocks/props.ts | 画面名 → props を作る builder の表 | 消す（コントローラーが役目を引き継ぐ） |
| mocks/actions.ts | ストアを書き換えて router.visit する版 | 消す |
| mocks/with-mock-props.tsx | ページに props を注入し、DemoBar を上に描く | 消す |
| mocks/demo-bar.tsx | デモ用の上の帯。ストアを直接触ってよい唯一のUI | 消す |
| app.tsx | resolve で withMockProps をかぶせる（VITE_MOCK のときだけ） | resolve を戻す |
| components/* | MobileShell, AdminTabs, SlotCard, SeatMeter, MonthCalendar, StatusBadge | 残す |
| pages/* | demo, reserve/, member/, admin/* | 残す |
| routes/web.php（リポジトリ直下から） | クロージャで Inertia::render。URLのパラメーターを props で渡す | コントローラーに移す |
| tests/fixtures/refund-cases.json | 返金額の正解表 | 残す（PHP のテストでも使う） |

引き継ぎから変えた点が2つある。いずれも境界を守るためのもの。

- DemoBar を components/ から mocks/ に移した。ストアを読み書きするので、components/ にあると「mocks/ を消すだけ」では本番に移れなくなる
- selectors を呼んでよいのは mocks/props.ts と mocks/actions.ts の2つ。actions 側も canBook で空きを確かめないと、ダブルブッキングを防げない。本番でこの役割を担うのはサーバー側のバリデーション

## 型・selectors・ストア・seed

日付はどこでも `'YYYY-MM-DD'` の文字列で持ち、Date にしない。「今日」は必ずストアの today を使い、`new Date()` は使わない（DemoBar で日付を切り替えるため）。

### types/models.ts

```ts
export type ISODate = string; // 'YYYY-MM-DD'
export type SlotId = string;  // 中身をパースしない

export type Slot = {
  id: SlotId; date: ISODate; start: string; end: string; // '10:00'
  capacity: number;            // 6
  trialOpen: boolean;          // 土日だけ true
  monthlyLimit: number | null; // 土日は settings.weekendMonthlyLimit、平日は null
  blocked: boolean;            // お休み
};
export type ReservationKind = 'trial' | 'monthly';
export type Channel = 'web' | 'phone' | 'dm' | 'member' | 'staff';
export type ReservationStatus = 'pending' | 'confirmed' | 'canceled' | 'attended' | 'no_show';
export type Contact = { name: string; phone: string; email?: string };
export type Reservation = {
  id: string; slotId: SlotId; kind: ReservationKind; channel: Channel;
  partySize: number; status: ReservationStatus; amount: number;
  refundedAmount?: number; expiresAt?: string; cancelToken?: string;
  contact?: Contact; memberId?: string; createdAt: string;
};
export type Member = { id: string; name: string; phone: string; email: string; active: boolean };
export type MemberMonth = { memberId: string; month: string /* 'YYYY-MM' */; submittedAt: string | null; changeUsed: boolean };
export type Settings = { trialPrice: number; stripeRate: number; sessionsPerMonth: number; weekendMonthlyLimit: number };
```

### mocks/selectors.ts（すべて純粋関数）

| 関数 | 仕様 |
|---|---|
| seatsUsed(slot, rs, kind?) | slotId が一致し、status が pending か confirmed の予約の partySize の合計。kind を渡すとその種類だけ |
| remaining(slot, rs, kind) | blocked なら 0。trial なら trialOpen ? capacity−使用席 : 0。monthly なら min(capacity−使用席, monthlyLimit−月謝の使用席)。monthlyLimit が null なら前者だけ |
| canBook(slot, rs, kind, n) | remaining(...) >= n |
| refundAmount(amount, slotDate, today, rate) | 日数差 = differenceInCalendarDays(parseISO(slotDate), parseISO(today))。差が2以上なら amount−round(amount×rate)、1なら round(amount×0.5)、0以下なら 0 |
| memberSessions(memberId, month, rs, slots) | その月の月謝予約（canceled を除く）を日付順で返す |
| changeRemaining(mm) | mm.changeUsed ? 0 : 1 |

`tests/fixtures/refund-cases.json` の最低限の中身は次のとおり。rate は 0.036。端数の行は、JS と PHP で四捨五入がずれないかを確かめるためのもの。

| amount | slotDate | today | expected | 意味 |
|---|---|---|---|---|
| 4500 | 2026-11-22 | 2026-11-19 | 4338 | 3日前 |
| 4500 | 2026-11-22 | 2026-11-20 | 4338 | 2日前（境界） |
| 4500 | 2026-11-22 | 2026-11-21 | 2250 | 前日 |
| 4500 | 2026-11-22 | 2026-11-22 | 0 | 当日 |
| 9000 | 2026-11-22 | 2026-11-19 | 8676 | 2名 |
| 1111 | 2026-11-22 | 2026-11-19 | 1071 | 手数料 39.996 → 40 |
| 4501 | 2026-11-22 | 2026-11-21 | 2251 | 2250.5 → 2251 |

### mocks/store.ts

- zustand + persist。storage は sessionStorage、保存キーは `tsuchinone-mock-v${SEED_VERSION}`。seed を変えたら SEED_VERSION を上げる
- 持つもの：slots, reservations, members, memberMonths, settings, today, role（'customer' | 'member' | 'staff'）, currentMemberId
- 変更用の関数（addReservation, updateReservation, setSlotBlocked, setToday, setRole, reset など）もストアに置く。ただし、呼んでよいのは mocks/actions.ts と DemoBar だけ

### mocks/seed.ts

- 乱数は mulberry32(20261120) で固定する。Math.random は使わない
- today の初期値は 2026-11-20（金）。currentMemberId は m01
- 枠：11/1〜12/31 の全日に、3枠（10:00-12:00 / 13:00-15:00 / 15:30-17:30）を作る。土日は trialOpen=true、monthlyLimit=3
- お休みは固定の日で、その日は3枠とも blocked。11月は 4, 10, 17, 26 日、12月は 2, 8, 16, 24 日（いずれも平日）
- 月謝40名（m01〜m40、日本語の氏名）：11月は全員が4回。12月は m01〜m25 が提出済みで、m26〜m40 は未提出。平日を中心に入れ、土日は1枠あたり3名まで。入れるときは canBook を通す
- m01 は12月も未提出にする（「来月を選ぶ」をデモできるように。m02〜m26 を提出済みにして、25名という数は守る）
- 体験：月謝を入れた後に入れる。11/21（土）13:00 を月謝3＋体験3で満席にする。11/21〜22 にそのほか数件、12月の土日にもまばらに入れる。channel は web / phone / dm を混ぜる
- デモ用の体験予約を1件固定で入れる：11/22（日）10:00、2名、web、9,000円、cancelToken 'demo'。DemoBar の「3日前・前日・当日」はこの予約が基準（11/19、11/21、11/22）

## ルートと画面

A の画面は12個。すべてのルートに `->name()` を付ける。/ はスターターキットの welcome を置き換える。予約・生徒・スタッフの画面には auth ミドルウェアを付けない。

| URL | ルート名 | ページ | こう動けばOK | 優先 |
|---|---|---|---|---|
| / | demo | demo | 3つの立場への入口と、「確認用の画面です」の説明 | A |
| /reserve | reserve.index | reserve/index | 体験は土日だけ表示。各枠に残席（月謝の分も引かれる）。満席は押せない | A |
| /reserve/form?slot | reserve.form | reserve/form | 人数（1〜残席）と連絡先を入力。金額＝単価×人数が表示される | A |
| /reserve/confirm?slot&n&name&phone&email | reserve.confirm | reserve/confirm | 内容・金額・キャンセル規定を表示。「支払う」で完了へ進む。満席ならエラーを表示 | A |
| /reserve/complete/{id} | reserve.complete | reserve/complete | 予約番号と内容。キャンセル用のリンク | A |
| /r/{token}/cancel | reserve.cancel | reserve/cancel | today によって返金額が変わる | B |
| /member | member.index | member/index | 今月・来月の4回と「日程変更 残り1回」。来月が未提出なら「選ぶ」ボタン | A |
| /member/schedule/{month} | member.schedule | member/schedule | カレンダーから4つ選ぶまで提出できない。満席・上限・お休みの枠は選べない | A |
| /member/change/{id} | member.change | member/change | 同じ月の空き枠に移せる。1回変えると残り0回になり、ボタンが無効になる | A |
| /admin?date | admin.today | admin/today | 枠ごとに体験・月謝の人。支払済／当日払い／月謝の色分け。前後の日へ移動 | A |
| /admin/reservations/new?date&slot | admin.reservations.new | admin/reservation-new | 満席の枠は選べない。登録すると一覧と残席に反映される | A |
| /admin/slots?month | admin.slots | admin/slots | 各枠に「月謝3・体験2／6」。お休みの切替 | A |
| /admin/members | admin.members | admin/members | 40名の一覧。来月の未提出者がわかる（絞り込みがあるとよい） | A |
| /admin/members/{id} | admin.members.show | admin/member-show | 生徒の詳細、代理入力 | B |

引き継ぎと違う点が2つある。

- confirm のクエリーに連絡先（name, phone, email）を足した。「入力途中の値はクエリーに置き、ストアに持たない」というルールをそのまま当てはめたため。ただし個人情報が URL に載るので、本番の形は設計セッションで決める（案：form から仮押さえ（pending）を作り、/reserve/confirm/{id} へ移る）
- スタッフの予約入力に date を足した。日付を選んでから枠を選ぶため

### routes/web.php の形

```php
Route::get('/reserve/form', fn (Request $r) => Inertia::render('reserve/form', [
    'slotId' => $r->query('slot'),
]))->name('reserve.form');

Route::get('/member/change/{id}', fn (string $id) => Inertia::render('member/change', [
    'reservationId' => $id,
]))->name('member.change');
```

サーバーが渡すのは URL のパラメーターだけ。残りの props は、モック中は mocks/props.ts の builder が作り、本番ではコントローラーが作る。

### types/pages.ts（画面が受け取るもの）

画面で計算しないように、残席・金額・「押せるか」は builder が計算済みの値で渡す。下は中心になる型だけを示す。他の画面は、作るときに同じ考え方で足す。

```ts
export type SlotView = {
  id: SlotId; date: ISODate; weekday: string; start: string; end: string;
  capacity: number;
  used: { monthly: number; trial: number }; // SeatMeter 用
  remaining: number;   // その画面の kind での残席
  bookable: boolean;   // その画面で押せるか
  blocked: boolean;
};
export type ReserveIndexProps = { days: { date: ISODate; label: string; slots: SlotView[] }[] };
export type ReserveFormProps = { slot: SlotView | null; unitPrice: number; maxParty: number };
export type ReserveConfirmProps = {
  slot: SlotView | null; partySize: number; contact: Contact;
  unitPrice: number; amount: number; cancelPolicy: string[];
};
export type AdminTodayProps = {
  date: ISODate; label: string; prevDate: ISODate; nextDate: ISODate;
  slots: { slot: SlotView; entries: {
    id: string; name: string; kind: ReservationKind; partySize: number;
    payment: 'paid' | 'onsite' | 'monthly'; channel: Channel;
  }[] }[];
};
```

- payment の決め方：月謝は monthly。体験の web は paid（支払済）、体験の phone / dm / staff は onsite（当日払い）
- 不正な slot や id が来たときは、builder は null を返し、画面は「枠が見つかりません」と表示する（クラッシュさせない）

## withMockProps と actions の約束

- mocks/props.ts は `Record<ページ名, (state, serverProps) => props>`。builder は純粋関数にする
- `withMockProps(name, mod)` は `useMockStore()` で状態を購読し、`{...serverProps, ...builder(state, serverProps)}` をページに渡す。画面の上には DemoBar を描く。builder が無いページはそのまま表示する（layout など、ページの static プロパティーも引き継ぐ）
- lib/actions/types.ts：`type Callbacks = { onError?: (errors: Record<string, string>) => void; onFinish?: () => void }`。各 action は `(input, cb?) => void`
- 必要な action：createWebReservation, createStaffReservation, submitMonthlySchedule, changeMonthlySession, setSlotBlocked（B：cancelByToken）
- mock 版は canBook で確かめ、だめなら `onError({ slotId: '満席になりました。別の枠をお選びください' })`。通ればストアを更新し、router.visit(次のURL) で遷移する。モックでは web 予約をいきなり confirmed にする（決済は省略）

## デザインと共通コンポーネント

スマホ幅（最大 420px）だけを前提にする。PC で開いても中央に 420px で表示する。

| 用途 | 値 |
|---|---|
| メイン色 | #2F4A6B |
| 背景 | #F6F4F0 |
| 本文色 | #2B2A28 |
| スタッフ画面のヘッダー | #2B2A28 |
| フォント | Noto Sans JP（見出しは Shippori Mincho も可）。app.blade.php で Google Fonts を読み込む |

色は Tailwind 4 の @theme（resources/css/app.css）に `--color-brand` などとして登録し、クラス名に16進数を直接書かない。

| コンポーネント | props | 使う場所 |
|---|---|---|
| MobileShell | title, variant: 'customer' \| 'staff', back?, children | 全画面。staff ならヘッダーが濃い色 |
| AdminTabs | current: 'today' \| 'slots' \| 'members' | スタッフ画面の下のタブ |
| SeatMeter | monthly, trial, capacity | 予約の選択、月カレンダー、今日の一覧 |
| SlotCard | slot: SlotView, href?, selected? | 枠を選ぶ画面。bookable=false ならグレーで押せない |
| MonthCalendar | month, days, renderDay | 生徒の「来月を選ぶ」、スタッフの月カレンダー |
| StatusBadge | kind: 'paid' \| 'onsite' \| 'monthly' \| 'full' \| 'closed' | 一覧や枠の表示 |

SeatMeter は6マスを左から「月謝→体験→空き」の順に塗る。色だけでなく明度でも区別できるようにする。月謝はメイン色の塗り、体験はその薄い色、空きは白地に枠線。横に「月謝3・体験2／6」を文字でも書く。

DemoBar（mocks/demo-bar.tsx）は画面の一番上に細く出す。載せるものは次の4つ。

- 「確認用の画面です」という表示
- 立場の切替：お客様 → /reserve、生徒さん → /member、スタッフ → /admin
- 今日の日付の切替：11/20、体験日の3日前（11/19）、前日（11/21）、当日（11/22）
- データのリセット

## 実装手順（要約）

6本の feat ブランチを順に作り、それぞれ develop にマージしていく。

1. feat/shell-demobar：全ルート、仮ページ、MobileShell / AdminTabs、demo、DemoBar の見た目
2. feat/mock-foundation：models / pages 型、seed / selectors / store、Vitest（返金表・seed）、withMockProps、actions の入れ物、DemoBar をストアに接続
3. feat/reserve-flow：/reserve → form → confirm → complete、SlotCard / SeatMeter、createWebReservation
4. feat/admin-calendar：MonthCalendar、/admin/slots、setSlotBlocked（予約がある枠はお休みにできない）
5. feat/member：/member、/member/schedule/{month}、/member/change/{id}
6. feat/admin-today：/admin?date、/admin/reservations/new、/admin/members（未提出の絞り込み）
7. main へ上げてリハーサル

外部の端末で見せる場合：dev サーバーを止め、`npm run build` して public/hot が無いことを確かめてから、`cloudflared tunnel --url http://localhost:8080` などで 8080 を公開する。VITE_MOCK はビルド時に埋め込まれる。

### リハーサルの順番

1. / → お客様：土日だけの枠、満席の枠、2名で予約して完了まで
2. スタッフ：その日の一覧に今の予約が出ている → 電話予約を入れて満席にする → お客様画面でも押せない
3. 生徒：マイページ → 来月の4回を選んで提出 → 日程変更で残り0回になる
4. スタッフ：月カレンダーとお休みの切替、生徒一覧の未提出
5. （B があれば）DemoBar で日付を変え、キャンセル画面の返金額が 8,676 → 4,500 → 0 と変わる
6. 最後にリセットしてから meet に入る

meet で森下様に確認したいこと：予約が入っている枠をお休みにしたいときはどうするか（モックでは「できない」にしてある）。

## レビュー観点

ブランチをマージする前に、次の grep を毎回実行する。どれも何も出なければ、境界は守られている。

```bash
# 1. 画面・部品がモックを直接読んでいないか
grep -rnE "mocks/|useMockStore" resources/js/pages resources/js/components
# 2. 画面が自分で送信・遷移していないか（<Link> は OK）
grep -rnE "router\.(post|visit|put|delete)" resources/js/pages resources/js/components
# 3. selectors を許可のない場所で呼んでいないか
grep -rln "selectors" resources/js | grep -vE "mocks/(props|actions|selectors|seed)\.ts"  # seed は「canBook を通す」ため許可
# 4. 「今日」を実時間から取っていないか、乱数が固定されているか
grep -rnE "new Date\(\)|Date\.now|Math\.random" resources/js
# 5. 画面に料金や定員が直書きされていないか
grep -rnE "4500|4,500|0\.036" resources/js/pages resources/js/components
```

目で確かめること：

- URL の slot や id をでたらめにしても白画面にならない（builder が null を返し、画面が「見つかりません」を表示する）
- n が数字でないとき、または 0 以下のときに NaN が表示されない（builder で 1 に丸める）
- seed やストアの形を変えたら SEED_VERSION を上げている
- 画面の中に reservations に対する .filter / .reduce がない（あれば、それは builder の仕事）
- 新しい画面の props 型が types/pages.ts に足されている（ページ内で props 型を定義していない）

## 後で振り返るメモ

- confirm 画面の URL に連絡先が載っている。本番では仮押さえ（pending + expiresAt）を作り、/reserve/confirm/{id} にするか
- モックでは web 予約をいきなり confirmed にしている。Stripe では、pending → webhook で confirmed になるので、その間の席の押さえ方と期限切れの解放を決める
- 予約が入っている枠をお休みにするときの扱い（meet での回答を反映する）
- 月謝の「前月 15〜25 日」「前日まで」「土日 3 名」は仮の値。settings に出すか
- selectors を PHP に移すときは、同じ refund-cases.json でテストする。round() の半端の扱いが JS と合うかもこの表で確かめる
- リンクは Wayfinder の予定だったが、スターターキットに Ziggy が入っていたため Ziggy の route() を使った。BE期に Wayfinder へ移すか決める
- スタッフの予約入力で月謝の生徒も入れられるようにした（1名、同じ日に2回不可、月の回数を超える追加は不可、4回そろったら提出済み扱い）。振替や回数を超える追加レッスンを受けるか、平日の月謝に定員以外の上限があるかは meet で確認する
