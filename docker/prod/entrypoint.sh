#!/bin/sh
# 起動のたびに、そのコンテナの .env から設定・ルート・画面のキャッシュを作る
# （app・queue・scheduler はそれぞれ別のコンテナなので、それぞれで作る。.env を変えたら再起動すれば反映される）
set -e
php artisan optimize --no-interaction >/dev/null
exec "$@"
