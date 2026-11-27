#!/bin/sh
set -eu
: "${DATABASE_HOSTNAME:=db}"
: "${DATABASE_PORT:=3306}"
: "${DATABASE_DATABASE:=auth}"
: "${DATABASE_USERNAME:=auth}"
: "${DATABASE_PASSWORD:?DATABASE_PASSWORD is required}"
mysql_cmd() {
  MYSQL_PWD="$DATABASE_PASSWORD" mysql --protocol=tcp --host="$DATABASE_HOSTNAME" --port="$DATABASE_PORT" --user="$DATABASE_USERNAME" --database="$DATABASE_DATABASE" --default-character-set=utf8mb4 "$@"
}
echo "Waiting for MySQL at $DATABASE_HOSTNAME:$DATABASE_PORT ..."
attempt=0
until mysql_cmd --execute='SELECT 1' >/dev/null 2>&1; do
  attempt=$((attempt + 1))
  if [ "$attempt" -ge 90 ]; then echo "MySQL did not become ready within 180 seconds." >&2; exit 1; fi
  sleep 2
done
mysql_cmd --execute="CREATE TABLE IF NOT EXISTS auth_schema_migrations (migration VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
for migration in /app/database/schema/*.sql; do
  [ -f "$migration" ] || continue
  name="$(basename "$migration")"
  applied="$(mysql_cmd --batch --skip-column-names --execute="SELECT COUNT(*) FROM auth_schema_migrations WHERE migration='$name'")"
  if [ "$applied" = "1" ]; then echo "Already applied: $name"; continue; fi
  echo "Applying migration: $name"
  mysql_cmd < "$migration"
  mysql_cmd --execute="INSERT INTO auth_schema_migrations (migration) VALUES ('$name')"
done
echo "Database migrations complete."
