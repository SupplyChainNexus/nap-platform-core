# Stage 1: Build stage
FROM php:8.3-cli-alpine AS builder

WORKDIR /app

COPY composer.json composer.lock ./
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Stage 2: Runtime stage
FROM php:8.3-fpm-alpine

# Install SQLite dependencies and extension
RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo_sqlite

WORKDIR /app

COPY --from=builder /app/vendor ./vendor
COPY . .

# Ensure startup script is executable
RUN chmod +x bin/start-with-worker.sh

EXPOSE 8080

ENTRYPOINT ["/bin/sh", "bin/start-with-worker.sh"]
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
