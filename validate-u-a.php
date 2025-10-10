<?php
/**
 * User-Agentの検証
 * @throws InvalidArgumentException User-Agentが無効な場合
 * @throws RuntimeException システムエラーの場合
 */
function validate_user_agent(): void {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // 1. 完全に空の場合
    if (empty($user_agent)) {
        $context = [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        error_log("[SECURITY] User-Agentなしアクセス: " . json_encode($context));
        throw new InvalidArgumentException('User-Agentが設定されていません');
    }
    
    // 2. 長さチェック
    if (strlen($user_agent) < 10 || strlen($user_agent) > 500) {
        error_log("[SECURITY] 異常なUser-Agent長: " . strlen($user_agent) . " - " . $user_agent);
        throw new InvalidArgumentException('User-Agentの長さが異常です');
    }
    
    // 3. 攻撃的パターンの検出
    $suspicious_patterns = [
        'sqlmap', 'nikto', 'nmap', 'dirb', 'gobuster',
        '<script', 'javascript:', 'python-requests', 'libwww-perl',
        'curl/', 'wget/', 'bot', 'crawler', 'spider'
    ];
    
    foreach ($suspicious_patterns as $pattern) {
        if (stripos($user_agent, $pattern) !== false) {
            error_log("[SECURITY ALERT] 攻撃的User-Agent検出: " . $user_agent);
            throw new InvalidArgumentException('不正なUser-Agentが検出されました');
        }
    }
    
    // 4. 正常な場合はセッションに保存
    $_SESSION['user_agent'] = $user_agent;
}

// //  使用例
// // 現在のコード（249行目付近）を以下に置き換え
// session_start();

// // User-Agentの検証
// try {
//     validate_user_agent();
// } catch (InvalidArgumentException $e) {
//     $attack_id = uniqid('UA_INVALID_');
    
//     error_log("[SECURITY] User-Agent検証失敗 - AttackID: {$attack_id}, " .
//               "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . 
//               ", エラー: " . $e->getMessage());
    
//     session_unset();
//     session_destroy();
//     session_write_close();
    
//     http_response_code(400); // Bad Request
//     die("不正なアクセスです。<br>
//          正常なブラウザからアクセスしてください。<br>
//          攻撃ID: {$attack_id}");
         
// } catch (RuntimeException $e) {
//     $error_id = uniqid('UA_SYSTEM_');
    
//     error_log("[ERROR] User-Agent検証システムエラー - ErrorID: {$error_id}, " .
//               "エラー: " . $e->getMessage());
    
//     http_response_code(500); // Internal Server Error
//     die("システムエラーが発生しました。<br>
//          エラーID: {$error_id}<br>
//          <a href=\"index.php\">再試行する</a>");
// }

