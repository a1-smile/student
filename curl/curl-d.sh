# 1) クッキー保存しつつトークン取得
TOKEN=$(curl -s -c curl/cookies.txt \
  -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36" \
  http://localhost/student/index.php \
  | grep -oP 'name="csrf_token"\s+value="\K[^"]+')

echo "CSRF_TOKEN=$TOKEN"

# 2) トークンを付けて POST 送信（Referer を index.php に設定）

# -i は curl の「レスポンスヘッダーも表示」オプションです。
# -b は curl の「クッキーをファイルから送信」オプションです。
# -e は curl の「Referer ヘッダーを設定」オプションです。
# -d は curl の「POSTデータを送信」オプションです。

curl -i -b curl/cookies.txt \
  -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36" \
  -e "http://localhost/student/index.php" \
  -d "csrf_token=$TOKEN" \
  http://localhost/student/basic-safety.php