FROM php:8.3-cli-alpine

RUN apk add --no-cache \
    sqlite-dev \
    libcurl \
    curl-dev \
    icu-dev \
    oniguruma-dev

RUN docker-php-ext-install pdo pdo_sqlite pdo_mysql opcache curl

WORKDIR /app

COPY . .

RUN chmod +x start.sh

EXPOSE 8000

CMD ["/bin/sh", "start.sh"]

