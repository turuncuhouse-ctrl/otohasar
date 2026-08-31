#!/usr/bin/env bash
# İlk kurulum — sunucuda bir kez çalıştırın
set -euo pipefail

DATA_ROOT="${DATA_ROOT:-/mnt/1tb_disk/otohasar}"
REPO_URL="${REPO_URL:-https://github.com/KULLANICI/otohasar.git}"
BRANCH="${BRANCH:-main}"

echo "=== OTOHASAR Stack İlk Kurulum ==="
echo "Veri dizini: $DATA_ROOT"

# Dizinleri oluştur
sudo mkdir -p "$DATA_ROOT"/{app,data/mysql,data/uploads,env}
sudo chown -R "$USER:$USER" "$DATA_ROOT"

# Uygulama kodunu klonla (zaten varsa atla)
if [ ! -d "$DATA_ROOT/app/.git" ]; then
    echo "Repo klonlanıyor: $REPO_URL"
    git clone --branch "$BRANCH" "$REPO_URL" "$DATA_ROOT/app"
else
    echo "Repo zaten mevcut: $DATA_ROOT/app"
fi

# Ortam dosyası
if [ ! -f "$DATA_ROOT/env/.env" ]; then
    cp "$DATA_ROOT/app/.env.stack.example" "$DATA_ROOT/env/.env"
    echo ""
    echo "ÖNEMLİ: $DATA_ROOT/env/.env dosyasını düzenleyin (şifreler, APP_URL)"
    echo ""
fi

# Docker Swarm aktif değilse başlat
if ! docker info 2>/dev/null | grep -q "Swarm: active"; then
    echo "Docker Swarm başlatılıyor..."
    docker swarm init 2>/dev/null || echo "Swarm zaten aktif veya init gerekmiyor"
fi

echo ""
echo "Kurulum dizinleri hazır."
echo "Sonraki adım:"
echo "  1. nano $DATA_ROOT/env/.env   (şifreleri güncelle)"
echo "  2. $DATA_ROOT/app/scripts/stack-deploy.sh"
