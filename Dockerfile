FROM php:8.4-cli-alpine

RUN apk add --no-linux-headers --no-cache $PHPIZE_DEPS sqlite-dev icu-dev zip libzip-dev \
    && docker-php-ext-install pdo pdo_sqlite intl zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-autoloader

COPY . .
RUN composer dump-autoload --optimize

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
