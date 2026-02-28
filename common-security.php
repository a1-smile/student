<?php
//  device_id クッキーをセットします。
// set_device_id_cookie();
// が common.php に記述されています。
// 本番環境では、'secure' => true と自動で
// なるように修正しています。
require_once __DIR__ . '/common.php';



//  session が開始されていない場合、開始します。
//  session が開始されている場合は、何もしません。
//  session_start() の前に
//  session_set_cookie_params() を使用して
//  セキュアなクッキーを設定します。

// session_set_cookie_params([
//         'lifetime' => 0,
//         'path'     => '/',
//         'domain'   => '',
//         'secure'   => $isHttps,        // 自動判定
//         'httponly' => true,
//         'samesite' => 'Strict'
//     ]);

initializeSecureSession();

//  session timeout を設定します。
handle_session_timeout();


// 想定外のエラーに備えて、エラーハンドラと例外ハンドラを設定します。
set_exception_handler(function ($e) {
    error_log("未処理の例外: " . $e->getMessage());
    http_response_code(500); // Internal Server Error 内部サーバーエラー
    die("想定していない例外が発生しました。");
    exit;
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHPエラー [$errno]: $errstr in $errfile:$errline");
    http_response_code(500); // Internal Server Error 内部サーバーエラー
    die("想定されていない不具合が発生しました、エラーハンドラー。");
    exit;
});


