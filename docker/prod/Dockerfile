# 本番用のイメージ（GitHub Actions で作り、GHCR に置く）。サーバーではビルドしない。
#   target app：PHP（php-fpm）。queue・scheduler も同じイメージで動かす
#   target web：nginx（画面の静的なファイル）

# ---- PHP の依存（本番用だけ） ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --optimize

# ---- 画面のビルド ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# 型の参照（ziggy-js）のために、PHP の依存から Ziggy だけ持ってくる
COPY --from=vendor /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
RUN npm run build

# ---- PHP（php-fpm） ----
FROM php:8.3-fpm AS app
RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev \
 && docker-php-ext-install pdo_mysql zip opcache \
 && rm -rf /var/lib/apt/lists/*
COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/prod/entrypoint.sh /usr/local/bin/app-entrypoint
# tinker などが設定を書く場所（www-data のホームは書き込めないため）
ENV XDG_CONFIG_HOME=/tmp/.config
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
RUN rm -f public/hot \
 && mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/public bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod +x /usr/local/bin/app-entrypoint \
 && su -s /bin/sh www-data -c "php artisan package:discover --ansi"
USER www-data
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# ---- nginx（静的なファイルを返し、PHP は app に渡す） ----
FROM nginx:1.27-alpine AS web
COPY docker/prod/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
