#!/bin/sh
set -eu

cd /www
exec php artisan horizon
