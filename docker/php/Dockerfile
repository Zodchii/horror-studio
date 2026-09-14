FROM php:8.3-fpm-alpine

RUN apk add --no-cache git unzip $PHPIZE_DEPS icu-dev postgresql-dev \
    && docker-php-ext-install pdo_pgsql intl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache rabbitmq-c-dev \
    && pecl install amqp \
    && docker-php-ext-enable amqp