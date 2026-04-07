#!/bin/sh
set -eu

cd /www
exec php webman.php start

