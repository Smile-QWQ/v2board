FROM mlocati/php-extension-installer:latest AS php_extension_installer
FROM composer:2 AS composer_binary

FROM php:8.2-cli-alpine AS build

COPY --from=php_extension_installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer_binary /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache git unzip bash \
    && install-php-extensions pcntl redis fileinfo pdo_mysql inotify

WORKDIR /www
COPY . /www

RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && rm -rf /root/.composer/cache

FROM php:8.2-cli-alpine

COPY --from=php_extension_installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pcntl redis fileinfo pdo_mysql inotify \
    && addgroup -S -g 1000 www \
    && adduser -S -G www -u 1000 www

WORKDIR /www
COPY --from=build --chown=www:www /www /www

RUN mkdir -p /www/storage/app/public \
    /www/storage/framework/cache/data \
    /www/storage/framework/sessions \
    /www/storage/framework/views \
    /www/storage/logs \
    /www/bootstrap/cache \
    && ln -sfn /www/storage/app/public /www/public/storage \
    && chmod +x /www/.docker/bin/*.sh

USER www
EXPOSE 7002

ENTRYPOINT ["sh", ".docker/bin/docker-entrypoint.sh"]
CMD ["sh", ".docker/bin/run-app.sh"]
