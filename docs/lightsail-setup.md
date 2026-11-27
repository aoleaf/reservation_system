# 本番の作り方（AWS Lightsail・サーバー1台）

費用を抑えるため、Lightsail のサーバー1台に全部を載せる（2GB のプラン）。HTTPS はサーバーの上の Caddy が受け、証明書（Let's Encrypt）は自動で取り、自動で更新する。バックアップは Lightsail の自動スナップショットと、DB の毎日のダンプ。

画面のビルドはサーバーでしない。main にマージすると GitHub Actions が本番用のイメージを作り、GitHub のレジストリ（GHCR）に置く。サーバーはそれを取ってくるだけ。

```
お客様・スタッフ ─ HTTPS ─▶ Caddy（443）─▶ nginx（web）─▶ PHP（app）─▶ MySQL（db）
                                              queue（メール・LINE の追加の通知）
                                              scheduler（仮押さえの解除・毎月の枠・毎朝の LINE）
外：Stripe（決済）・Resend（メール）・LINE（お店のグループ）・GHCR（イメージ）
```

- 外に開けるのは 80・443 番（と SSH の 22 番）だけ。nginx・PHP・MySQL は外に出さない
- 費用の目安：Lightsail 2GB のプランで月 12 ドル前後（静的 IP はサーバーに付けていれば無料）。自動スナップショットは容量に応じて少し。ドメイン代は別（料金は AWS の料金表で確かめる）

キーやパスワードはすべて、サーバーの `deploy/.env` にご自身で書く。AI には渡さない。

---

## 1. イメージを作れるようにする（GitHub）

1. このリポジトリの `develop` を `main` にマージすると、Actions の「image」が動いて、次の2つのイメージができる
   - `ghcr.io/aoleaf/reservation_system-app`（PHP）
   - `ghcr.io/aoleaf/reservation_system-web`（nginx）
   - main へは、先に #25・#26（取り消しの PR）を「Revert」して取り消しを戻してから、develop をマージする（入力チェックと LINE 通知が抜けないように）
2. GitHub の「Actions」で image が緑になり、リポジトリの「Packages」に2つのイメージがあることを確かめる
3. **サーバーがイメージを取ってこられるようにする**：リポジトリが非公開なら、イメージも非公開になる。GitHub で「Personal access token（classic）」を `read:packages` だけの権限で作っておく（手順 5 で使う）

## 2. サーバーを作る（Lightsail）

1. Lightsail で「インスタンスの作成」
   - リージョン：東京（ap-northeast-1）
   - プラットフォーム：Linux/Unix、ブループリント：OS のみ → **Ubuntu 24.04 LTS**
   - プラン：**2 GB メモリ**
   - 名前：`tsuchinone`
2. **自動スナップショットを有効にする**（インスタンスの「スナップショット」タブ）。時刻は夜中（例：日本時間 4:00。画面の時刻が UTC なら 19:00）
3. **静的 IP を作ってインスタンスに付ける**（「ネットワーキング」→「静的 IP の作成」）。サーバーを作り直しても IP が変わらないように
4. **ファイアウォール**（インスタンスの「ネットワーキング」タブ）：
   - SSH（22）：できれば自分の IP に絞る。ブラウザの SSH を使うなら「Lightsail のブラウザ SSH を許可」を残す
   - HTTP（80）・HTTPS（443）：すべて許可
   - それ以外は開けない（MySQL の 3306 などは開けない）

## 3. ドメインを向ける

1. 予約に使う名前（例：`yoyaku.（ドメイン）`）の **A レコード**を、手順 2 の静的 IP に向ける（Route 53 か、ドメインを買った会社の DNS で）
2. `dig +short yoyaku.（ドメイン）` で静的 IP が返るまで待つ（数分〜数時間）。返る前に起動すると、Caddy が証明書を取れない

## 4. サーバーの準備（SSH で入って）

```bash
# 日本時間にする（cron とバックアップのファイル名のため。アプリは設定で日本時間）
sudo timedatectl set-timezone Asia/Tokyo

# メモリ 2GB を補うスワップ（2GB）
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# Docker（Docker の公式の手順「Install Docker Engine on Ubuntu」の apt の方法で入れる）
# 入れたら、sudo なしで使えるようにして、入り直す
sudo usermod -aG docker $USER
```

