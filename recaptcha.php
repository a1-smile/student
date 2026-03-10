<?php
// redirect 元で
// $_SESSION['redirect_after_captcha'] = $SERVER['REQUEST_URI'];
// header('Location: recaptcha.php');
// exit;


// recaptcha.php 側で、
// session_start();
// if ($captcha_passed) {
//     $_SESSION['recaptcha_passed'] = 1;
//     $redirect = $_SESSION['redirect_after_captcha'] ?? 'index.php';

//     // 外部url へリダイレクトさせない
//     if (strpos($redirect, '/') !== 0) {
//         // strpos() 関数は、
//         // 文字列内で特定の文字列が最初に現れる位置を
//         // 返します。ここでは、
//         // $redirect 変数の先頭に '/' が
//         // あるかどうかを確認しています。
//         // 先頭にある場合は、０が返されます。
//         $redirect = 'index.php';
//     }
//     unset($_SESSION['redirect_after_captcha']);
//     header("Location: $redirect");
// }


// 
// recaptcha.php が通ったら、
// session に
// recaptcha_passed = 1;
// というフラグを立てます。
// そして、rate-limit-check.php で……

/***********************************/

// - recaptcha.php での設定

// recaptcha通過のフラグ
$_SESSION['recaptcha_passed'] = 1;

// recaptcha通過時のIPプレフィックス
$ipv4_blocks = 2; // IPv4なら上位16ビット（/16）をプレフィックスとする例
$ipv6_blocks = 3; // IPv6なら上位48ビット（/48）をプレフィックスとする例
$_SESSION['recaptcha_ip_prefix'] = 
get_ip_prefix_for_session($ipv4_blocks, $ipv6_blocks);

// student\get-ip-prefix.php に get_ip_prefix_for_session()があります。

//  recaptcha 通過の時刻
$_SESSION['recaptcha_passed_at'] = time(); // 現在のタイム

/************************************/

http_response_code(403); // Forbidden アクセス禁止
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <p>reCAPTCHA</p>
</body>
</html>