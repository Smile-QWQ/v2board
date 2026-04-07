#!/bin/sh
set -eu

cd /www
exec php artisan v2board:install