- Ubuntu はセキュリティの更新を自動で入れる（unattended-upgrades）。止めない

## 5. コードの置き場所と、イメージの取り込み

```bash
# 手順のファイル（deploy フォルダ）を取ってくる。GitHub に読み取り専用の「デプロイキー」を登録して clone する
git clone git@github.com:aoleaf/reservation_system.git
cd reservation_system/deploy

# イメージを取ってこられるようにする（手順 1-3 のトークンを、聞かれたときに貼る）
docker login ghcr.io -u （GitHub のユーザー名）
```

## 6. `.env` を書く（ご自身で）

```bash
cp .env.example .env
nano .env
```

- `APP_DOMAIN`・`APP_URL`：手順 3 の名前（`APP_URL` は `https://` から）
- `DB_PASSWORD`・`DB_ROOT_PASSWORD`：長いランダムな値（`openssl rand -base64 24` で作れる）
- `APP_KEY`：次のコマンドで作り、表示された `base64:…` を書く
  ```bash
  docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
  ```
- Resend・Stripe・LINE の値は、手順 9〜11 で書く（空のままでも起動はできる。ただし Stripe が空だと、お客様の支払いのところでエラーになる）

## 7. 起動する

```bash
./deploy.sh
```

イメージを取ってきて、DB を作り（マイグレーション）、起動する。終わったら：

- `https://yoyaku.（ドメイン）/up` が表示される（Caddy が証明書を取るまで1分ほどかかることがある）
- `https://yoyaku.（ドメイン）/` で予約のページが出る
- うまくいかないとき：`docker compose logs caddy`（証明書）・`docker compose logs app`

## 8. 最初のデータ

```bash
# スタッフのアカウント（パスワードを聞かれる）
docker compose exec app php artisan staff:create （お店のメールアドレス） --name=森下

# 枠（今月から先。以降は毎月1日に自動で翌々月の分ができる）
docker compose exec app php artisan slots:generate 2026-12
docker compose exec app php artisan slots:generate 2027-01

# お店の電話番号（キャンセルの案内・メールに出る。画面が無いので、ここで書く）
docker compose exec app php artisan settings:shop-phone （お店の電話番号）

# 生徒さんの CSV（手元から scp でサーバーに送ってから）
docker compose cp ~/生徒.csv app:/tmp/members.csv
docker compose exec app php artisan import:members /tmp/members.csv --dry-run
docker compose exec app php artisan import:members /tmp/members.csv
docker compose cp ~/12月の予約.csv app:/tmp/monthly.csv
docker compose exec app php artisan import:monthly /tmp/monthly.csv --dry-run
docker compose exec app php artisan import:monthly /tmp/monthly.csv
```

- お休みの日は、スタッフの管理画面のカレンダーで設定する

## 9. メール（Resend）

1. Resend で送信元のドメイン（例：`mail.（ドメイン）`）を追加し、表示された DNS のレコード（SPF・DKIM）を DNS に登録して「Verify」
2. API キーを作る（Sending access・そのドメインだけ）
3. `.env`：`MAIL_MAILER=resend`・`RESEND_KEY`・`MAIL_FROM_ADDRESS="yoyaku@mail.（ドメイン）"`
4. `docker compose up -d`（設定は起動のたびに読み直す）
5. 自分のメールアドレスで生徒を1人登録し、生徒のログインのメールが届くか確かめる

## 10. 決済（Stripe）

1. まず**テストモード**で：
   - webhook を登録：URL `https://yoyaku.（ドメイン）/stripe/webhook`、イベント `checkout.session.completed`・`checkout.session.async_payment_succeeded`・`checkout.session.expired`
   - `.env`：`STRIPE_SECRET=sk_test_…`、`STRIPE_WEBHOOK_SECRET=whsec_…`（その webhook の署名シークレット）
   - `docker compose up -d`
   - テスト用のカード（4242 4242 4242 4242）で、予約 → 支払い → 予約確認メール → メールのリンクからキャンセル → 返金 を通す
2. 公開するときに、本番モードで webhook を登録し直し、`sk_live_…` と本番の `whsec_…` に入れ替えて `docker compose up -d`

## 11. LINE 通知

