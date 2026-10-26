#!/bin/sh
set -eu

: "${PORT:=10000}"
: "${APP_KEY:?Configure APP_KEY dans les variables Render}"
: "${DB_CONNECTION:?Configure DB_CONNECTION=pgsql dans Render}"

if [ "$DB_CONNECTION" != "pgsql" ]; then
    echo "Pour ce déploiement Render, DB_CONNECTION doit être pgsql." >&2
    exit 1
fi

sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan migrate --force
if [ -n "${SIGEC_CENTRAL_ADMIN_EMAIL:-}" ]; then
    php artisan sigec:grant-access "$SIGEC_CENTRAL_ADMIN_EMAIL" central_admin
fi
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache
exec apache2-foreground


