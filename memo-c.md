- curl を使用してuser_agent_check()をテストしたいです。

- 最初に正規ユーザーが1回アクセスした場合（正常ケース）
をvsコードのターミナルの git bash
で実行したいです。

- test用のファイルとして、
送信元ファイル test-index.php
送信先ファイル test-basic-safety.php
を使用します。

- -c 
オプションでクッキーを保存するファイルを指定し、
test-index.phpにアクセスしてセッションを開始します。

- $_SESSION['first_simple_ua']がセットされるはずです。

- その後、-b オプションでクッキーを読み込むファイルを指定し、
test-basic-safety.phpにアクセスしてuser_agent_check()を実行します。

- これにより、同じセッションが維持され、user_agent_check()が正常に動作するはずです。

- error_log にテスト終了と表示されると想定されます。

- 以上の考え方であっていれば、test-1.shにコマンドを記述してください。