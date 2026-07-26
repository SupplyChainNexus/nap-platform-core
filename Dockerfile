FROM php:8.3-cli-alpine

# Install SQLite dependencies and extensions
RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo_sqlite

WORKDIR /app

# Copy application files
COPY . .

# Ensure startup script is executable
RUN chmod +x bin/start-with-worker.sh

EXPOSE 8080

# Run worker script in background and launch built-in PHP web server
ENTRYPOINT ["/bin/sh", "bin/start-with-worker.sh"]
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
