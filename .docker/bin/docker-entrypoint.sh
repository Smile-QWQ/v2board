#!/bin/sh
set -eu

cd /www

mkdir -p \
  /www/storage/app/public \
  /www/storage/framework/cache/data \
  /www/storage/framework/sessions \
  /www/storage/framework/views \
  /www/storage/logs \
  /www/storage/workerman \
  /www/bootstrap/cache

if [ ! -L /www/public/storage ]; then
  ln -sfn /www/storage/app/public /www/public/storage
fi

exec "$@"

