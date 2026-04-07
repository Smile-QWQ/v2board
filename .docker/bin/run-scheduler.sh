#!/bin/sh
set -eu

cd /www

interval="${SCHEDULE_INTERVAL:-60}"

echo "[scheduler] started with interval ${interval}s"

while true; do
  echo "[scheduler] $(date '+%Y-%m-%d %H:%M:%S') php artisan schedule:run --no-interaction"
  if ! php artisan schedule:run --no-interaction; then
    echo "[scheduler] command failed" >&2
  fi
  sleep "${interval}"
done
