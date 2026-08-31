#!/bin/bash
set -e

INIT_MARKER="/var/www/data/.initialized"

echo "Waiting for database..."
until mysqladmin ping -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --silent 2>/dev/null; do
    sleep 2
done

if [ ! -f "$INIT_MARKER" ]; then
    echo "First run - initializing database and seed data..."
    mkdir -p /var/www/data

    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < /var/www/database/schema.sql

    php /var/www/scripts/setup.php

    HASH=$(php -r "echo password_hash('1234', PASSWORD_BCRYPT);")
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "UPDATE users SET password='$HASH';"

    touch "$INIT_MARKER"
    echo "Database initialized."
else
    echo "Database already initialized, skipping schema import."
    php /var/www/scripts/setup.php 2>/dev/null || true
fi

echo "Setting upload permissions..."
chown -R www-data:www-data /var/www/public/uploads
chmod -R 755 /var/www/public/uploads

echo "OTOHASAR ready!"
exec "$@"
