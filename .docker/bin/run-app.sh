#!/bin/sh
set -eu

cd /www

APP_HTTP_HOST="${APP_HTTP_HOST:-0.0.0.0}"
APP_HTTP_PORT="${APP_HTTP_PORT:-7002}"
NGINX_RUNTIME_DIR="/tmp/nginx"
NGINX_SITE_DIR="${NGINX_RUNTIME_DIR}/http.d"
NGINX_SITE_CONF="${NGINX_SITE_DIR}/default.conf"
PHP_FPM_PID=""
NGINX_PID=""

mkdir -p \
  /run/nginx \
  "${NGINX_SITE_DIR}" \
  "${NGINX_RUNTIME_DIR}/client_temp" \
  "${NGINX_RUNTIME_DIR}/proxy_temp" \
  "${NGINX_RUNTIME_DIR}/fastcgi_temp" \
  "${NGINX_RUNTIME_DIR}/uwsgi_temp" \
  "${NGINX_RUNTIME_DIR}/scgi_temp"

sed \
  -e "s/__APP_HTTP_HOST__/${APP_HTTP_HOST}/g" \
  -e "s/__APP_HTTP_PORT__/${APP_HTTP_PORT}/g" \
  /www/.docker/etc/nginx/http.d/default.conf.template > "${NGINX_SITE_CONF}"

php-fpm --fpm-config /www/.docker/etc/php-fpm.conf &
PHP_FPM_PID="$!"

nginx -c /www/.docker/etc/nginx/nginx.conf -g "daemon off;" &
NGINX_PID="$!"

cleanup() {
  if [ -n "${NGINX_PID}" ]; then
    kill -TERM "${NGINX_PID}" 2>/dev/null || true
  fi
  if [ -n "${PHP_FPM_PID}" ]; then
    kill -TERM "${PHP_FPM_PID}" 2>/dev/null || true
  fi
  wait "${NGINX_PID}" 2>/dev/null || true
  wait "${PHP_FPM_PID}" 2>/dev/null || true
}

trap cleanup INT TERM

status=0
while kill -0 "${NGINX_PID}" 2>/dev/null && kill -0 "${PHP_FPM_PID}" 2>/dev/null; do
  sleep 1
done

if ! kill -0 "${NGINX_PID}" 2>/dev/null; then
  wait "${NGINX_PID}" || status=$?
else
  kill -TERM "${NGINX_PID}" 2>/dev/null || true
  wait "${NGINX_PID}" 2>/dev/null || true
fi

if ! kill -0 "${PHP_FPM_PID}" 2>/dev/null; then
  wait "${PHP_FPM_PID}" || status=$?
else
  kill -TERM "${PHP_FPM_PID}" 2>/dev/null || true
  wait "${PHP_FPM_PID}" 2>/dev/null || true
fi

exit "${status}"
