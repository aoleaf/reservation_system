#!/usr/bin/env bash
# 本番を新しい版にする（サーバーの deploy フォルダで実行）。
#   ./deploy.sh                 … いちばん新しい版（latest）
#   IMAGE_TAG=<コミット> ./deploy.sh  … その版にする（前の版に戻すとき）
set -euo pipefail
cd "$(dirname "$0")"

echo "== 手順のファイル（compose・Caddyfile）を新しくする"
git pull --ff-only

echo "== イメージを取ってくる"
docker compose pull

echo "== DB を新しい版に合わせる（マイグレーション）"
docker compose up -d db
docker compose run --rm app php artisan migrate --force

echo "== 新しい版で起動し直す"
docker compose up -d --remove-orphans

echo "== 古いイメージを片付ける"
docker image prune -f >/dev/null

docker compose ps
echo "== できました。https://$(grep '^APP_DOMAIN=' .env | cut -d= -f2)/up で確かめてください"
