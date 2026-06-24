FROM serversideup/php:8.5-frankenphp

ENV PHP_OPCACHE_ENABLE=1
ENV AUTORUN_ENABLED=true

USER root

RUN install-php-extensions bcmath gd imagick intl pcntl pdo_pgsql redis

RUN apt-get update && \
    apt-get install -y zip unzip git && \
    curl -sL https://deb.nodesource.com/setup_22.x | bash - && \
    apt-get install -y nodejs && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock package*.json ./

USER www-data

RUN --mount=type=cache,target=/root/.composer \
    composer install --prefer-dist --no-interaction --optimize-autoloader --no-dev

RUN --mount=type=cache,target=/root/.npm \
    npm ci --prefer-offline

COPY --chown=www-data:www-data . /var/www/html

RUN npm run build

ENV AUTORUN_LARAVEL_MIGRATION=true
