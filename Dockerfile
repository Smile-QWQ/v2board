FROM mlocati/php-extension-installer:latest AS php_extension_installer
FROM composer:2 AS composer_binary

FROM php:8.2-fpm-alpine AS build

COPY --from=php_extension_installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer_binary /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache git unzip bash \
    && install-php-extensions pcntl redis fileinfo pdo_mysql inotify

WORKDIR /www
COPY . /www

RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && rm -rf /root/.composer/cache

FROM php:8.2-fpm-alpine

COPY --from=php_extension_installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache su-exec nginx \
    && install-php-extensions pcntl redis fileinfo pdo_mysql inotify \
    && addgroup -S -g 1000 www \
    && adduser -S -G www -u 1000 www

WORKDIR /www
COPY --from=build --chown=www:www /www /www

RUN mkdir -p /www/storage/app/public \
    /www/storage/framework/cache/data \
    /www/storage/framework/sessions \
    /www/storage/framework/views \
    /www/storage/logs \
    /www/storage/workerman \
    /www/bootstrap/cache \
    /data/config/theme \
    /data/storage/app/public \
    /data/custom/rules \
    /data/custom/admin \
    /data/custom/public \
    /data/custom/theme \
    /run/nginx \
    /tmp/nginx/http.d \
    /tmp/nginx/client_temp \
    /tmp/nginx/proxy_temp \
    /tmp/nginx/fastcgi_temp \
    /tmp/nginx/uwsgi_temp \
    /tmp/nginx/scgi_temp \
    && ln -sfn /www/storage/app/public /www/public/storage \
    && chown -R www:www /www /data /run/nginx /tmp/nginx \
    && chmod +x /www/.docker/bin/*.sh

EXPOSE 7002

ENTRYPOINT ["sh", ".docker/bin/docker-entrypoint.sh"]
CMD ["sh", ".docker/bin/run-app.sh"]
