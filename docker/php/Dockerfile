FROM php:8.3-fpm
RUN apt-get update && apt-get install -y git unzip libzip-dev \
 && docker-php-ext-install zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer
WORKDIR /var/www/html