<?php
// redirect 元で
// $_SESSION['redirect_after_captcha'] = $SERVER['REQUEST_URI'];
// header('Location: recaptcha.php');
// exit;


// recaptcha.php 側で、
// session_start();
// if ($captcha_solved) {
//     $_SESSION['recaptcha_solved'] = 1;
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
// recaptcha_solved = true
// というフラグを立てます。
// そして、rate-limit-check.php で


http_response_code(403); // Forbidden アクセス禁止
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <p>reCAPTCHA</p>
</body>
</html>