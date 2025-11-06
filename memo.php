<?php
/**
 * セッション破棄前にセキュリティデータを保存
 * @param string $ip クライアントのIPアドレス
 * @return array $preserved_data 保存されたセキュリティデータ
 * レート制限データに加えて、使用済みトークンやセキュリティイベントも保存
 */
function preserveSecurityData(string $ip): array {
    $rate_key = "csrf_attempts_{$ip}";

    // レート制限データを取得
    $rate_data = $_SESSION[$rate_key] ?? [
        'failures' => [],
        'successes' => [],
        'blocked_until' => 0,
        'session_resets' => 0,
        'last_reset_time' => 0
    ];

    // 追加のセキュリティデータも保存
    $preserved_data = [
        'rate_data' => $rate_data,
        'used_csrf_tokens' => $_SESSION['used_csrf_tokens'] ?? [],
        'security_events' => $_SESSION['security_events'] ?? [],
        'preservation_timestamp' => time(),
        'preserved_from_ip' => $ip
    ];

    return $preserved_data;
}

/**
 * 新しいセッションにセキュリティデータを復元
 * @param string $ip クライアントのIPアドレス
 * @param array $preserved_data 復元するセキュリティデータ
 * @return array $rate_data 更新されたレート制限データ
 */
function restoreSecurityData(string $ip, array $preserved_data): array {
    $rate_key = "csrf_attempts_{$ip}";
    
    // レート制限データを復元
    if (isset($preserved_data['rate_data'])) {
        $_SESSION[$rate_key] = $preserved_data['rate_data'];
    }
    
    // 使用済みトークンを復元
    if (isset($preserved_data['used_csrf_tokens'])) {
        $_SESSION['used_csrf_tokens'] = $preserved_data['used_csrf_tokens'];
    }
    
    // セキュリティイベントを復元（既存データがあれば統合）
    if (isset($preserved_data['security_events'])) {
        $_SESSION['security_events'] = $preserved_data['security_events'];
    }
    
    // セッション破棄の記録も追加
    $_SESSION['security_events'] = array_merge(
        $_SESSION['security_events'] ?? [],
        [
            'last_session_destroy' => time(),
            'destroy_reason' => 'critical_csrf_attack',
            'destroy_ip' => $ip,
            'restoration_timestamp' => time(),
            'preservation_timestamp' => $preserved_data['preservation_timestamp'] ?? 0
        ]
    );
    
    $rate_data = $_SESSION[$rate_key] ?? [];
    return $rate_data;
}