#!/usr/bin/env bash
# Build + Stack deploy — her güncellemede çalıştırın
set -euo pipefail

DATA_ROOT="${DATA_ROOT:-/mnt/1tb_disk/otohasar}"
STACK_NAME="${STACK_NAME:-otohasar}"
ENV_FILE="${ENV_FILE:-$DATA_ROOT/env/.env}"
APP_DIR="${APP_DIR:-$DATA_ROOT/app}"

APP_DIR="${APP_DIR:-$DATA_ROOT/app}"

if [ ! -f "$ENV_FILE" ]; then
    if [ -f "$APP_DIR/deploy/env/.env" ]; then
        mkdir -p "$(dirname "$ENV_FILE")"
        cp "$APP_DIR/deploy/env/.env" "$ENV_FILE"
        chmod 600 "$ENV_FILE"
        echo ".env otomatik kopyalandı: $ENV_FILE"
    else
        echo "HATA: $ENV_FILE bulunamadı."
        exit 1
    fi
fi

# shellcheck disable=SC1090
set -a
source "$ENV_FILE"
set +a

echo "=== OTOHASAR Deploy ==="
echo "App:  $APP_DIR"
echo "Port: ${HTTP_PORT:-4080}"
echo "URL:  ${APP_URL}"

cd "$APP_DIR"

# Git pull (varsa)
if [ -d .git ]; then
    echo "Git pull..."
    git pull --ff-only origin "${BRANCH:-main}" || true
fi

# PHP imajını build et
echo "Docker image build..."
docker build -t "${PHP_IMAGE:-otohasar-php:latest}" .

# Stack deploy
echo "Stack deploy: $STACK_NAME"
docker stack deploy \
    -c docker-compose.stack.yml \
    --env-file "$ENV_FILE" \
    --resolve-image never \
    "$STACK_NAME"

echo ""
echo "Deploy tamamlandı. Servisler başlıyor..."
sleep 5
docker stack services "$STACK_NAME"

echo ""
echo "Erişim: http://$(hostname -I | awk '{print $1}'):${HTTP_PORT:-4080}"
echo "Loglar: docker service logs -f ${STACK_NAME}_nginx"
