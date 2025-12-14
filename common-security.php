<?php
//  device_id クッキーをセットします。
// set_device_id_cookie();
// が common.php に記述されています。
// ただし、本番環境では、'secure' => true にしてください。
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