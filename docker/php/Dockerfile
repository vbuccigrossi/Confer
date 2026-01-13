FROM php:8.3-fpm-alpine

# System dependencies
RUN apk add --no-cache bash git curl libpng libpng-dev libjpeg-turbo-dev freetype-dev \
    libzip-dev icu-dev oniguruma-dev libpq-dev shadow autoconf g++ make

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) gd pdo pdo_pgsql mbstring zip bcmath intl pcntl exif opcache

# Redis extension
RUN pecl install redis \
 && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP config
COPY conf.d/php.ini /usr/local/etc/php/conf.d/php.ini

WORKDIR /var/www/html

EXPOSE 9000