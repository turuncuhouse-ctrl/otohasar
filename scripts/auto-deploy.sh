#!/usr/bin/env bash
# Sunucuda GitHub güncellemesi (Portainer stack ile uyumlu)
# Kullanım: ./scripts/auto-deploy.sh
# GitHub Actions her push'ta bunu çalıştırır.
set -euo pipefail

APP_DIR="${APP_DIR:-/mnt/1tb_disk/otohasar}"
BRANCH="${1:-main}"

cd "$APP_DIR"

echo "=== OTOHASAR auto-deploy ==="
echo "Dizin: $APP_DIR"
echo "Branch: $BRANCH"

if [ ! -d .git ]; then
    echo "HATA: Bu dizin bir git reposu değil."
    echo "Ilk kurulum icin GITHUB-DEPLOY.md dosyasina bakin."
    exit 1
fi

echo "[1/4] Git fetch + reset..."
git fetch origin
git reset --hard "origin/${BRANCH}"

echo "[2/4] Satir sonlari duzeltiliyor..."
sed -i 's/\r$//' docker/entrypoint.sh 2>/dev/null || true
find scripts -name '*.sh' -exec sed -i 's/\r$//' {} \; 2>/dev/null || true

echo "[3/4] Container yeniden baslatiliyor..."
if command -v docker >/dev/null 2>&1; then
    docker restart otohasar_php otohasar_nginx 2>/dev/null || sudo docker restart otohasar_php otohasar_nginx
else
    echo "HATA: docker bulunamadi"
    exit 1
fi

echo "[4/4] Kontrol..."
sleep 3
docker ps --filter name=otohasar_ --format 'table {{.Names}}\t{{.Status}}' 2>/dev/null \
  || sudo docker ps --filter name=otohasar_ --format 'table {{.Names}}\t{{.Status}}'

echo ""
echo "Deploy tamamlandi."
echo "Test: curl -I http://127.0.0.1:4080"
