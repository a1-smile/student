# 取得と送信を verbose で再現（80 の場合）
TOKEN=$(curl -sS -v -c curl/cookies.txt \
  -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36" \
  http://localhost/student/index.php \
  | grep -oP 'name="csrf_token"\s+value="\K[^"]+')

echo "CSRF_TOKEN=$TOKEN"
cat curl/cookies.txt

curl -sS -v -i -b curl/cookies.txt \
  -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -e "http://localhost/student/index.php" \
  --data-urlencode "csrf_token=$TOKEN" \
  http://localhost/student/test-basic-safety.php