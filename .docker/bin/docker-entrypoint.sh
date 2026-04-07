#!/bin/sh
set -eu

cd /www

PERSIST_ROOT="${V2BOARD_PERSIST_PATH:-/data}"
PERSIST_CONFIG_DIR="${PERSIST_ROOT}/config"
PERSIST_THEME_DIR="${PERSIST_CONFIG_DIR}/theme"
PERSIST_STORAGE_PUBLIC_DIR="${PERSIST_ROOT}/storage/app/public"
PERSIST_CUSTOM_RULES_DIR="${PERSIST_ROOT}/custom/rules"
PERSIST_CUSTOM_ADMIN_DIR="${PERSIST_ROOT}/custom/admin"
PERSIST_CUSTOM_PUBLIC_DIR="${PERSIST_ROOT}/custom/public"
PERSIST_CUSTOM_THEME_DIR="${PERSIST_ROOT}/custom/theme"

ensure_dir() {
  mkdir -p "$1"
}

ensure_symlink() {
  target="$1"
  link="$2"

  ensure_dir "$(dirname "$target")"
  ensure_dir "$(dirname "$link")"

  if [ -L "$link" ]; then
    current_target="$(readlink "$link" || true)"
    if [ "$current_target" = "$target" ]; then
      return
    fi
    rm -f "$link"
  elif [ -e "$link" ]; then
    rm -rf "$link"
  fi

  ln -s "$target" "$link"
}

ensure_optional_symlink() {
  target="$1"
  link="$2"

  ensure_dir "$(dirname "$target")"
  ensure_dir "$(dirname "$link")"

  if [ -L "$link" ]; then
    current_target="$(readlink "$link" || true)"
    if [ "$current_target" = "$target" ]; then
      return
    fi
    rm -f "$link"
  elif [ -e "$link" ]; then
    return
  fi

  ln -s "$target" "$link"
}

is_app_command() {
  [ "${1:-}" = "sh" ] && [ "${2:-}" = ".docker/bin/run-app.sh" ]
}

ensure_dir "$PERSIST_CONFIG_DIR"
ensure_dir "$PERSIST_THEME_DIR"
ensure_dir "$PERSIST_STORAGE_PUBLIC_DIR"
ensure_dir "$PERSIST_CUSTOM_RULES_DIR"
ensure_dir "$PERSIST_CUSTOM_ADMIN_DIR"
ensure_dir "$PERSIST_CUSTOM_PUBLIC_DIR"
ensure_dir "$PERSIST_CUSTOM_THEME_DIR"

ensure_dir /www/storage/framework/cache/data
ensure_dir /www/storage/framework/sessions
ensure_dir /www/storage/framework/views
ensure_dir /www/storage/logs
ensure_dir /www/storage/workerman
ensure_dir /www/bootstrap/cache
ensure_dir /run/nginx
ensure_dir /tmp/nginx/http.d
ensure_dir /tmp/nginx/client_temp
ensure_dir /tmp/nginx/proxy_temp
ensure_dir /tmp/nginx/fastcgi_temp
ensure_dir /tmp/nginx/uwsgi_temp
ensure_dir /tmp/nginx/scgi_temp

if [ ! -f "${PERSIST_CONFIG_DIR}/v2board.php" ]; then
  printf '%s\n' '<?php' '' 'return [];' > "${PERSIST_CONFIG_DIR}/v2board.php"
fi

ensure_symlink "${PERSIST_CONFIG_DIR}/v2board.php" /www/config/v2board.php
ensure_symlink "${PERSIST_THEME_DIR}" /www/config/theme
ensure_symlink "${PERSIST_STORAGE_PUBLIC_DIR}" /www/storage/app/public
ensure_symlink /www/storage/app/public /www/public/storage

ensure_optional_symlink "${PERSIST_CUSTOM_ADMIN_DIR}/custom.css" /www/public/assets/admin/custom.css
ensure_optional_symlink "${PERSIST_CUSTOM_PUBLIC_DIR}/favicon.ico" /www/public/favicon.ico

for custom_rule in \
  custom.app.clash.yaml \
  custom.clash.yaml \
  custom.sing-box.json \
  custom.sing-box.old.json \
  custom.stash.yaml \
  custom.surge.conf \
  custom.surfboard.conf
do
  ensure_optional_symlink "${PERSIST_CUSTOM_RULES_DIR}/${custom_rule}" "/www/resources/rules/${custom_rule}"
done

for theme_dir in /www/public/theme/*; do
  if [ ! -d "$theme_dir" ]; then
    continue
  fi

  theme_name="$(basename "$theme_dir")"
  ensure_dir "${PERSIST_CUSTOM_THEME_DIR}/${theme_name}/assets"
  ensure_optional_symlink "${PERSIST_CUSTOM_THEME_DIR}/${theme_name}/assets/custom.css" "${theme_dir}/assets/custom.css"
  ensure_optional_symlink "${PERSIST_CUSTOM_THEME_DIR}/${theme_name}/assets/custom.js" "${theme_dir}/assets/custom.js"
done

chown -R www:www "$PERSIST_ROOT" /www/storage /www/bootstrap/cache /run/nginx /tmp/nginx

su-exec www php artisan v2board:docker-bootstrap --no-interaction

if is_app_command "${1:-}" "${2:-}"; then
  exec "$@"
fi

exec su-exec www "$@"
