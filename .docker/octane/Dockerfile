FROM dunglas/frankenphp:1-php8.4

# For Composer
RUN apt-get update
RUN apt-get install -y zip unzip git

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN install-php-extensions bcmath gd imagick intl opcache pcntl pdo_pgsql redis zip

RUN curl -sL https://deb.nodesource.com/setup_22.x | bash -
RUN apt-get install -y nodejs

COPY . /app

RUN composer install --prefer-dist --no-interaction --optimize-autoloader --no-dev

RUN npm install
RUN npm run build

ENTRYPOINT ["php", "artisan", "octane:frankenphp"]
