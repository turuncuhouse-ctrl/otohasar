#!/bin/bash
set -e

INIT_MARKER="/var/www/data/.initialized"

# Ensure required tools/extensions exist (container recreate-safe)
if ! php -m 2>/dev/null | grep -qi pdo_mysql; then
    echo "PHP eklentileri eksik, kuruluyor..."
    apt-get update -qq
    apt-get install -y -qq default-mysql-client libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
    docker-php-ext-install -j"$(nproc)" pdo_mysql gd
    echo "PHP eklentileri kuruldu."
fi

if ! command -v mysqladmin >/dev/null 2>&1; then
    apt-get update -qq
    apt-get install -y -qq default-mysql-client
fi

echo "Waiting for database..."
tries=0
until mysqladmin ping -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --silent 2>/dev/null; do
    tries=$((tries + 1))
    if [ "$tries" -ge 60 ]; then
        echo "HATA: Veritabanina 2 dakikada baglanilamadi."
        echo "DB_HOST=$DB_HOST DB_USER=$DB_USER DB_NAME=$DB_NAME"
        exit 1
    fi
    sleep 2
done
echo "Database OK."

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

echo "Running migrations..."
php /var/www/scripts/migrate_v2.php || true
php /var/www/scripts/migrate_v3.php || true
php /var/www/scripts/migrate_v4.php || true
php /var/www/scripts/migrate_v5.php || true
php /var/www/scripts/migrate_v6.php || true
php /var/www/scripts/migrate_v7.php || true
php /var/www/scripts/migrate_v8.php || true
php /var/www/scripts/migrate_v9.php || true
php /var/www/scripts/migrate_v10.php || true
php /var/www/scripts/migrate_v11.php || true

# ZIP extension for document downloads (required)
ensure_zip() {
    if php -m 2>/dev/null | grep -qi '^zip$'; then
        return 0
    fi
    echo "Installing php-zip..."
    apt-get update -qq
    apt-get install -y -qq libzip-dev zlib1g-dev
    docker-php-ext-configure zip
    docker-php-ext-install zip
}
ensure_zip

echo "Setting upload permissions..."
mkdir -p /var/www/public/uploads
chown -R www-data:www-data /var/www/public/uploads
chmod -R 755 /var/www/public/uploads

echo "OTOHASAR ready!"
php -m | grep -i zip || echo "UYARI: zip eklentisi yuklenemedi"
exec "$@"
