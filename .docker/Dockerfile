# -----------------------------------------------------------------------------
# STAGE 1: Base System (Hanya Install Extension)
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:php8.3-bookworm AS base_system

# Gunakan script installer agar layer lebih kecil dan bersih
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    # Install PHP Extensions (Hapus supervisor dari list ini)
    install-php-extensions \
    pdo_mysql \
    pdo_pgsql \
    xml \
    dom \
    mbstring \
    zip \
    exif \
    pcntl \
    bcmath \
    gd \
    redis \
    intl && \
    # Install Supervisor lewat apt
    apt-get update && \
    apt-get install -y supervisor && \
    # Bersihkan cache apt agar image kecil
    rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------------------------
# STAGE 2: Base Config (Shared Setup)
# -----------------------------------------------------------------------------
FROM base_system AS base_config

ENV TZ=Asia/Jakarta
WORKDIR /app

# Setup User laravel agar tidak ada masalah permission di volume mount
ARG UID=1000
ARG GID=1000
RUN groupadd -g $GID laravel && \
    useradd -u $UID -g laravel -s /bin/bash -m laravel && \
    mkdir -p /data /config /var/log/supervisor && \
    chown -R laravel:laravel /app /data /config /var/log/supervisor

# Copy script entrypoint jika Anda menggunakan Opsi 2
# COPY --chown=laravel:laravel entrypoint.sh /usr/local/bin/entrypoint.sh

# -----------------------------------------------------------------------------
# STAGE 3: Development (Localhost)
# -----------------------------------------------------------------------------
FROM base_config AS dev

# Config PHP Development
# RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY .docker/php/php.ini $PHP_INI_DIR/conf.d/prod.ini

# Ambil binary Composer dari official image untuk kebutuhan development
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer 

USER laravel

# Server Name untuk Caddy (Local)
ENV SERVER_NAME=":8080"

# (Dev selesai, docker-compose akan mount code via volume)

# -----------------------------------------------------------------------------
# STAGE 4: Build Backend (Composer)
# -----------------------------------------------------------------------------
FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN if [ -f composer.lock ]; then \
        composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs; \
    else \
        composer update --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs; \
    fi
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative
RUN rm -rf tests node_modules

# -----------------------------------------------------------------------------
# STAGE 4: Build Frontend (Node.js/Vite)
# -----------------------------------------------------------------------------
# Gunakan node:20-bookworm (Debian) sesuai request Anda sebelumnya
FROM node:20-bookworm AS frontend

WORKDIR /app

# 1. Install Dependencies Node.js
COPY package.json package-lock.json* ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi

# 2. Copy Source Code Utama
COPY . .

# 3. [PENTING] Copy folder 'vendor' dari stage PHP sebelumnya
#    Ini diperlukan karena Vite/Ziggy butuh akses ke file di vendor/tightenco/ziggy
COPY --from=vendor /app/vendor /app/vendor

# 4. Jalankan Build
RUN npm run build

# -----------------------------------------------------------------------------
# STAGE 5: Production (Deploy ke VPS)
# -----------------------------------------------------------------------------
FROM base_config AS prod

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV SERVER_NAME=":80"

# RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
# COPY .docker/php/php.ini $PHP_INI_DIR/conf.d/php.ini
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/php.ini

# 1. Copy Vendor PHP (Backend)
COPY --from=vendor --chown=laravel:laravel /app/vendor /app/vendor
COPY --from=vendor --chown=laravel:laravel /app/composer.json /app/composer.lock* /app/
COPY --chown=laravel:laravel . .

# 2. Copy Hasil Build Frontend (PENTING: Ini solusi error Anda)
# Kita ambil folder public/build dari stage 'frontend'
COPY --from=frontend --chown=laravel:laravel /app/public/build /app/public/build

# Copy konfigurasi Supervisor ke lokasi sistem
COPY .docker/supervisor/worker.conf /etc/supervisor/conf.d/worker.conf
COPY .docker/supervisor/reverb.conf* /etc/supervisor/conf.d/

# Bersihkan cache & Setup Permission
RUN rm -rf bootstrap/cache/*.php && \
    mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache /data /config && \
    chmod -R 775 storage bootstrap/cache /data /config && \
    chown -R laravel:laravel storage bootstrap/cache /data /config

USER laravel