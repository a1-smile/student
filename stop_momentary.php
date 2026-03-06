<?php
// session_start()します。これにより、セッション変数を使用できるようになります。
session_start();


// セキュアなクッキーを設定します。これにより、クッキーがHTTPS接続でのみ送信されるようになります。
// これにより、セッションIDが安全に保護されます。
session_set_cookie_params([
    'secure' => true, // HTTPS接続でのみクッキーを送信
    'httponly' => true, // JavaScriptからクッキーにアクセスできないようにする
    'samesite' => 'Strict', // クロスサイトリクエストを防止
]);


http_response_code(403); // Forbidden アクセス禁止

// 処理を一時的に停止します。

// リダイレクト元の url を取得します。


// リダイレクトもとにリダイレクトします。
// header('Location: ' . $redirect_url);