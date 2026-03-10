<?php
/**
 * User-Agentの検証
 * @throws InvalidArgumentException User-Agentが無効な場合
 * @throws RuntimeException システムエラーの場合
 */
function validate_user_agent(): void {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // 1. 空（警告のみ。許容）
    if ($user_agent === '') {
        error_log("[SECURITY] UAなし: " . json_encode([
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            'len' => 0,
            'stamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE));
        // 許容継続（例外は投げない）
    }

    // 2. 長さチェック（短すぎ／長すぎ）
    $len = strlen($user_agent);
    if ($len < 10 || $len > 500) {
        error_log(sprintf(
            "[SECURITY] 異常UA長: len=%d, hash=%s",
            $len,
            hash('sha256', $user_agent)
        ));
        throw new InvalidArgumentException('User-Agentの長さが異常です', 4001);
    }

    // 3. 攻撃的パターンの検出
    $suspicious = [
        'sqlmap','nikto','nmap','dirb','gobuster',
        '<script','javascript:','python-requests','libwww-perl',
        'curl/','wget/','bot','crawler','spider'
    ];
    foreach ($suspicious as $p) {
        if (stripos($user_agent, $p) !== false) {
            error_log(sprintf(
                "[SECURITY ALERT] 攻撃的UA検出: hash=%s, pattern=%s",
                hash('sha256', $user_agent),
                $p
            ));
            // 運用方針で 403 か 429 を選択
            // ここでは 403 をデフォルトに
            throw new InvalidArgumentException('不正なUser-Agentが検出されました', 4031);
        }
    }



}

