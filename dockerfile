FROM library/php:8.2-fpm-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pcntl redis fileinfo pdo_mysql inotify \
    && apk --no-cache add shadow supervisor nginx nginx-mod-http-brotli mysql-client git patch redis vim lsof mtr curl bash \
    && addgroup -S -g 1000 www && adduser -S -G www -u 1000 www

WORKDIR /www
COPY .docker /
COPY . /www

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && HASH=$(curl -sS https://composer.github.io/installer.sig) \
    && php -r "if (hash_file('sha384', 'composer-setup.php') === getenv('HASH')) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); exit(1); } echo PHP_EOL;" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');" \
    && mv composer.phar /usr/local/bin/composer

RUN composer install --optimize-autoloader --no-cache --no-dev \
    && php artisan storage:link \
    && chown -R www:www /www \
    && chmod -R 777 /www

CMD ["supervisord", "--nodaemon", "-c", "/etc/supervisor/supervisord.conf"]
