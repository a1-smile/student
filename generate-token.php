<?php
/**
 * tokenを生成して
 * セッションに保存する $_SESSION['csrf_token'] 
 * トークンタイムを設定する $_SESSION['csrf_token_time']
 * @return string 生成したトークン
 */
function generate_csrf_token() : string {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    return $token;
}