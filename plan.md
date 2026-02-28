#Windows PC のPowerShellで毎日3時に自動で
C:dev\student\ファイル名
というファイルを実行する設定をする。

# C:dev\student\ファイル名 の中身
-固定トークンをPowerShellの環境変数に
設定するスクリプトを記述する。

-固定トークンを $_ENV[]に保存する。

-POST送信で ua-clean-old-logs.php
にアクセスする。
この時、固定トークンをPOSTのデーターとして、
送信する。

# ua-clean-old-logs.php の追加機能
- POST送信のみ受け付ける。
- 送信された固定トークンが
環境変数に保存されているものと
POST送信で送られてきたものと
hash_equalsで比較して
一致した場合のみ実行。
一致しない場合は403エラーを返す。

- 実行内容は
古いログファイルを削除する。