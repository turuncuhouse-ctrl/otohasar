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

if [ ! -d .git ]; then
    echo "HATA: $APP_DIR bir git reposu degil."
    exit 1
fi

echo "[1/5] Git fetch + reset..."
git fetch origin
git reset --hard "origin/${BRANCH}"

echo "[2/5] Satir sonlari duzeltiliyor..."
sed -i 's/\r$//' docker/entrypoint.sh 2>/dev/null || true
find scripts -name '*.sh' -exec sed -i 's/\r$//' {} \; 2>/dev/null || true

echo "[3/5] Legacy kok dizin senkronu (eski volume mount)..."
if [ "$APP_DIR" != "$DATA_ROOT" ] && [ -d "$DATA_ROOT" ]; then
    rsync -a --delete \
        --exclude data --exclude env --exclude app \
        "$APP_DIR/" "$DATA_ROOT/"
fi

echo "[4/5] Container/stack guncelleme..."
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

echo "[4b/5] php-zip kontrol..."
docker exec -u root otohasar_php bash -lc 'php -m | grep -qi "^zip$" || (apt-get update -qq && apt-get install -y -qq libzip-dev zlib1g-dev && docker-php-ext-install zip)' 2>/dev/null \
    || sudo docker exec -u root otohasar_php bash -lc 'php -m | grep -qi "^zip$" || (apt-get update -qq && apt-get install -y -qq libzip-dev zlib1g-dev && docker-php-ext-install zip)' 2>/dev/null \
    || true

docker restart otohasar_php 2>/dev/null || sudo docker restart otohasar_php 2>/dev/null || true

echo "[4c/5] Migrations..."
sleep 5
for migrate in migrate_v2.php migrate_v3.php migrate_v4.php migrate_v5.php; do
    docker exec otohasar_php php "/var/www/scripts/$migrate" 2>/dev/null \
        || sudo docker exec otohasar_php php "/var/www/scripts/$migrate" 2>/dev/null \
        || true
done

echo "[5/5] Canli kontrol..."
sleep 3
docker ps --filter name=otohasar_ --format 'table {{.Names}}\t{{.Status}}' 2>/dev/null \
    || sudo docker ps --filter name=otohasar_ --format 'table {{.Names}}\t{{.Status}}' 2>/dev/null \
    || true

if ! curl -sf "http://127.0.0.1:${HTTP_PORT}/assets/js/app.js" | grep -q snapshotInputFiles; then
    echo "HATA: Canli app.js guncellenmedi (snapshotInputFiles yok)."
    echo "  Kontrol: curl http://127.0.0.1:${HTTP_PORT}/assets/js/app.js | head"
    exit 1
fi

if ! curl -sf "http://127.0.0.1:${HTTP_PORT}/login.php" | grep -q 'app.js?v=12'; then
    echo "UYARI: asset_version hala 12 degil — config.php kontrol edin."
fi

echo ""
echo "Deploy tamamlandi."
