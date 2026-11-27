#!/bin/sh
set -eu
cd /app
/usr/local/bin/auth-migrate
php /app/bin/create-admin.php
exec php -S 0.0.0.0:8000 -t /app/public /app/public/index.php
