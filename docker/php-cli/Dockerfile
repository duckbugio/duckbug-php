FROM php:7.1-cli-alpine3.10

RUN apk add --no-cache \
        unzip \
        bash \
        coreutils \
        git \
        zip \
        libzip-dev \
    && docker-php-ext-install sockets bcmath zip \
    && apk del git

RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --quiet

WORKDIR /app
