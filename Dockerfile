FROM php:8.3-fpm-alpine

# Install ekstensi wajib Laravel & PhpSpreadsheet (zip, gd, pdo_mysql, mbstring, xml)
RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip xml

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

RUN composer install --no-dev --optimize-autoloader \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
