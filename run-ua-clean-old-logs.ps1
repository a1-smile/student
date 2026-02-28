$token = $Env:UA_CLEAN_OLD_LOGS_TOKEN

$uri   = "http://localhost/student/ua-clean-old-logs.php"

Invoke-WebRequest -Uri $uri -Method POST -Body @{ token = $token } -UseBasicParsing

# 1. GUI で設定する方法（おすすめ）

# Win + R → sysdm.cpl と入力 → Enter
# 「詳細設定」タブ → 「環境変数(N)...」をクリック
# 上側の「ユーザー環境変数」欄で「新規(N)...」をクリック
# 変数名: UA_CLEAN_OLD_LOGS_TOKEN
# 変数値: （あなたが決めた長い秘密トークン）
# OK で閉じていく
# 既に開いている PowerShell やタスクスケジューラは一度閉じて開き直す
# PowerShell からは次のように参照できます。

#  $Env:UA_CLEAN_OLD_LOGS_TOKEN