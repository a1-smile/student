# 実行方法
#$ cd /c/MAMP/htdocs/student
# bash test-index-php.sh


BASE=http://localhost/student
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'
JAR=curl/cookies.txt
HTML=out-test-post.html

# データベースをクリアしてから実行してする。

#-s: 進捗などの表示を消して静かに実行（silent）
#-i: レスポンスヘッダも出力（include headers）
#-c "$JAR": 受け取ったクッキーを
# ファイルに保存（cookie jar）
#-A "$UA": User-Agent ヘッダを指定
#-o "$HTML": レスポンスボディをファイルに保存

curl -s -i -c "$JAR" -A "$UA" "$BASE/index.php" -o "$HTML"

TOKEN=$(grep -oP 'name="csrf_token"\s+value="\K[0-9a-fA-F]{64}' "$HTML")
echo "CSRF_TOKEN=$TOKEN"


# -s: 進捗などの表示を消して静かに実行（silent）
# -S: エラー時にメッセージを表示
# -sS: 余計な表示はしないが、エラー時には表示
# -v: 詳細な情報を表示（verbose）
# -i: レスポンスヘッダも出力（include headers）
# -b "$JAR": クッキーをファイルから送信（cookie）
# -A "$UA": User-Agent ヘッダを指定
# -H "Content-Type: application/x-www-form-urlencoded":
#   POSTデータの形式を指定
# -e "$BASE/index.php": リファラヘッダを指定
# --data-urlencode "csrf_token=$TOKEN": POSTデータを指定
curl -sS -v -i -b "$JAR" \
  -A "$UA" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -e "$BASE/index.php" \
  --data-urlencode "csrf_token=$TOKEN" \
  "$BASE/test-basic-safety.php"