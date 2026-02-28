#!/usr/bin/env bash
# user_agent_check() UA 不一致ケーステスト
# 1回目: test-index.php にアクセスしてセッション開始

# 以下ループに入り、
# UA 不一致で test-basic-safety.php に連続アクセス
# ループ終了します。

# -e: エラー時にスクリプトを終了
set -e

# 必要に応じてポート番号を 8888 などに変更してください
BASE_URL="http://localhost/student"

# curl をブロックされないよう、ブラウザ風の UA を指定
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'
# 異なる UA（不正な UA）を配列として5個用意する
INVALID_UA=('InvalidUserAgent/1.0' 'AnotherInvalidUA/2.0' 'FakeBrowser/3.0' 'FakeBrowser/4.0' 'FakeBrowser/5.0')
# クッキーファイル（既存の curl スクリプトと同じく curl/ 配下に保存）
COOKIE_FILE="curl/cookies-test1.txt"

# 古いクッキーを削除
# rm は削除コマンド
# -f オプションは、ファイルが存在しない場合でもエラーにしない
rm -f "$COOKIE_FILE"

echo "1) test_index.php にアクセスしてセッション開始（クッキー保存）"
#  -i: レスポンスヘッダーも表示
#  -c: クッキーファイルに保存
#  -A: ユーザーエージェントを指定
#  -o /dev/null: レスポンスボディーは表示しない
curl -i -c "$COOKIE_FILE" \
  -A "$UA" \
  -o /dev/null \
  "$BASE_URL/test_index.php"

#   以下はループに入ります。
# 閾値
THRESHOLD=2
LIMIT=$((THRESHOLD + 2))
# ループ開始

# 配列 INVALID_UA の要素を順に取り出して変数 i に代入
for i in "${INVALID_UA[@]}"; do
echo
echo "2)  UA 不一致で test-basic-safety.php にアクセス（user_agent_check 実行）"
# -s: サイレントモード
# -L: リダイレクトをたどる
# -b: クッキーファイルを使用
# -A: ユーザーエージェントを指定
# -o /dev/null: レスポンスボディーは表示しない
# -w: 最終的な HTTP ステータスと URL を表示
#  -A: ユーザーエージェントを指定
# $INVALID_UAと'i'を文字列連結して、毎回異なるUAを設定
curl -s -L -b "$COOKIE_FILE" \
  -A "${i}" \
  -o /dev/null \
  -w "HTTPステータス: %{http_code} -> 最終URL: %{url_effective}\n" \
  "$BASE_URL/test-basic-safety.php"

echo
# サーバーに負担をかけないよう、少し待機
sleep 0.5
# ループ終了
  ((LIMIT--))
  if [ $LIMIT -le 0 ]; then
    break
  fi
done

echo "テスト完了: リダイレクト先を確認してください。"