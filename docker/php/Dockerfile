FROM php:8.3-fpm
# zip：Composer 用、pdo_mysql：本番の DB（MySQL）用
RUN apt-get update && apt-get install -y git unzip libzip-dev \
 && docker-php-ext-install zip pdo_mysql
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer
WORKDIR /var/www/html
