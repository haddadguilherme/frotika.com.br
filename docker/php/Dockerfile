# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — build dos assets front-end (Vite + Tailwind v4)
# ---------------------------------------------------------------------------
FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install --ignore-scripts

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2 — dependências PHP via Composer
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock* ./
# Instala sem rodar scripts (artisan ainda não tem o código-fonte completo aqui)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ---------------------------------------------------------------------------
# Stage 3 — runtime PHP 8.3-FPM
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS app

# Dependências de sistema para as extensões PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
        gd \
        exif \
        pcntl \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer (binário) para uso interno em builds/CI
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configurações PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-frotika.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

WORKDIR /var/www/html

# Código-fonte da aplicação
COPY . .

# Vendor e assets vindos dos stages anteriores
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Regenera o autoload otimizado com o código completo já presente
RUN composer dump-autoload --optimize --no-dev --no-interaction

# Permissões para o usuário do PHP-FPM
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
