FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    ca-certificates \
    libpng-dev \
    libjpeg-dev \
    && docker-php-ext-install zip pdo pdo_mysql gd \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Копируем только composer.json и lock для кеширования зависимостей
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Копируем остальные файлы
COPY . /var/www/html

RUN mkdir -p /var/www/html/storage/uploads /var/www/html/storage/output \
    && chmod -R 777 /var/www/html/storage