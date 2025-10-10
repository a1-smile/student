<?php
/**
 * セキュアなセッションを初期化する関数
 * HTTPS環境を自動判定し、適切なセッション設定を行う
 * @return void
 */
function initializeSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    
    // 環境判定
    $isHttps = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    );
    
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,        // 自動判定
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_start();
}