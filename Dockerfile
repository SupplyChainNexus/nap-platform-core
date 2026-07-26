# --- Stage 1: Build & Composer Dependencies ---
FROM php:8.3-cli-alpine AS builder

WORKDIR /app

# Install system dependencies & SQLite extension
RUN apk add --no-cache sqlite-dev libpng-dev libzip-dev \
    && docker-php-ext-install pdo pdo_sqlite zip

# Copy Composer binary from official image
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Copy application manifest & source
COPY composer.json composer.lock ./
COPY src/ ./src/

# Install production dependencies & dump optimized classmap
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# --- Stage 2: Runtime Environment ---
FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

# Install runtime dependencies & extensions
RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Copy built vendor and application source from builder stage
COPY --from=builder /app/vendor ./vendor
COPY src/ ./src/
COPY public/ ./public/
COPY bin/ ./bin/
COPY composer.json ./

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
