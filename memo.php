<?php
// ...existing code...
function cleanupAfterFailure(string $failed_token): void {
    
    // 失敗したトークンも使用済みとして記録（再利用防止）
    // 型チェックを追加して文字列の場合のみ処理
    if (!empty($failed_token) && is_string($failed_token)) {
        if (!isset($_SESSION['used_csrf_tokens']) || !is_array($_SESSION['used_csrf_tokens'])) {
            $_SESSION['used_csrf_tokens'] = [];
        }

        $now = time();

        // 連想配列: token => timestamp に統一
        // 既存キーがある場合は一旦外して末尾に入れ直し（挿入順維持）
        if (array_key_exists($failed_token, $_SESSION['used_csrf_tokens'])) {
            unset($_SESSION['used_csrf_tokens'][$failed_token]);
        }
        $_SESSION['used_csrf_tokens'][$failed_token] = $now;

        // 履歴上限 10 件までに制限（古いものから削除）
        while (count($_SESSION['used_csrf_tokens']) > 10) {
            $oldest = function_exists('array_key_first')
                ? array_key_first($_SESSION['used_csrf_tokens'])
                : (count($_SESSION['used_csrf_tokens']) ? array_keys($_SESSION['used_csrf_tokens'])[0] : null);
            if ($oldest !== null) {
                unset($_SESSION['used_csrf_tokens'][$oldest]);
            } else {
                break;
            }
        }
    } else {
        error_log("無効なトークン形式のため、使用済みトークンリストに追加しませんでした。");
    }
     
    // トークンを削除
    if (isset($_SESSION['csrf_token'])) {
        unset($_SESSION['csrf_token']);
    }
    if (isset($_SESSION['csrf_token_time'])) {
        unset($_SESSION['csrf_token_time']);
    }
    if (isset($_POST['csrf_token'])) {
        unset($_POST['csrf_token']);
    }
}
// ...existing code...


//  修正例3


// ...existing code...
    if (!isset($_SESSION['used_csrf_tokens'])) {
        $_SESSION['used_csrf_tokens'] = [];
    }
    // 旧形式（配列の値がトークン）と新形式（token => timestamp）を両対応でチェック
    $used = $_SESSION['used_csrf_tokens'];
    $is_reused = (is_array($used) && (isset($used[$post_token]) || in_array($post_token, $used, true)));
    if ($is_reused) {
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        $rate_data = recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            '使用済みトークンの再利用',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
// ...existing code...
// 
// 


// 修正例２
// ...existing code...
function cleanupAfterSuccess(string $used_token): void {
    // 使用済みトークンとして記録（token => timestamp）
    if (!isset($_SESSION['used_csrf_tokens']) || !is_array($_SESSION['used_csrf_tokens'])) {
        $_SESSION['used_csrf_tokens'] = [];
    }

    $now = time();
    if (array_key_exists($used_token, $_SESSION['used_csrf_tokens'])) {
        unset($_SESSION['used_csrf_tokens'][$used_token]);
    }
    $_SESSION['used_csrf_tokens'][$used_token] = $now;

    while (count($_SESSION['used_csrf_tokens']) > 10) {
        $oldest = function_exists('array_key_first')
            ? array_key_first($_SESSION['used_csrf_tokens'])
            : (count($_SESSION['used_csrf_tokens']) ? array_keys($_SESSION['used_csrf_tokens'])[0] : null);
        if ($oldest !== null) {
            unset($_SESSION['used_csrf_tokens'][$oldest]);
        } else {
            break;
        }
    }
    
    // トークンを削除
    if (isset($_SESSION['csrf_token'])) {
        unset($_SESSION['csrf_token']);
    }
    
    if (isset($_SESSION['csrf_token_time'])) {
        unset($_SESSION['csrf_token_time']);
    }
    if (isset($_POST['csrf_token'])) {
        unset($_POST['csrf_token']);
    }
}
// ...existing code...