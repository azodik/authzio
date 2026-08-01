#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY is required" >&2
  exit 1
fi

# Nightwatch agent is off by default; enable only with token + flag.
if [ "${NIGHTWATCH_ENABLED:-false}" = "true" ] && [ -n "${NIGHTWATCH_TOKEN:-}" ]; then
  sed -i \
    -e '/\[program:nightwatch\]/,/stderr_logfile_maxbytes=0/{s/^autostart=.*/autostart=true/; s/^autorestart=.*/autorestart=true/;}' \
    /etc/supervisor/conf.d/authzio.conf
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Optional first-boot demo data (local Compose sets AUTHZIO_SEED=true).
if [ "${AUTHZIO_SEED:-false}" = "true" ]; then
  php artisan db:seed --force
fi

exec "$@"
