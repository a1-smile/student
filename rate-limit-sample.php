<?php
<?php
// ...existing code...
require_once __DIR__ . '/lib/rate_limit_repo.php';

$pdo = rl_pdo();

// rate key（現行ロジック準拠）
$rate_key = 'csrf_attack_' . hash('sha256', session_id());

// 設定
$failure_threshold_high   = 10;
$failure_threshold_medium = 3;
$time_window              = 60 * 5;   // 5分
$block_time               = 60 * 15;  // 15分
$current_time             = time();

$former_security_level    = SecurityException::LEVEL_LOW;

// 状態取得（なければ初期化）
$state = rl_get_or_init_state($pdo, $rate_key);
$block_until = (int)$state['block_until'];

try {
    // ブロック中判定（now < block_until）
    if ($current_time < $block_until) {
        // ブロック中アクセスも失敗として記録
        rl_insert_failure($pdo, $rate_key, $current_time);
        $current_security_level = SecurityException::LEVEL_CRITICAL;

        $security_level = max($current_security_level, $former_security_level);
        $remaining_block_time = $block_until - $current_time;

        throw CSRFException::fromCurrentRequest(
            'アクセスが制限されています。' . $remaining_block_time . '秒後に再試行してください',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            $security_level,
            null
        );
    }

    // 直近 time_window の失敗件数を集計
    $since = $current_time - $time_window;
    $failure_count = rl_count_failures_since($pdo, $rate_key, $since);

    if ($failure_count >= $failure_threshold_high) {
        // ブロック開始
        $until = $current_time + $block_time;
        rl_set_block_until($pdo, $rate_key, $until);

        // 今回の失敗も記録（行動ログとして）
        rl_insert_failure($pdo, $rate_key, $current_time);

        $current_security_level = SecurityException::LEVEL_HIGH;
    } elseif ($failure_count >= $failure_threshold_medium) {
        $current_security_level = SecurityException::LEVEL_MEDIUM;
    } else {
        $current_security_level = SecurityException::LEVEL_LOW;
    }

    $security_level = max($current_security_level, $former_security_level);

    if ($security_level >= SecurityException::LEVEL_HIGH) {
        $remaining_block_time = max(0, ($current_time + $block_time) - $current_time);
        throw CSRFException::fromCurrentRequest(
            'アクセスが制限されています。' . $remaining_block_time . '秒後に再試行してください',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            $security_level,
            null
        );
    }

    if ($security_level === SecurityException::LEVEL_MEDIUM) {
        throw CSRFException::fromCurrentRequest(
            '不審なアクセスが検出されました。しばらくしてから再試行してください',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            $security_level,
            null
        );
    }

} catch (CSRFException $e) {
    // 必要ならログやリダイレクト処理
    header('Location: index.php?error=' . urlencode($e->getMessage()));
    exit;
}
// ...existing code...