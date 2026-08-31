#!/usr/bin/env bash
# OTOHASAR test script — run after docker compose up
set -e
BASE="${1:-http://localhost:8080}"
COOKIE=$(mktemp)

echo "=== 1. Login ==="
RESP=$(curl -s -c "$COOKIE" -X POST "$BASE/api/login.php" \
  -H "Content-Type: application/json" \
  -d '{"username":"hasardanismandemo","password":"1234"}')
echo "$RESP" | grep -q '"ok":true' && echo "OK: Login" || { echo "FAIL: Login"; exit 1; }

echo "=== 2. Dashboard (6 files) ==="
PAGE=$(curl -s -b "$COOKIE" "$BASE/dashboard.php")
COUNT=$(echo "$PAGE" | grep -c 'kanban-card' || true)
[ "$COUNT" -eq 6 ] && echo "OK: 6 kanban cards" || echo "WARN: Found $COUNT cards (expected 6)"

echo "=== 3. CSRF without token ==="
CODE=$(curl -s -o /dev/null -w "%{http_code}" -b "$COOKIE" -X POST "$BASE/api/status.php" \
  -d "damage_file_id=1&status=eksperde")
[ "$CODE" = "403" ] && echo "OK: CSRF blocked ($CODE)" || echo "FAIL: CSRF ($CODE)"

echo "=== 4. Manifest + SW ==="
curl -s "$BASE/manifest.json" | grep -q 'OTOHASAR' && echo "OK: manifest.json"
curl -s "$BASE/sw.js" | grep -q 'CACHE_NAME' && echo "OK: sw.js"

echo "=== 5. Workshop upload restriction ==="
WCOOKIE=$(mktemp)
curl -s -c "$WCOOKIE" -X POST "$BASE/api/login.php" \
  -H "Content-Type: application/json" \
  -d '{"username":"atolyedemo","password":"1234"}' > /dev/null
# Get CSRF from dashboard
CSRF=$(curl -s -b "$WCOOKIE" "$BASE/dashboard.php" | grep -oP 'csrf-token" content="\K[^"]+' || echo "")
CODE=$(curl -s -o /dev/null -w "%{http_code}" -b "$WCOOKIE" -X POST "$BASE/api/upload.php" \
  -F "csrf=$CSRF" -F "damage_file_id=1" -F "category=onarim" -F "files[]=@/dev/null")
[ "$CODE" = "403" ] && echo "OK: Workshop blocked on non-onarimda ($CODE)" || echo "INFO: Workshop upload ($CODE)"

echo "=== All tests done ==="
rm -f "$COOKIE" "$WCOOKIE"
