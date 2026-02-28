#!/usr/bin/env bash
# user-agent-check.phpで定義した
# 関数 user_agent_check() のチェックをおこないます。

# vs code のターミナルの git bash で
# curl コマンドを使用して
# 動作確認を行います。
# test_index.php へアクセス
# してから、
# test-basic-safety.php へアクセスする流れを
# を想定しています。

# 同一ipアドレスから、
# session id
# を変化させながら、
# 同一ユーザーエージェントで
# 連続アクセスした場合の動作を確認を行います。
# この場合、閾値    
# $ACCESS_THRESHOLD_RECAPTCHA       = ['session'=>10, 'ip'=>15];
# のうちの
# $ACCESS_THRESHOLD_RECAPTCHA['ip'](つまり15回)
# を超えた場合にerror_page.phpへリダイレクト
# されることを確認したいと思います。

# 以下の手順で確認を行います。

# まず、
# ユーザーエージェントを一定に
# 設定しておきます。

# 以下はループに入ります。
# まず、
# cookie を保存するファイルをクリアしておきます。
# 最初はcookie が無い状態です。
# 先に設定しておいたユーザーエージェントを指定して
# test_index.php へアクセスすると、
# session が開始されます。
# cookie を取得します。
# cookie に session id が保存されます。
# これで、新しい session id が発行されます。
# この時レスポンスボディーは表示しません。

# その後、cookie を使用して、
# 同一ユーザーエージェントで
# test-basic-safety.php へアクセスします。
# test-basic-safety.php 内で
# ユーザーエージェントは一致していると判定されます。
# また、同一ipアドレスからのアクセスとして
# アクセスがカウントされます。
# カウント数がデータベースに保存されます。

# ヘッダーとhttpレスポンス、リダイレクト先を確認します。
# ループ終了します。
# これを閾値を超えるまで繰り返します。

# 以下はコードです。
# -e: エラー時にスクリプトを終了
set -e

BASE_URL="http://localhost/student"

# curl をブロックされないよう、ブラウザ風の UA を指定
# こちらのUAは固定して使用します。
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'

# クッキーファイル（既存の curl スクリプトと同じく curl/ 配下に保存）
COOKIE_FILE="curl/cookies-test1.txt"



echo
echo "2) user_agent_check() の連続アクセスによる閾値超え検証"
# 追加テスト: user_agent_check() の連続アクセスによる閾値超え検証



THRESHOLD=15
LIMIT=$((THRESHOLD + 2))

echo " Starting continuous access (LIMIT=$LIMIT) ---"
for ((i = 1; i <= LIMIT; i++)); do

# 古いクッキーを削除
# rm は削除コマンド
# -f オプションは、ファイルが存在しない場合でもエラーにしない
rm -f "$COOKIE_FILE"

echo " test_index.php にアクセスしてセッション開始（クッキー保存）"
#  -i: レスポンスヘッダーも表示
#  -c: クッキーファイルに保存
#  レスポンスボディーは表示しない
curl -s -D - -o /dev/null -c "$COOKIE_FILE" \
  -A "$UA" \
  "$BASE_URL/test_index.php"
  RESULT=$(curl -i -s -L -b "$COOKIE_FILE" -A "$UA" \
           -o /dev/null -w "%{http_code} -> %{url_effective}" \
           "$BASE_URL/test-basic-safety.php")
echo "-----------------------------"
echo "Access $i"
  echo "Access $i: $RESULT"
  sleep 0.2
done
