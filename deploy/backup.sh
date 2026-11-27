#!/usr/bin/env bash
# DB を毎日ダンプする（cron で 1 日 1 回）。14 日より古いものは消す。
# Lightsail のスナップショット（ディスクまるごと）に加えて、DB だけを手早く戻せるようにするため。
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p backups
file="backups/tsuchinone-$(date +%Y%m%d-%H%M).sql.gz"
docker compose exec -T db sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' | gzip > "$file"
find backups -name 'tsuchinone-*.sql.gz' -mtime +14 -delete
echo "保存しました：$file"
