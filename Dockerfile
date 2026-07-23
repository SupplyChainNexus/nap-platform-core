FROM php:8.3-cli-alpine

# Install system dependencies & SQLite extension headers
RUN apk add --no-cache \
    sqlite-dev \
    libcurl \
    curl-dev \
    icu-dev \
    oniguruma-dev

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite pdo_mysql opcache curl

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Expose HTTP port for Web Service
EXPOSE 8000

# Default command (overridden by render.yaml for worker)
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]

