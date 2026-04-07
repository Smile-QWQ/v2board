#!/bin/sh
set -eu

cd /www
exec php -c cli-php.ini webman.php start
