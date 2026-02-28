#!/usr/bin/env bash
# user_agent_check() 正常ケーステスト
# 1回目: test-index.php にアクセスしてセッション開始
#    クッキーを保存

# 2回目: 同じクッキーで test-basic-safety.php にアクセス

# -e: エラー時にスクリプトを終了
set -e

# 必要に応じてポート番号を 8888 などに変更してください
BASE_URL="http://localhost/student"

# curl をブロックされないよう、ブラウザ風の UA を指定
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'

# クッキーファイル（既存の curl スクリプトと同じく curl/ 配下に保存）
COOKIE_FILE="curl/cookies-test1.txt"

# 古いクッキーを削除
# rm -f は、ファイルが存在しない場合でもエラーにしない
# rm は削除コマンド
# -f オプションは、ファイルが存在しない場合でもエラーにしない
rm -f "$COOKIE_FILE"

echo "1) test_index.php にアクセスしてセッション開始（クッキー保存）"
#  -i: レスポンスヘッダーも表示
#  -c: クッキーファイルに保存
#  -A: User-Agent ヘッダーを指定
curl -i -c "$COOKIE_FILE" \
  -A "$UA" \
  "$BASE_URL/test_index.php"

echo
echo "2) 保存したクッキーを使って test-basic-safety.php にアクセス（user_agent_check 実行）"
#  -i: レスポンスヘッダーも表示
#  -b: クッキーファイルから読み込み

curl -i -b "$COOKIE_FILE" \
  -A "$UA" \
  "$BASE_URL/test-basic-safety.php"

echo
echo "テスト完了: error_log に『ユーザーエージェントチェック完了』が出力されているか確認してください"