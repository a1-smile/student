# AI に対するクエリ（プロンプト）：
# basic-safety.phpのcsrf対策の部分のテストコードを
# test.shにcurlを使用してつくります。
# csrf_tokenの型式はあっているが、
# 値が異なるトークンをpost送信で、
# basic-safety.phpへ送り、
# レスポンスヘッダーを確認するという処理を15回行います。
# 1回失敗でエラーページ、
# 3回失敗でrecaptcha.php、
# 10回失敗でblock_page.phpへリダイレクトされることを
# 確認するようにしてください。
# 以上をvsコードのターミナルのgit bashで行えるように、
# test.shファイルに作ってください。

# データベースをクリアしてから実行してする。






#!/usr/bin/env bash

# Git Bash で動作する CSRF テストスクリプト
# 目的: basic-safety.php に「形式は正しいが値が不一致」のトークンを 15 回 POST し、
#       レスポンスヘッダの Location とステータスを確認する。
# 期待: 1回目以降は error_page.php、3回到達で recaptcha.php、10回到達で block_page.php にリダイレクト。

# -euo のオプションは、エラーで即時終了。
# 途中のコマンド失敗などを、発見できる。
# pipefail はパイプライン内の任意のコマンド失敗でも終了。
set -euo pipefail

BASE_URL="http://localhost/student"
FORM_PAGE="$BASE_URL/test-post.php"  # トークンが埋め込まれているフォームページ
POST_TARGET="$BASE_URL/test-basic-safety.php"

COOKIES_FILE="cookies.txt" # あとで、curl が自動作成します。
FORM_FILE="form.html"     # あとで、curl が自動作成します。
HEADERS_DIR="headers" # あとで、 curl が自動作成します。
RESP_DIR="responses" # あとで、 curl が自動作成します。

# 作業ディレクトリ準備
# -p オプションで既存ディレクトリがあってもエラーにならない
# 2階層分のディレクトリーを作成
# headers/responses
mkdir -p "$HEADERS_DIR" "$RESP_DIR"

# cookies.txt が既にあれば削除
# -f オプションで存在しなくてもエラーにならない
rm -f "$COOKIES_FILE"

echo "[1/4] フォームページからセッションとCSRFトークンを取得します…"

# クッキーを保存しつつフォームを取得（Git Bash 同梱の curl 利用）
curl -s -c "$COOKIES_FILE" "$FORM_PAGE" -o "$FORM_FILE"
# つまり「FORM_PAGEにアクセスして、
# サーバが返すクッキーを cookies.txt に記録し、
# 返ってきたHTMLを FORM_FILE に保存する」動きです。
# Git Bashのcurlは本家なのでそのまま使えます。


# -s: 進捗などの表示を消して静かに実行（silent）
# -c "$COOKIES_FILE": 受け取ったクッキーをファイルに保存（cookie jar）
# ここでは cookies.txt に保存し、後続のPOSTで再利用します
# -o "$FORM_FILE": レスポンス本文をファイルに保存


# grepは、テキストから「特定の文字列やパターンに一致する行」
# を探して表示するコマンドです。
# ログやHTMLから必要な情報だけ抜き出すのに便利です。

# 基本の使い方

# 文字列で検索
# grep "ERROR" file.txt
# 大文字小文字を無視
# grep -i "error" file.txt
# 行番号も表示
# grep -n "error" file.txt
# 一致した部分だけ表示（パターンが複数行にまたがらない前提）
# grep -o "error" file.txt

# 今回のtest.shでの使い方

# HTMLから hiddenのcsrf_tokenを抽出
# （大小無視・一致部分だけ取り出し）

# grep -oiE 'name="csrf_token"[^>]*value="[a-f0-9]+'
# [^>]*: 閉じタグまでの任意の文字列をスキップ、続いて
# value="xxxx": value属性の値を取得


# -o: 一致部分のみ表示
# -i: 大文字小文字を無視
# -E: 拡張正規表現を使用
# パターン: name="csrf_token" の後に value="xxxx" を探し、16進文字列を抜く
# レスポンスヘッダから Location を探す（大小無視）

# grep -i '^Location:' headers_1.txt
# ポイント

# 正規表現を使うと柔軟にパターン指定ができます（-Eで拡張正規表現）。
# パイプと組み合わせて抽出→整形が定番です（例: grep … | sed …）。
# 大文字小文字を無視したいときは -i、部分だけ欲しいときは -o を覚えると便利です。

