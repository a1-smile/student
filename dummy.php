<?php
function handleCSRFAttack(CSRFException $e, string $severity, string $ip): void {
    switch ($severity) {
        case 'critical':
            // ✅ セッション破棄前の準備
            $preserved_data = preserveSecurityData($ip);
            
            // セッション完全破棄
            recordSessionReset($ip);
            session_destroy();
            session_start();
            
            // ✅ セキュリティデータを新セッションに復元
            restoreSecurityData($ip, $preserved_data);
            
            error_log("重大CSRF攻撃検知 - IP: {$ip}, セッション破棄実行");
            break;
            
        case 'high':
            recordSessionReset($ip);
            cleanupAfterFailure($_POST['csrf_token'] ?? '');
            session_regenerate_id(true);
            
            error_log("高リスクCSRF攻撃 - IP: {$ip}, セッション再生成");
            break;
            
        case 'low':
        default:
            cleanupAfterFailure($_POST['csrf_token'] ?? '');
            error_log("CSRF攻撃検知 - IP: {$ip}, セッション再生成");
            break;
    }
}

/**
 * セッション破棄前にセキュリティデータを保存
 */
function preserveSecurityData(string $ip): array {
    $rate_key = "csrf_attempts_{$ip}";
    
    $data = $_SESSION[$rate_key] ?? [
        'failures' => [],
        'successes' => [],
        'blocked_until' => 0,
        'session_resets' => 0,
        'last_reset_time' => 0
    ];
    
    // 重大攻撃の記録
    $data['failures'][] = time();
    $data['session_resets']++;
    $data['last_reset_time'] = time();
    $data['blocked_until'] = time() + 3600; // 1時間ブロック
    
    return $data;
}

/**
 * 新しいセッションにセキュリティデータを復元
 */
function restoreSecurityData(string $ip, array $preserved_data): void {
    $rate_key = "csrf_attempts_{$ip}";
    $_SESSION[$rate_key] = $preserved_data;
    
    // セッション破棄の記録も追加
    $_SESSION['security_events'] = [
        'last_session_destroy' => time(),
        'destroy_reason' => 'critical_csrf_attack',
        'destroy_ip' => $ip
    ];
}