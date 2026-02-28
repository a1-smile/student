#!/usr/bin/env bash
# user_agent_check() 連続アクセステストスクリプト
# 1回目: test-index.php にアクセスしてセッション開始
# 以降: 同じクッキーで test-basic-safety.php にアクセス

# -e: エラー時にスクリプトを終了
set -e

BASE_URL="http://localhost/student"

# curl をブロックされないよう、ブラウザ風の UA を指定
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'

# クッキーファイル（既存の curl スクリプトと同じく curl/ 配下に保存）
COOKIE_FILE="curl/cookies-test1.txt"

# 古いクッキーを削除
# rm は削除コマンド
# -f オプションは、ファイルが存在しない場合でもエラーにしない
rm -f "$COOKIE_FILE"

echo "1) test_index.php にアクセスしてセッション開始（クッキー保存）"
#  -i: レスポンスヘッダーも表示
#  -c: クッキーファイルに保存
curl -i -c "$COOKIE_FILE" \
  -A "$UA" \
  "$BASE_URL/test_index.php"

echo
echo "2) user_agent_check() の連続アクセスによる閾値超え検証"
# 追加テスト: user_agent_check() の連続アクセスによる閾値超え検証



THRESHOLD=10
LIMIT=$((THRESHOLD + 2))

echo " Starting continuous access (LIMIT=$LIMIT) ---"
for ((i = 1; i <= LIMIT; i++)); do
#  -i: レスポンスヘッダーも表示
#  -b: クッキーファイルを使用
#  -L: リダイレクトをたどる
#  -s: 静かに実行
#  -o /dev/null: 出力を捨てる
#  -A: ユーザーエージェント指定
#  -w: フォーマット指定で結果を表示
  RESULT=$(curl -i -s -L -b "$COOKIE_FILE" -A "$UA" \
           -o /dev/null -w "%{http_code} -> %{url_effective}" \
           "$BASE_URL/test-basic-safety.php")

  echo "Access $i: $RESULT"
  sleep 0.2
done
