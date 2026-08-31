#!/usr/bin/env bash
# Sunucuda GitHub guncellemesi (Portainer stack ile uyumlu)
set -euo pipefail

APP_DIR="${APP_DIR:-/mnt/1tb_disk/otohasar}"
BRANCH="${1:-main}"

cd "$APP_DIR"

echo "=== OTOHASAR auto-deploy ==="
echo "Dizin: $APP_DIR"
echo "Branch: $BRANCH"

if [ ! -d .git ]; then
    echo "HATA: Bu dizin bir git reposu degil."
    exit 1
fi

echo "[1/4] Git fetch + reset..."
git fetch origin
git reset --hard "origin/${BRANCH}"

echo "[2/4] Satir sonlari duzeltiliyor..."
sed -i 's/\r$//' docker/entrypoint.sh 2>/dev/null || true
find scripts -name '*.sh' -exec sed -i 's/\r$//' {} \; 2>/dev/null || true

echo "[3/4] Container yeniden baslatiliyor..."
if docker restart otohasar_php otohasar_nginx 2>/dev/null; then
    true
elif sudo docker restart otohasar_php otohasar_nginx 2>/dev/null; then
    true
else
    echo "UYARI: Container restart edilemedi (belki henuz yok)."
fi

echo "[4/4] Kontrol..."
sleep 3
docker ps --filter name=otohasar_ --format 'table {{.Names}}\t{{.Status}}' 2>/dev/null \
  || sudo docker ps --filter name=otohasar_ --format 'table {{.Names}}\t{{.Status}}' 2>/dev/null \
  || true

echo ""
echo "Deploy tamamlandi."
