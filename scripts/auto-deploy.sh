#!/usr/bin/env bash
# Sunucuda GitHub guncellemesi (Portainer stack ile uyumlu)
set -euo pipefail

APP_DIR="${APP_DIR:-/mnt/1tb_disk/otohasar/app}"
BRANCH="${1:-main}"

cd "$APP_DIR"

echo "=== OTOHASAR auto-deploy ==="
echo "Dizin: $APP_DIR"
echo "Branch: $BRANCH"

if [ ! -d .git ]; then
    echo "HATA: $APP_DIR bir git reposu degil."
    echo "Beklenen: /mnt/1tb_disk/otohasar/app (stack volume)"
    exit 1
fi

echo "[1/4] Git fetch + reset..."
git fetch origin
git reset --hard "origin/${BRANCH}"

echo "[2/4] Satir sonlari duzeltiliyor..."
sed -i 's/\r$//' docker/entrypoint.sh 2>/dev/null || true
find scripts -name '*.sh' -exec sed -i 's/\r$//' {} \; 2>/dev/null || true

echo "[3/4] Container yeniden baslatiliyor..."
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

# Ensure zip inside running PHP container (idempotent)
echo "[3b/4] php-zip kontrol..."
docker exec -u root otohasar_php bash -lc 'php -m | grep -qi "^zip$" || (apt-get update -qq && apt-get install -y -qq libzip-dev zlib1g-dev && docker-php-ext-install zip)' 2>/dev/null \
  || sudo docker exec -u root otohasar_php bash -lc 'php -m | grep -qi "^zip$" || (apt-get update -qq && apt-get install -y -qq libzip-dev zlib1g-dev && docker-php-ext-install zip)' 2>/dev/null \
  || true

docker restart otohasar_php 2>/dev/null || sudo docker restart otohasar_php 2>/dev/null || true

echo "[3c/4] Migrations..."
sleep 5
docker exec otohasar_php php /var/www/scripts/migrate_v2.php 2>/dev/null \
  || sudo docker exec otohasar_php php /var/www/scripts/migrate_v2.php 2>/dev/null \
  || true
docker exec otohasar_php php /var/www/scripts/migrate_v3.php 2>/dev/null \
  || sudo docker exec otohasar_php php /var/www/scripts/migrate_v3.php 2>/dev/null \
  || true
docker exec otohasar_php php /var/www/scripts/migrate_v4.php 2>/dev/null \
  || sudo docker exec otohasar_php php /var/www/scripts/migrate_v4.php 2>/dev/null \
  || true
docker exec otohasar_php php /var/www/scripts/migrate_v5.php 2>/dev/null \
  || sudo docker exec otohasar_php php /var/www/scripts/migrate_v5.php 2>/dev/null \
  || true

echo "[4/4] Kontrol..."
sleep 3
docker ps --filter name=otohasar_ --format 'table {{.Names}}\t{{.Status}}' 2>/dev/null \
  || sudo docker ps --filter name=otohasar_ --format 'table {{.Names}}\t{{.Status}}' 2>/dev/null \
  || true

echo ""
echo "Deploy tamamlandi."
