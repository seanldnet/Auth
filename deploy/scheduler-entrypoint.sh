#!/bin/sh
set -eu
cd /var/www/html

: "${APP_KEY:?APP_KEY is required}"
: "${DB_HOST:=db}"
: "${DB_NAME:=auth}"
: "${DB_USER:=auth}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"
: "${DB_PORT:=3306}"
: "${REDIS_HOST:=redis}"
: "${REDIS_PORT:=6379}"

cat > .env <<EOF
APP_DEBUG = false
APP_TRACE = false
APP_KEY = ${APP_KEY}
[APP]
DEFAULT_TIMEZONE = Asia/Shanghai
[DATABASE]
TYPE = mysql
HOSTNAME = ${DB_HOST}
DATABASE = ${DB_NAME}
USERNAME = ${DB_USER}
PASSWORD = ${DB_PASSWORD}
HOSTPORT = ${DB_PORT}
CHARSET = utf8mb4
PREFIX = auth_
[REDIS]
HOST = ${REDIS_HOST}
PORT = ${REDIS_PORT}
PASSWORD =
SELECT = 0
EOF

until php -r 'exit(@file_get_contents("http://backend/api/health") === false ? 1 : 0);' >/dev/null 2>&1; do
    echo "Waiting for backend to finish initialization..."
    sleep 5
done

while true; do
    php think auth:cleanup-api-key-nonces || true
    if [ "$(date +%M)" = "00" ]; then
        php think auth:notify-expiring-licenses || true
    fi
    sleep 60
done
