<?php
//  このファイルの主な内容は、
//  function validate_csrf_token() として
// CSRFトークンの型式チェックを行う関数を定義します。
// filepath: c:\MAMP\htdocs\student\csrf-protect.php
require_once __DIR__ . '/safe-path.php';
require_once __DIR__ . '/common.php';

// セッション開始の保証
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// 設定値を定数化
const CSRF_TOKEN_LENGTH = 64;   // bin2hex(random_bytes(32))
const CSRF_TOKEN_TTL    = 1800; // 秒: 30分

/**
 * function validate_csrf_token()
 * CSRF token の型式チェック
 * @throws CSRFException if invalid token
 */
function validate_csrf_token(): void {
    //  通信メソッドを確認
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    //  sessionにトークンがセットされていなければ
    //  throw CSRFException::fromCurrentRequest(
    //  'CSRFトークンが存在しません');
    if (!isset($_SESSION['csrf_token'])) {
        throw CSRFException::fromCurrentRequest(
            'セッションにCSRFトークンが存在しません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    $session_token = $_SESSION['csrf_token'];

    //  sessionにトークンタイムがセットされていなければ
    //  throw CSRFException::fromCurrentRequest(
    //  'CSRFトークンタイムが存在しません');
    if (!isset($_SESSION['csrf_token_time'])) {
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        throw CSRFException::fromCurrentRequest(
            'CSRFトークンタイムが存在しません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    $token_time = $_SESSION['csrf_token_time'];
    //  トークンタイムが30分以上前なら
    //  throw CSRFException::fromCurrentRequest(
    //  'CSRFトークンの有効期限が切れています');
    if (time() - $token_time > CSRF_TOKEN_TTL) {
        throw CSRFException::fromCurrentRequest(
            'CSRFトークンの有効期限が切れています',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    //  POSTされたトークンがあるか確認
    if (!isset($_POST['csrf_token'])) {
        throw CSRFException::fromCurrentRequest(
            'POSTのCSRFトークンが存在しません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    $post_token = $_POST['csrf_token'];

    //  token が文字列か確認
    //  random_bytes()はバイナリデータで
    //  bin2hex()で16進数文字列に変換される
    if (!is_string($post_token)) {
        $message = 'POSTされたCSRFトークンが文字列ではありません';
        throw CSRFException::fromCurrentRequest(
            $message,
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    if (!is_string($session_token)) {
        throw CSRFException::fromCurrentRequest(
            'セッションのCSRFトークンが文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    //  token の長さを確認
    //  bin2hex(random_bytes(32)) は64文字の16進数文字列
    //  bin2hex()で64文字になるのは
    //  32バイトのバイナリデータを16進数に変換するため
    //  64文字でなければ不正
    //  strlen()はバイト数を返す
    if (strlen($post_token) !== CSRF_TOKEN_LENGTH) {
        throw CSRFException::fromCurrentRequest(
            'ポストCSRFトークンの長さが不正です',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    if (strlen($session_token) !== CSRF_TOKEN_LENGTH) {
        throw CSRFException::fromCurrentRequest(
            'セッションのCSRFトークンの長さが不正です',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    //  ctype_xdigit()で16進数文字列か確認
    if (!ctype_xdigit($post_token) ) {
        throw CSRFException::fromCurrentRequest(
            'POSTされたCSRFトークンが16進数文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    if (!ctype_xdigit($session_token) ) {
        throw CSRFException::fromCurrentRequest(
            'セッションのCSRFトークンが16進数文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

}

//  csrf token を hash equals で比較
$post_token    = $_POST['csrf_token'];
$session_token = $_SESSION['csrf_token'];
$result        = hash_equals($session_token, $post_token);
if (!$result) {
    throw CSRFException::fromCurrentRequest(
        'CSRFトークンが一致しません',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_MEDIUM,
        null
    );
} else {
    // トークンが一致した場合の後処理
    cleanupAfterSuccess($post_token);
}