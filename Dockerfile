FROM php:8.3-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    sqlite-dev \
    libcurl \
    curl-dev \
    icu-dev \
    oniguruma-dev \
    git \
    unzip

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite pdo_mysql opcache curl

# Copy Composer binary from official Composer image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application files
COPY . .

# Install production dependencies and generate autoloader
RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN chmod +x start.sh

EXPOSE 8000

CMD ["/bin/sh", "start.sh"]

