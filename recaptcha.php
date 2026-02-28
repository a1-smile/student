<?php
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