# 正規表現 で + は「1回以上の繰り返し」を意味します。
# つまり、[a-f0-9]+ は「16進文字が1文字以上続く」ことを表します。

# 正規表現の $ は「末尾」を意味します。
# 正規表現で . は「任意の1文字」を意味します。
# * は「0回以上の繰り返し」を意味します。

# フォームから hidden name="csrf_token" の値を抽出（単純なパターンマッチ）


ORIG_TOKEN=$(grep -oiE 'name="csrf_token"[^>]*value="[a-f0-9]+' "$FORM_FILE" | sed -E 's/.*value="([a-f0-9]+)$/\1/i')
# ORIG_TOKEN抽出後の整形:
# sed -E 's/.*value="([a-f0-9]+)$/\1/i'
# -E: 拡張正規表現
# s/…/…/: 置換コマンド
# 先頭から value="… の直前までを全て削り、
# 括弧でキャプチャした16進のトークン([a-f0-9]+)だけを残す
# \1 はキャプチャ1個目の中身（トークン部分）
# i は大文字小文字無視
# 正規表現のキャプチャ () と、置換側の \1 参照が鍵。





# -z は Bash のテスト演算子で「文字列が空かどうか」を判定します。
if [[ -z "$ORIG_TOKEN" ]]; then
	echo "CSRFトークンの抽出に失敗しました。フォームの hidden name=csrf_token の value を確認してください。" >&2
	exit 1
fi

echo "取得トークン(先頭20桁表示): ${ORIG_TOKEN:0:20}..."

# トークン形式(64桁hex)を満たしたまま1文字変更して不一致にする
MOD_TOKEN="$ORIG_TOKEN"
case "${MOD_TOKEN:0:1}" in
	0) MOD_TOKEN="1${MOD_TOKEN:1}";;
	1) MOD_TOKEN="2${MOD_TOKEN:1}";;
	a) MOD_TOKEN="b${MOD_TOKEN:1}";;
	f) MOD_TOKEN="e${MOD_TOKEN:1}";;
	*) MOD_TOKEN="0${MOD_TOKEN:1}";;
esac

echo "[2/4] 不一致トークンで 15 回 POST して検証します…"

CRITICAL_COUNT=0
HIGH_COUNT=0
MEDIUM_COUNT=0

for i in $(seq 1 15); do
	hdr_file="$HEADERS_DIR/headers_${i}.txt"
	resp_file="$RESP_DIR/resp_${i}.html"

	# ヘッダを保存(-D)し、ボディも保存(-o)。Cookie再利用(-b)。詳細ログ(-sS でエラー時のみ表示)。
	curl -sS -X POST -H "Content-Type: application/x-www-form-urlencoded" \
			 -b "$COOKIES_FILE" \
			 --data "csrf_token=$MOD_TOKEN" \
			 "$POST_TARGET" -D "$hdr_file" -o "$resp_file"

	# ステータスコードと Location を抽出
	STATUS=$(head -n 1 "$hdr_file" | awk '{print $2}')
	LOCATION=$(grep -i '^Location:' "$hdr_file" | awk '{print $2}')

	# 結果表示
	printf "[%02d] HTTP %s\tLocation: %s\n" "$i" "${STATUS:-unknown}" "${LOCATION:-(none)}"

	# 集計（Location により判定）
	if echo "$LOCATION" | grep -qi 'block_page.php'; then
		((CRITICAL_COUNT++))
	elif echo "$LOCATION" | grep -qi 'recaptcha.php'; then
		((HIGH_COUNT++))
	elif echo "$LOCATION" | grep -qi 'error_page.php'; then
		((MEDIUM_COUNT++))
	fi
done

echo "[3/4] 判定サマリ:"
echo "  error_page.php (MEDIUM): $MEDIUM_COUNT 回"
echo "  recaptcha.php (HIGH):   $HIGH_COUNT 回"
echo "  block_page.php (CRIT):  $CRITICAL_COUNT 回"

echo "[4/4] 期待値: 1回目以降 error_page.php、累計3回到達で recaptcha.php、累計10回到達で block_page.php"
echo "ヘッダ詳細は $HEADERS_DIR/、ボディは $RESP_DIR/ を確認してください。"

exit 0
