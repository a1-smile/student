<?php


/*------------------------------------
  device_id クッキー準備（1年有効）
------------------------------------*/
function set_device_id_cookie()
{
if (empty($_COOKIE['device_id'])) {
    $id = bin2hex(random_bytes(16));
    setcookie(
        'device_id', // 名前
        $id, // 値
        [
  'expires' => time() + 86400 * 365,
  'path' => '/',
  'domain' => '',            // 固定ドメインがあるなら明示
  'secure' => false,         // HTTPSならtrue推奨
  'httponly' => true,
  'samesite' => 'Lax'        // 要件に応じて Strict / None(+secure=true)
]);
    $_COOKIE['device_id'] = $id;
}
}