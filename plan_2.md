- 2. php.exe の場所を確認

コマンドプロンプトか PowerShell を開いて、例えば:

php -v

が通るなら、php.exe はパスに通っています。
通らない場合は、XAMPP や PHP 単体を
インストールしたフォルダ
（例: C:\php\php.exe や C:\xampp\php\php.exe）の
フルパスを確認してください。

動作テストとして、手動で一度実行:

"C:\path\to\php.exe" "C:\dev\student\ua-clean-old-logs.php"

エラーが出ずに終了すればOKです

タスクスケジューラの設定

「タスク スケジューラ」を開く
右側で「基本タスクの作成」をクリック
名前: 例「ua-clean-old-logs バッチ」
トリガー: 「毎日」→ 実行時刻を 3:00 に設定
操作: 「プログラムの開始」を選択して次へ
設定:
プログラム/スクリプト:
C:\path\to\php.exe
（例: C:\php\php.exe や C:\xampp\php\php.exe）
引数の追加（オプション）:
ua-clean-old-logs.php
開始（オプション）:
student（空でも動きますが、揃えておくと安心）
「完了」で登録