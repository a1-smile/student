# -c は curl の「クッキーをファイルに保存」オプションです。
# -i は curl の「レスポンスヘッダーも表示」オプションです。
# -A は curl の「User-Agent を指定する」オプションです。
# 送信するリクエストヘッダーの User-Agent を任意文字列に設定します。
# ユーザーエージェントチェックで curl を禁止しているので、
# User-Agent を設定してアクセスします。 

#cookies.txt にクッキーを保存します。
# 80 の場合
curl -i -c curl/cookies.txt -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36" \
  http://localhost/student/index.php

# 8888 の場合
curl -i -c curl/cookies.txt -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36" \
  http://localhost:8888/student/index.php