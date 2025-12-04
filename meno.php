<?php
// ...existing code...
function validate_csrf_token(): void {
    $token = $_POST['csrf_token'] ?? '';
    // 追加: 受信値とセッション値をログ
    error_log(sprintf(
        "[CSRF VALIDATE] SID=%s POST=%s SESSION=%s",
        session_id(),
        $token,
        $_SESSION['csrf_token'] ?? ''
    ));

    // 形式や期限チェックの失敗理由を個別ログ出力（例）
    if ($token === '' || !preg_match('/^[0-9a-f]{64}$/i', $token)) {
        error_log("[CSRF VALIDATE] format NG");
        throw new CSRFException('token format invalid', SecurityException::SEC_CSRF_ATTACK);
    }
    // ...existing code...
}
// ...existing code...
// ...existing code...
    // トークンの一致確認
    try {
        $post_token = $_POST['csrf_token'] ?? '';
        $session_token = $_SESSION['csrf_token'] ?? '';
        // 追加: デバッグログ
        error_log(sprintf(
            "[CSRF DEBUG] SID=%s POST=%s SESSION=%s COOKIES=%s",
            session_id(),
            $post_token,
            $session_token,
            json_encode($_COOKIE, JSON_UNESCAPED_UNICODE)
        ));

        if (!hash_equals($post_token, $session_token)) {
            throw CSRFException::fromCurrentRequest(
                'CSRFトークンが一致しません - 攻撃の可能性',
                SecurityException::SEC_CSRF_ATTACK,
                [],
                SecurityException::LEVEL_MEDIUM,
                null
            );
        }
    } catch (CSRFException $e) {
        //  失敗として記録
        record_failure($pdo, $ip_key);
        record_failure($pdo, $session_key);
        record_failure($pdo, $device_key);
        record_failure($pdo, $ip_prefix_key);
        //  unset トークン
        unset_token();

        http_response_code(403);
        header('Location: error_page.php');
        exit;
    }
// ...existing code...