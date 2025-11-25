<?php
/**
 * 4層レート制限チェック
 * @param string $ip_key
 * @param string $session_key
 * @param string $device_key
 * @param string $ip_prefix_key
 * @param PDO $pdo
 * @throws CSRFException レート制限超過時
 */

function rate_limit_check(
    PDO $pdo,
    string $ip_key, 
    string $session_key,
    string $device_key,
    string $ip_prefix_key): void {
/*------------------------------------
  事前ブロック確認（session / device）
------------------------------------*/
if (is_blocked($pdo, $session_key)) {
    throw CSRFException::fromCurrentRequest(
        'セッションが一時的にブロックされています',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_CRITICAL,
        null
    );
}
if (is_blocked($pdo, $device_key)) {
    throw CSRFException::fromCurrentRequest(
        'デバイスが一時的にブロックされています',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_CRITICAL,
        null
    );
}

/*------------------------------------
  4層レート制限の実施
------------------------------------*/

// 第1層：IP（5分で100回）
//  5分間の失敗タイムスタンプを配列で取得
$failure_array = get_failures($pdo, $ip_key, RATE_LIMITS['ip']['window']);
//  失敗回数をカウント
$failure_count = count($failure_array);
if ($failure_count 
    >=
    RATE_LIMITS['ip']['max_failures']) {
    throw CSRFException::fromCurrentRequest(
        'IPアドレスからのリクエストが多すぎます',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_CRITICAL,
        null
    );
}elseif ($failure_count 
    >=
    RATE_LIMITS['ip']['soft_failure']) {
    throw CSRFException::fromCurrentRequest(
        'IPアドレスからのリクエストが多めです',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_HIGH,
        null
    );
    }

// 第2層：セッションID（5分で10回） ← 本命
//  5分間の失敗タイムスタンプを配列で取得
$failure_array = get_failures($pdo, $session_key, RATE_LIMITS['session']['window']);
//  失敗回数をカウント
$failure_count = count($failure_array);
if ($failure_count 
    >=
    RATE_LIMITS['session']['max_failures']) {
           // 閾値超え → ブロック登録
    record_block($pdo, $session_key, BLOCK_DURATION_SESSION);
    throw CSRFException::fromCurrentRequest(
        'セッションからのリクエストが多すぎます',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_CRITICAL,
        null
    );
}elseif ($failure_count 
        >=
        RATE_LIMITS['session']['soft_failure']) {
    throw CSRFException::fromCurrentRequest(
        'セッションからのリクエストが多めです',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_HIGH,
        null
    );
    }

// 第3層：device_id（5分で30回）
//  5分間の失敗タイムスタンプを配列で取得
$failure_array = get_failures($pdo, $device_key, RATE_LIMITS['device']['window']);
//  失敗回数をカウント
$failure_count = count($failure_array);
if ($failure_count 
    >=
    RATE_LIMITS['device']['max_failures']) {
            // 閾値超え → ブロック登録
    record_block($pdo, $device_key, BLOCK_DURATION_DEVICE);
        throw CSRFException::fromCurrentRequest(
            'デバイスからのリクエストが多すぎます',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_CRITICAL,
            null
        );
    }elseif ($failure_count 
    >=
    RATE_LIMITS['device']['soft_failure']) {
        throw CSRFException::fromCurrentRequest(
            'デバイスからのリクエストが多めです',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_HIGH,
            null
        );
    }
    
    // 第4層：IPプレフィックス（5分で1000回）
    //  5分間の失敗タイムスタンプを配列で取得
    $failure_array = get_failures($pdo, $ip_prefix_key, RATE_LIMITS['ip_prefix']['window']);
    //  失敗回数をカウント
    $failure_count = count($failure_array);
    if ($failure_count 
    >=
    RATE_LIMITS['ip_prefix']['max_failures']) {
        throw CSRFException::fromCurrentRequest(
            'IPプレフィックスからのリクエストが多すぎます',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_CRITICAL,
            null
        );
    }elseif ($failure_count 
    >=
    RATE_LIMITS['ip_prefix']['soft_failure']) {
        throw CSRFException::fromCurrentRequest(
            'IPプレフィックスからのリクエストが多めです',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_HIGH,
            null
        );
    }
}
