<?php
/**
 * content_type_check.php
 * @throw SecurityException 不正なContent-Typeの場合
 * @return void
 */

function content_type_check(): void {
    // POST以外は対象外（早期リターン）
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    // 一部環境では HTTP_CONTENT_TYPE に入ることがあるためフォールバック
    $content_type = $_SERVER['CONTENT_TYPE']
        ?? $_SERVER['HTTP_CONTENT_TYPE']
        ?? '';

    // 先頭一致（パラメータ付き: boundary=... 等を許容）
    $allowed_prefixes = [
        'application/x-www-form-urlencoded',
        'multipart/form-data',
    ];

    $valid = false;
    foreach ($allowed_prefixes as $type) {
        if ($content_type !== '' && stripos($content_type, $type) === 0) {
            $valid = true;
            break;
        }
    }

    if (!$valid) {
         throw CSRFException::fromCurrentRequest(
            '不正なContent-Type: ' . $content_type,
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
}