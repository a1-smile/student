# cd /c/MAMP/htdocs/student
# bash test3-b-up.sh
# 繰り返し回数を変更
# ITER=5 bash test3-b-up.sh 
# デフォルトでは15回




#!/usr/bin/env bash
set -euo pipefail

# Config
BASE=${BASE:-http://localhost/student}
UA=${UA:-'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'}
JAR=${JAR:-curl/cookies.txt}
HTML=${HTML:-out-test-post.html}
ITER=${ITER:-15}

POST_URL="$BASE/basic-safety.php"
TEST_URL="$BASE/test-post.php"

mkdir -p "$(dirname "$JAR")"

curl -s -i -c "$JAR" -A "$UA" "$TEST_URL" -o "$HTML"

echo "[CONFIG] BASE=$BASE"
echo "[CONFIG] UA=$UA"
echo "[CONFIG] JAR=$JAR"
echo "[CONFIG] HTML=$HTML"
echo

echo "Start: CSRF invalid token test ($ITER iterations)"

for i in $(seq 1 "$ITER"); do
  echo "--- Iteration $i ---"
  
  # セッションで新トークンを再セット
  curl -s -i -b "$JAR" -A "$UA" "$BASE/test-post.php" -o "$HTML"

  
  # 2) Extract CSRF token from HTML (for confirmation only)
  TOKEN=$(grep -oP 'name="csrf_token"\s+value="\K[0-9a-fA-F]{64}' "$HTML" | head -n1 || true)
  TOKEN=${TOKEN//$'\r'/}
  echo "CSRF_TOKEN=${TOKEN:-<none>}"
  
  # 3) Generate invalid token (random 32 bytes -> 64 hex chars)
  INVALID_TOKEN=$(openssl rand -hex 32)
  echo "INVALID_TOKEN=$INVALID_TOKEN"
  
  # 4) POST invalid token; capture headers only
  RESPONSE=$(curl -s -D - -o /dev/null \
    -b "$JAR" \
    -A "$UA" \
    -e "$TEST_URL" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    --data-urlencode "csrf_token=$INVALID_TOKEN" \
    "$POST_URL")
  
  STATUS_LINE=$(echo "$RESPONSE" | head -n1)
  LOCATION=$(echo "$RESPONSE" | grep -i '^Location:' | awk '{print $2}')
  
  echo "HTTP: $STATUS_LINE"
  echo "Location: ${LOCATION:-<none>}"
  echo
  
  # Throttle
  sleep 1
done

echo "Done."
