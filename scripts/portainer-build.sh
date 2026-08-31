#!/usr/bin/env bash
# Portainer deploy ÖNCESİ bir kez çalıştırın
set -euo pipefail

DATA_ROOT="/mnt/1tb_disk/otohasar"

if [ ! -f "$DATA_ROOT/Dockerfile" ]; then
    echo "HATA: Proje dosyaları bulunamadı!"
    echo "      $DATA_ROOT dizinine tüm dosyaları yükleyin (WinSCP)"
    exit 1
fi

sudo mkdir -p "$DATA_ROOT/data/mysql" "$DATA_ROOT/data/uploads"
sudo chown -R "$USER:$USER" "$DATA_ROOT/data" 2>/dev/null || true

echo "PHP imajı build ediliyor (2-3 dk)..."
docker build -t otohasar-php:latest -f "$DATA_ROOT/Dockerfile" "$DATA_ROOT"

echo ""
echo "Hazır! Portainer'dan stack'i deploy edebilirsiniz."
docker images otohasar-php
