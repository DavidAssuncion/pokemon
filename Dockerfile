# syntax=docker/dockerfile:1

###############################################################################
# Etapa 1: dependencias PHP (solo para produzir el vendor/ optimizado)
###############################################################################
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./
COPY --chown=www-data:www-data app/ app/
COPY --chown=www-data:www-data src/ src/
COPY --chown=www-data:www-data bootstrap/ bootstrap/
COPY --chown=www-data:www-data config/ config/
COPY --chown=www-data:www-data database/ database/
COPY --chown=www-data:www-data routes/ routes/
COPY --chown=www-data:www-data resources/ resources/
COPY --chown=www-data:www-data public/ public/
COPY --chown=www-data:www-data artisan composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

###############################################################################
# Etapa 2: build del frontend (Vite + Tailwind)
###############################################################################
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources/ resources/
COPY public/ public/
COPY bootstrap/ bootstrap/

RUN npm run build

###############################################################################
# Etapa 3: imagen final de aplicación (Apache + PHP 8.4 + PostgreSQL)
###############################################################################
FROM php:8.4-apache AS app

# --- Extensiones PHP requeridas (PostgreSQL obligatorio, GD para WebP) ---
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        unzip \
        git \
        curl \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        gd \
        bcmath \
        intl \
        zip \
        mbstring \
        exif \
        pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# OPCache para producción
COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini

# --- Configuración Apache: docroot en public/, AllowOverride y mods ---
RUN a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

# AllowOverride All necesario para los .htaccess (rewrite + cache immutable de iconos)
RUN { \
    echo "<Directory ${APACHE_DOCUMENT_ROOT}>"; \
    echo "    Options Indexes FollowSymLinks"; \
    echo "    AllowOverride All"; \
    echo "    Require all granted"; \
    echo "</Directory>"; \
} > /etc/apache2/conf-available/document-root.conf \
    && a2enconf document-root

WORKDIR /var/www/html

# Copiar código fuente y dependencias (corren como www-data)
COPY --chown=www-data:www-data . /var/www/html
COPY --from=composer --chown=www-data:www-data /app/vendor /var/www/html/vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build /var/www/html/public/build

# Imágenes estáticas (iconos, tipos, piedras, etc.) se incluyen en la imagen
COPY --chown=www-data:www-data public/images /var/www/html/public/images

# Permisos de escritura para storage y bootstrap/cache
RUN mkdir -p storage/framework/{cache,data,sessions,testing,views} bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache

# Entrypoint según rol
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
