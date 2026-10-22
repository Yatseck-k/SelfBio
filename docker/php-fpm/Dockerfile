# Образ приложения: php-fpm для сайта и тот же образ для бота входа (другая команда).
# Цели: local (xdebug, php.ini для разработки) и prod (кэш opcache без проверки файлов).
FROM php:8.5.12-fpm-trixie AS base

ARG PHPREDIS_VERSION=6.3.0
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfcgi-bin \
        libfreetype-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libwebp-dev \
        libzip-dev \
        unzip \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" bcmath gd intl pcntl pdo_pgsql pgsql zip \
    && pecl install "redis-${PHPREDIS_VERSION}" \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Composer с getcomposer.org со сверкой контрольной суммы: без лишнего запроса к Docker Hub.
RUN curl -fsSL -o /usr/local/bin/composer https://getcomposer.org/download/latest-2.x/composer.phar \
    && echo "$(curl -fsSL https://getcomposer.org/download/latest-2.x/composer.phar.sha256)  /usr/local/bin/composer" | sha256sum -c - \
    && chmod +x /usr/local/bin/composer

# Код монтируется с хоста, где он принадлежит 1000:1000: www-data получает те же id, чтобы права совпадали.
ARG PUID=1000
ARG PGID=1000
RUN groupmod -o -g "${PGID}" www-data \
    && usermod -o -u "${PUID}" -g www-data -d /var/www www-data \
    && mkdir -p /var/www/html /var/www/.composer \
    && chown -R www-data:www-data /var/www

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY php.ini "$PHP_INI_DIR/conf.d/90-app.ini"
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

ENV HOME=/var/www COMPOSER_HOME=/var/www/.composer
USER www-data
WORKDIR /var/www/html
EXPOSE 9000
CMD ["php-fpm"]

### local ###
FROM base AS local

USER root
RUN apt-get update && apt-get install -y --no-install-recommends git $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* /tmp/pear \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY xdebug.ini "$PHP_INI_DIR/conf.d/99-xdebug.ini"
USER www-data

### prod ###
FROM base AS prod

COPY prod/php.ini "$PHP_INI_DIR/conf.d/95-prod.ini"
COPY prod/www.conf /usr/local/etc/php-fpm.d/www.conf