`docs/release.md` の「LINE 通知」の手順で、公式アカウント・Messaging API・トークン・webhook（`https://yoyaku.（ドメイン）/line/webhook`）を設定し、`.env` に `LINE_CHANNEL_SECRET`・`LINE_CHANNEL_ACCESS_TOKEN` を書いて `docker compose up -d`。お店の LINE グループに公式アカウントを招待し、管理画面の「設定」で送り先に決めて「テスト送信」。

## 12. スケジューラーのタイムゾーンを確かめる

```bash
docker compose exec scheduler php artisan schedule:list
```

- `0 8 * * *  php artisan line:daily-digest` の「次」が、**日本時間の翌朝 8:00** になっていること
- `0 3 1 * *  php artisan slots:generate` が、来月1日の 3:00（日本時間）になっていること
- `* * * * *  php artisan reservations:expire-holds` が1分ごと
- アプリのタイムゾーン：`docker compose exec app php artisan about --only=environment` の Timezone が Asia/Tokyo
- アプリはスケジューラーも含めて Asia/Tokyo で動く（config/app.php の timezone・schedule_timezone）。コンテナの OS の時計が UTC でも影響しない

## 13. バックアップ

- **Lightsail の自動スナップショット**（手順 2-2）：ディスクまるごと、毎日。サーバーごと戻すとき
- **DB の毎日のダンプ**：DB だけを手早く戻すとき。cron に登録する
  ```bash
  crontab -e
  # 毎日 3:30 に DB をダンプ（14 日分を残す）
  30 3 * * * /home/ubuntu/reservation_system/deploy/backup.sh >> /home/ubuntu/backup.log 2>&1
  ```
- DB をダンプから戻すとき（**今の DB は消える**。先にスナップショットかダンプを取ってから）：
  ```bash
  gunzip -c backups/tsuchinone-（日付）.sql.gz | docker compose exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
  ```
- ときどき、ダンプを手元にも取っておく（サーバーごと失ったときのため）

## 14. 公開前の確認

- `docs/release.md` の「6. 公開の前に確かめること」を、本番の URL で1つずつ
- **お客様の IP を偽れないこと**：手元から、生徒のログインのリンクを、毎回ちがう偽の `X-Forwarded-For` を付けて 11 回送る。同じ IP からは 10 分に 10 回までなので、**11 回目が `429`** になれば、偽の値は使われていない（Caddy がお客様の送ってきた値を捨てて付け直している）。CSRF を通すため、先にページを開いてクッキーとトークンを受け取る
  ```bash
  D=https://yoyaku.（ドメイン）; J=/tmp/jar.txt; rm -f $J
  curl -s -c $J -b $J -o /dev/null $D/member/login
  T=$(grep XSRF-TOKEN $J | awk '{print $7}' | python3 -c "import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))")
  for i in $(seq 1 11); do curl -s -c $J -b $J -o /dev/null -w "%{http_code} " -X POST $D/member/login \
    -H "X-XSRF-TOKEN: $T" -H "X-Inertia: true" -H "Content-Type: application/json" -H "X-Forwarded-For: 10.0.0.$i" -d "{\"email\":\"nobody$i@example.com\"}"; done; echo
  # 302 が 10 個のあとに 429 が出れば OK（手元の本番と同じ形で確認済み）
  ```
  （登録されていないメールアドレスで送るので、メールは届かない）

## 15. 更新と、前の版に戻すとき

- **更新**：develop を main にマージ → Actions の image が緑になったら、サーバーで `./deploy.sh`
- **前の版に戻す**：GitHub で戻したいコミットの SHA を確かめて `IMAGE_TAG=（SHA） ./deploy.sh`。戻したら `.env` の `IMAGE_TAG` も合わせるか、次の更新で latest に戻す
  - DB のマイグレーションは戻らない。DB の形を変えた版から戻すときは、先にダンプから戻すか相談する

## 16. 困ったとき

| 見たいもの | コマンド |
|---|---|
| 動いているか | `docker compose ps` |
| アプリのログ | `docker compose exec app tail -n 100 storage/logs/laravel-$(date +%F).log` |
| 証明書・アクセス | `docker compose logs caddy --tail 100` |
| メールや LINE の送信 | `docker compose logs queue --tail 100` |
| 失敗したキューの仕事 | `docker compose exec app php artisan queue:failed` |
| 設定を読み直す | `docker compose up -d`（起動のたびに .env から作り直す） |
