# -I は curl の「HTTPヘッダーのみ取得」オプションです。
# -A は curl の「User-Agent を指定する」オプションです。
# 送信するリクエストヘッダーの User-Agent を任意文字列に設定します。
# ユーザーエージェントチェックで curl を禁止しているので、
# User-Agent を設定してアクセスします。
curl -I -A "Mozilla/5.0" http://localhost/student/index.php