#!/usr/bin/env bash
# OTOHASAR — Docker ile ilk kurulum
# Sunucuda: cd /mnt/1tb_disk/otohasar && ./scripts/docker-install.sh
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DATA_ROOT="/mnt/1tb_disk/otohasar"

echo "============================================"
echo "  OTOHASAR — Docker Kurulum"
echo "============================================"

# Proje doğru dizinde mi?
if [ "$PROJECT_DIR" != "$DATA_ROOT" ]; then
    echo ""
    echo "  UYARI: Proje dizini: $PROJECT_DIR"
    echo "  Beklenen:           $DATA_ROOT"
    echo ""
    echo "  Dosyaları şuraya koyun: $DATA_ROOT"
    echo "  Devam ediliyor..."
    echo ""
fi

# Veri dizinleri
echo "[1/3] Veri dizinleri oluşturuluyor..."
sudo mkdir -p "$DATA_ROOT/data/mysql" "$DATA_ROOT/data/uploads"
sudo chown -R "$USER:$USER" "$DATA_ROOT/data" 2>/dev/null || true

# .env kontrol
echo "[2/3] .env kontrol..."
cd "$PROJECT_DIR"
if [ ! -f .env ]; then
    if [ -f deploy/env/.env ]; then
        cp deploy/env/.env .env
    else
        echo "HATA: .env dosyası bulunamadı!"
        exit 1
    fi
fi

# Docker Compose
echo "[3/3] Docker build & start..."
if ! command -v docker &>/dev/null; then
    echo "HATA: Docker yüklü değil!"
    exit 1
fi

docker compose -f docker-compose.prod.yml up -d --build

echo ""
echo "============================================"
echo "  KURULUM TAMAMLANDI"
echo "============================================"
echo ""
echo "  Durum:  docker compose -f docker-compose.prod.yml ps"
echo "  Loglar: docker compose -f docker-compose.prod.yml logs -f"
echo ""
echo "  Erişim: http://$(hostname -I 2>/dev/null | awk '{print $1}'):4080"
echo "  Giriş:  hasardanismandemo / 1234"
echo "============================================"
