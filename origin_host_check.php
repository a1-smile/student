<?php


/**
 * origin_host_check.php
 * Originヘッダのチェックを行います。
 * @throw CSRFException 不正なOriginの場合
 * @return void
 */
function origin_host_check(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;

    //  https:// プラス ドメイン名 （アクセス元）
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') return; // Originなしは許容（CSRFトークンで防御）

    // 期待値をサーバ変数から構築

    // スキーム（http または https）
    $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 
              ((!empty($_SERVER['HTTPS']) && 
              $_SERVER['HTTPS'] !== 'off') ? 
              'https' : 'http');

    // ホスト名 ドメイン（アクセス先）
    $host   = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    if ($host === '') {
         throw CSRFException::fromCurrentRequest(
            'Host 不明',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    $expected = $scheme . '://' . $host;

    // 先頭一致（ポート含む場合も許容）
    if (stripos($origin, $expected) !== 0) {
         throw CSRFException::fromCurrentRequest(
            "Origin不一致: origin=$origin expected=$expected",
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
}