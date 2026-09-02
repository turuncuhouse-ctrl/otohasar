#!/usr/bin/env bash
# OTOHASAR test script — run after docker compose up
set -e
BASE="${1:-http://localhost:8080}"
COOKIE=$(mktemp)

echo "=== 1. Login (admin) ==="
RESP=$(curl -s -c "$COOKIE" -X POST "$BASE/api/login.php" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"1234"}')
echo "$RESP" | grep -q '"ok":true' && echo "OK: Login" || { echo "FAIL: Login"; echo "$RESP"; exit 1; }

echo "=== 2. Dashboard loads ==="
PAGE=$(curl -s -b "$COOKIE" "$BASE/dashboard.php")
echo "$PAGE" | grep -q 'Hasar Dosya\|Dosyalarım\|Pano\|Sistem' && echo "OK: Dashboard" || echo "WARN: Dashboard markup unexpected"

echo "=== 3. CSRF without token ==="
CODE=$(curl -s -o /dev/null -w "%{http_code}" -b "$COOKIE" -X POST "$BASE/api/status.php" \
  -d "damage_file_id=1&status=eksperde")
[ "$CODE" = "403" ] && echo "OK: CSRF blocked ($CODE)" || echo "FAIL: CSRF ($CODE)"

echo "=== 4. Manifest + SW ==="
curl -s "$BASE/manifest.json" | grep -q 'OTOHASAR' && echo "OK: manifest.json"
curl -s "$BASE/sw.js" | grep -q 'CACHE_NAME' && echo "OK: sw.js"

echo "=== 5. Login page has no demo chips ==="
LOGIN=$(curl -s "$BASE/login.php")
echo "$LOGIN" | grep -q 'demo-chips' && echo "FAIL: demo chips still present" || echo "OK: no demo chips"

echo "=== All tests done ==="
rm -f "$COOKIE"
