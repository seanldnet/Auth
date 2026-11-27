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
chmod 640 .env
chown www-data:www-data .env
mysql_client() {
    MYSQL_PWD="$DB_PASSWORD" mysql --protocol=tcp --connect-timeout=5 \
        --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" "$DB_NAME" "$@"
}

until mysql_client -e "SELECT 1" >/dev/null 2>&1; do
    echo "Waiting for MySQL..."
    sleep 3
done
echo "MySQL connection confirmed."

mysql_client <<'SQL'
CREATE TABLE IF NOT EXISTS auth_schema_migrations (
    version VARCHAR(190) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL

for migration in /var/www/html/database/schema/*.sql; do
    [ -f "$migration" ] || continue
    version=$(basename "$migration")
    applied=$(mysql_client -Nse "SELECT COUNT(*) FROM auth_schema_migrations WHERE version='$version'")
    if [ "$applied" = "1" ]; then
        echo "Migration already applied: $version"
        continue
    fi
    echo "Applying database migration: $version"
    MYSQL_PWD="$DB_PASSWORD" timeout 120s mysql --protocol=tcp --connect-timeout=5 \
        --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" "$DB_NAME" < "$migration"
    mysql_client -e "INSERT INTO auth_schema_migrations(version, applied_at) VALUES ('$version', UTC_TIMESTAMP())"
    echo "Finished database migration: $version"
done

if [ -n "${AUTH_BOOTSTRAP_ADMIN_USERNAME:-}" ] && [ -n "${AUTH_BOOTSTRAP_ADMIN_PASSWORD:-}" ]; then
    php bin/create-admin.php
fi
exec docker-php-entrypoint "$@"
