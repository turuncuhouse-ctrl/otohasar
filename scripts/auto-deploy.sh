#!/usr/bin/env bash
# Sunucuda GitHub guncellemesi (Stack + legacy compose uyumlu)
set -euo pipefail

DATA_ROOT="${DATA_ROOT:-/mnt/1tb_disk/otohasar}"
APP_DIR="${APP_DIR:-$DATA_ROOT/app}"
BRANCH="${1:-main}"
ENV_FILE="${ENV_FILE:-$DATA_ROOT/env/.env}"
HTTP_PORT="${HTTP_PORT:-4080}"

cd "$APP_DIR"

echo "=== OTOHASAR auto-deploy ==="
echo "App:  $APP_DIR"
echo "Data: $DATA_ROOT"
echo "Branch: $BRANCH"
echo "[1/5] Kod zaten deploy workflow ile senkron — git atlanıyor."

echo "[2/5] Versioned asset kopyalari..."
ASSET_VER=$(grep -oP "'asset_version'\s*=>\s*'\K[0-9]+" config/config.php || echo "14")
cp public/assets/js/app.js "public/assets/js/app.${ASSET_VER}.js"
cp public/assets/css/style.css "public/assets/css/style.${ASSET_VER}.css"

echo "[3/5] Satir sonlari duzeltiliyor..."
sed -i 's/\r$//' docker/entrypoint.sh 2>/dev/null || true
find scripts -name '*.sh' -exec sed -i 's/\r$//' {} \; 2>/dev/null || true

echo "[4/5] Legacy kok dizin senkronu (eski volume mount)..."
if [ "$APP_DIR" != "$DATA_ROOT" ] && [ -d "$DATA_ROOT" ]; then
    rsync -a --delete \
        --exclude data --exclude env --exclude app \
        "$APP_DIR/" "$DATA_ROOT/"
fi

echo "[5/5] Container/stack guncelleme..."
if docker stack ls 2>/dev/null | grep -qE '^otohasar '; then
    echo "  -> Docker Swarm stack-deploy"
    DATA_ROOT="$DATA_ROOT" APP_DIR="$APP_DIR" BRANCH="$BRANCH" ./scripts/stack-deploy.sh "$BRANCH"
elif docker ps --format '{{.Names}}' 2>/dev/null | grep -qx 'otohasar_nginx'; then
    echo "  -> Docker Compose prod (imaj rebuild)"
    if [ -f "$ENV_FILE" ]; then
        # shellcheck disable=SC1090
        set -a
        source "$ENV_FILE"
        set +a
    fi
    docker build -t otohasar-php:latest .
    docker build -f Dockerfile.nginx -t otohasar-nginx:latest .
    docker compose -f docker-compose.prod.yml --env-file "$ENV_FILE" up -d --force-recreate
else
    echo "  -> Bilinmeyen mod, servis restart deneniyor..."
    docker service update --force otohasar_php 2>/dev/null \
        || sudo docker service update --force otohasar_php 2>/dev/null \
        || docker restart otohasar_php 2>/dev/null \
        || sudo docker restart otohasar_php 2>/dev/null \
        || true
    docker service update --force otohasar_nginx 2>/dev/null \
        || sudo docker service update --force otohasar_nginx 2>/dev/null \
        || docker restart otohasar_nginx 2>/dev/null \
        || sudo docker restart otohasar_nginx 2>/dev/null \
        || true
fi

echo "[4b/5] php-zip + upload limit kontrol..."
docker exec -u root otohasar_php bash -lc '
  php -m | grep -qi "^zip$" || (apt-get update -qq && apt-get install -y -qq libzip-dev zlib1g-dev && docker-php-ext-install zip)
  printf "upload_max_filesize=22M\npost_max_size=64M\nmax_file_uploads=25\n" > /usr/local/etc/php/conf.d/zz-upload-limits.ini
' 2>/dev/null \
    || sudo docker exec -u root otohasar_php bash -lc '
  php -m | grep -qi "^zip$" || (apt-get update -qq && apt-get install -y -qq libzip-dev zlib1g-dev && docker-php-ext-install zip)
  printf "upload_max_filesize=22M\npost_max_size=64M\nmax_file_uploads=25\n" > /usr/local/etc/php/conf.d/zz-upload-limits.ini
' 2>/dev/null \
    || true

docker restart otohasar_php 2>/dev/null || sudo docker restart otohasar_php 2>/dev/null || true

echo "[4c/5] Migrations..."
sleep 5
for migrate in migrate_v2.php migrate_v3.php migrate_v4.php migrate_v5.php migrate_v6.php migrate_v7.php migrate_v8.php migrate_v9.php migrate_v10.php; do
    docker exec otohasar_php php "/var/www/scripts/$migrate" 2>/dev/null \
        || sudo docker exec otohasar_php php "/var/www/scripts/$migrate" 2>/dev/null \
        || true
done

echo "[5/5] Canli kontrol..."
sleep 3
docker ps --filter name=otohasar_ --format 'table {{.Names}}\t{{.Status}}' 2>/dev/null \
    || sudo docker ps --filter name=otohasar_ --format 'table {{.Names}}\t{{.Status}}' 2>/dev/null \
    || true

if ! curl -sf "http://127.0.0.1:${HTTP_PORT}/assets/js/app.${ASSET_VER}.js" | grep -q snapshotInputFiles; then
    echo "HATA: Canli app.${ASSET_VER}.js guncellenmedi."
    echo "  Kontrol: curl http://127.0.0.1:${HTTP_PORT}/assets/js/app.js | head"
    exit 1
fi

if ! curl -sf "http://127.0.0.1:${HTTP_PORT}/login.php" | grep -q "app.${ASSET_VER}.js"; then
    echo "UYARI: asset JS yolu guncellenmedi — footer.php kontrol edin."
fi

echo ""
echo "Deploy tamamlandi."
