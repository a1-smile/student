#!/usr/bin/env bash
# user_agent_check() 正常ケーステスト
# 1回目: test-index.php にアクセスしてセッション開始
# 2回目: 同じクッキーで test-basic-safety.php にアクセス

# -e: エラー時にスクリプトを終了
set -e

# 必要に応じてポート番号を 8888 などに変更してください。
BASE_URL="http://localhost/student"

# UA を空文字に設定
UA=''

# クッキーファイル（既存の curl スクリプトと同じく curl/ 配下に保存）
COOKIE_FILE="curl/cookies-test1.txt"


echo
echo "2) 保存したクッキーを使って test-basic-safety.php にアクセス（user_agent_check 実行）"
curl -A "$UA" \
  "$BASE_URL/test-basic-safety.php"

echo
echo "テスト完了: error_log を確認してください"