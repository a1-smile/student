<?php


/*------------------------------------
  device_id クッキー準備（1年有効）
------------------------------------*/
function set_device_id_cookie()
{
if (empty($_COOKIE['device_id'])) {
    $id = bin2hex(random_bytes(16));
  // 現在の通信がHTTPSかを判定（プロキシ環境も考慮）
  $isSecure = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
  );
    setcookie(
        'device_id', // 名前
        $id, // 値
        [
  'expires' => time() + 86400 * 365,
  'path' => '/',
  'domain' => '',            // 固定ドメインがあるなら明示

  'secure' => $isSecure,     // 通信がHTTPSのときにのみ送信
  
  'httponly' => true,
  'samesite' => 'Lax'        // 要件に応じて Strict / None(+secure=true)
]);
    $_COOKIE['device_id'] = $id;
}
}