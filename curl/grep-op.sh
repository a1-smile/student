# このスクリプトは、curl コマンドを使用して
# 指定したURLにアクセスし、レスポンスから
# CSRFトークンを抽出する例です。

# アクセスするURLは http://localhost/student/index.php です。



# -s は curl の「サイレントモード」オプションです。
# 進捗表示などを抑制して、出力をシンプルにします。 
# -s がないと、進捗表示が混ざってしまい、
# grep での抽出がうまくいかなくなることがあります。
# -A は curl の「User-Agent を指定する」オプションです
# -b は curl の「クッキーをファイルから送信」オプションです。

# -o は grep の「一致部分のみ表示」オプションです。
# この場合は、name="csrf_token" の後に続く 
# value="xxxx" の xxxx 部分を抽出します。
# -P は grep の「Perl互換正規表現を使用」オプションです。
# \K は「ここまでのマッチを破棄して、以降を出力対象にする」機能です。
# つまり、name="csrf_token" の後に続く 
# value="xxxx" の xxxx 部分だけを抽出します。
# \s+ は「空白文字が1回以上続く」ことを意味します。

curl -s -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36" \
  -b curl/cookies.txt http://localhost/student/index.php \
  | grep -oP 'name="csrf_token"\s+value="\K[^"]+'