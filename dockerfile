FROM library/php:8.2-fpm-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pcntl redis fileinfo pdo_mysql inotify \
    && apk --no-cache add shadow supervisor nginx nginx-mod-http-brotli mysql-client git patch redis vim lsof mtr curl bash \
    && addgroup -S -g 1000 www && adduser -S -G www -u 1000 www

WORKDIR /www
COPY .docker /
COPY . /www

RUN set -eux; \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"; \
    EXPECTED_CHECKSUM="$(curl -s https://composer.github.io/installer.sig)"; \
    ACTUAL_CHECKSUM="$(php -r 'echo hash_file(\"sha384\", \"composer-setup.php\");')"; \
    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then \
        echo "ERROR: Invalid installer checksum"; \
        rm composer-setup.php; \
        exit 1; \
    fi; \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer; \
    rm composer-setup.php

RUN composer install --optimize-autoloader --no-cache --no-dev \
    && php artisan storage:link \
    && chown -R www:www /www \
    && chmod -R 777 /www

CMD ["supervisord", "--nodaemon", "-c", "/etc/supervisor/supervisord.conf"]
