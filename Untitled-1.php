<?php
// ...existing code...
function validate_user_agent(): void {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // 空UAは許可するが、監視・警戒フラグを設定
    if ($user_agent === '') {
        error_log("[SECURITY] User-Agentなしアクセス: IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $_SESSION['ua_empty'] = true; // 後続のレート制限や検証で強化用
        // ここでは例外は投げない
    } else {
        // 長さチェック（緩め）
        if (strlen($user_agent) < 5 || strlen($user_agent) > 500) {
            error_log("[SECURITY] 異常なUser-Agent長: " . strlen($user_agent) . " - " . $user_agent);
            throw new InvalidArgumentException('User-Agentの長さが異常です');
        }
        // 攻撃的パターンのみブロック
        $suspicious_patterns = [
            'sqlmap','nikto','nmap','dirb','gobuster',
            '<script','javascript:','python-requests','libwww-perl',
            // 'curl/' は許可（API/テスト用途を想定）。必要ならブロック側に戻す
            'wget/','bot','crawler','spider'
        ];
        foreach ($suspicious_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                error_log("[SECURITY ALERT] 攻撃的User-Agent検出: " . $user_agent);
                throw new InvalidArgumentException('不正なUser-Agentが検出されました');
            }
        }
        $_SESSION['user_agent'] = $user_agent;
        $_SESSION['ua_empty'] = false;
    }
}
// ...existing code...