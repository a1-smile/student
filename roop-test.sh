# test-post.php にアクセスして Cookie 取得


# 32バイト乱数 → 16進（64文字）生成

# それを invalid_token として POST送信

# User-Agent を付与

# 15回繰り返す

# 各回の HTTPステータス / リダイレクト先 URL を表示

# 完全に実行できるスクリプトを作成します。


# 実行方法
# データベースをクリアしてから実行してする。
# cd /c/MAMP/htdocs/student
# bash roop-test.sh


#!/bin/bash

# --- 設定 ---
BASE_URL="http://localhost/student"        # 変更可
POST_URL="$BASE_URL/test-basic-safety.php"
TEST_URL="$BASE_URL/test-post.php"
UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36"

COOKIE="curl/cookies.txt"

# --- Cookie 初期取得 ---
echo "== 初回アクセス：Cookie取得 =="
# /dev/null は出力をしない処理を実行
# -A "$UA": User-Agent ヘッダを指定
curl -s -c "$COOKIE" -A "$UA" "$TEST_URL" > /dev/null

echo "開始: CSRF不正トークンテスト"
echo ""

# --- 15回繰り返し ---
for i in {1..15}; do
    echo "--- $i 回目 ---"

    # 32バイト乱数を 16進（64文字）へ
    TOKEN=$(openssl rand -hex 32)

    echo "送信 invalid_token: $TOKEN"

    # -D はヘッダ出力
    # -o /dev/null はボディ出力をしない
    # -s は進捗やエラーメッセージを表示しない
    # -e は Referer ヘッダを指定
    # -A "$UA": User-Agent ヘッダを指定
    #--data-urlencode "csrf_token=$TOKEN"
    # はフォームデータをURLエンコードして送信し、
    # curlはこのオプションがあると自動的にPOSTを使用します。"$POST_URL"が送信先のエンドポイントです。
    #"$POST_URL"が送信先のエンドポイントです。
    RESPONSE=$(curl -s -D - -o /dev/null \
  -b "$COOKIE" \
  -A "$UA" \
  -e "$TEST_URL" \
  --data-urlencode "csrf_token=$TOKEN" \
  "$POST_URL")



    # HTTPステータス抽出
    # HTTP/1.1 200 OK のような形式で返される。
    # この行は、変数RESPONSEに格納された
    # HTTPレスポンスヘッダから最初の1行だけを取り出し、
    # STATUSに代入しています。
    # curlの-D -によってRESPONSEにはヘッダ全体
    # （先頭行は「HTTP/1.1 302 Found」などのステータスライン）
    # が入っているため、head -n 1でそのステータスラインを抽出し、
    # 後続処理でHTTPバージョン・ステータスコード・メッセージを扱えるようにしています。

    # 注意点として、
    # ヘッダの先頭行が必ずステータスラインであることに
    # 依存しています。
    # より堅牢にステータスコードだけを取りたい場合は、
    # HTTP行を明示的に抽出し、
    # コード部分をパースする方法が安全です
    # （例: grepで「^HTTP/」を拾ってからawkで$2を取り出す）。
    # また、複数回のリダイレクトがある場合、
    # -Lを使うと最終応答のステータスラインではなく
    # 中間のヘッダが混在することがあるため、
    # テスト要件に応じて挙動を選択してください。

    STATUS=$(echo "$RESPONSE" | head -n 1)

    # Location 抽出（リダイレクト先）
    # grep -i "^Location:" で
    # 大文字小文字を無視して(-iオプション)
    # Location ヘッダ行を抽出し、
    # awk '{print $2}' で2番目のフィールド
    # （URL部分）を取り出しています。
    # このawkのワンライナーは、
    # 入力行を空白でフィールド分割し、
    # 2番目のフィールドだけを出力します。
    # たとえば「Location: http://example.com」
    # のようなヘッダ行に対しては、先頭が「Location:」、
    # 2番目がURLになるため、URL部分のみを取り出せます。
    LOCATION=$(echo "$RESPONSE" | grep -i "^Location:" | awk '{print $2}')

    echo "HTTP: $STATUS"
    echo "Location: $LOCATION"
    echo ""

    # 1秒待機（サーバー負荷軽減）
    sleep 1
done

echo "完了！"
