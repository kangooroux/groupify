#!/bin/sh
set -e

PORT=${PORT:-8080}
export PORT

envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

APP_ENV=prod php bin/console cache:warmup --no-interaction

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
