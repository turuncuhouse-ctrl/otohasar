#!/usr/bin/env bash
# İlk kurulum — dosyaları sunucuya attıktan sonra bir kez çalıştırın
# Kullanım: cd /mnt/1tb_disk/otohasar/app && ./scripts/first-install.sh
set -euo pipefail

DATA_ROOT="/mnt/1tb_disk/otohasar"
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "============================================"
echo "  OTOHASAR — İlk Kurulum"
echo "============================================"
echo "App:  $APP_DIR"
echo "Data: $DATA_ROOT"
echo ""

if [ "$APP_DIR" != "$DATA_ROOT/app" ]; then
    echo "UYARI: Dosyaların $DATA_ROOT/app altında olması önerilir."
    echo "       Şu an: $APP_DIR"
    echo ""
fi

# 1. Veri dizinleri
echo "[1/4] Dizinler oluşturuluyor..."
sudo mkdir -p "$DATA_ROOT"/{data/mysql,data/uploads,env}
sudo chown -R "$USER:$USER" "$DATA_ROOT"

# 2. .env kopyala
echo "[2/4] .env yerleştiriliyor..."
cp "$APP_DIR/deploy/env/.env" "$DATA_ROOT/env/.env"
chmod 600 "$DATA_ROOT/env/.env"
echo "  → $DATA_ROOT/env/.env"

# 3. Docker Swarm
echo "[3/4] Docker Swarm..."
if ! docker info 2>/dev/null | grep -q "Swarm: active"; then
    docker swarm init
fi

# 4. Deploy
echo "[4/4] Stack deploy..."
chmod +x "$APP_DIR/scripts/"*.sh
bash "$APP_DIR/scripts/stack-deploy.sh"

echo ""
echo "============================================"
echo "  KURULUM TAMAMLANDI"
echo "============================================"
echo "  http://$(hostname -I 2>/dev/null | awk '{print $1}'):4080"
echo "  Giriş: admin / 1234 (şifreyi değiştirin)"
echo "  Şifreler: deploy/SIFRELER.txt"
echo "============================================"
