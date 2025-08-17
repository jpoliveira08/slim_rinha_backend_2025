FROM php:8.4-fpm

ARG UID=1000
ARG GID=1000

RUN apt update \
    && apt install -y zlib1g-dev g++ git libicu-dev zip libzip-dev libz-dev zip curl libpq-dev libmemcached-dev \
    && apt-get install -y --no-install-recommends \
            curl \
            libmemcached-dev \
            libz-dev \
            libpq-dev \
            libjpeg-dev \
            libpng-dev \
            libfreetype6-dev \
            libssl-dev \
            libwebp-dev \
            libxpm-dev \
            libmcrypt-dev \
            libonig-dev; \
    rm -rf /var/lib/apt/lists/*

# Add this line to install PostgreSQL PDO extension
RUN docker-php-ext-install pdo pdo_pgsql

RUN groupadd -g $GID appuser && \
    useradd -u $UID -g appuser -m appuser

WORKDIR /var/www/html

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

USER appuser