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
        time() + 86400 * 365, // 有効期限（1年後）
        "/", // パス :サイト内全域で有効
        "", // ドメイン :指定なしで現在のドメイン
        false, // HTTPS限定か？==> false
        true //  JavaScriptからアクセス不可==> true
    );
    $_COOKIE['device_id'] = $id;
}
}