FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build


FROM php:8.4-cli-bookworm AS application

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        gosu \
        libcurl4-openssl-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        dom \
        mbstring \
        pcntl \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R ug+rwX storage bootstrap/cache

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/docker-entrypoint

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
