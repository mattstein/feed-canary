FROM serversideup/php:8.4-cli

ENV PHP_OPCACHE_ENABLE=1

USER root

# For Composer
RUN apt-get update
RUN apt-get install -y zip unzip git

RUN curl -sL https://deb.nodesource.com/setup_22.x | bash -
RUN apt-get install -y nodejs

COPY --chown=www-data:www-data . /var/www/html

USER www-data

RUN composer install --prefer-dist --no-interaction --optimize-autoloader --no-dev

RUN npm install
RUN npm run build
