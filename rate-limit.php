<?php
//
// rate_limiter

//  rate key を hash 化
 $rate_key_id = 'csrf_attack' . hash('sha256', session_id());
 $rate_key  = $rate_key_id;

// rate_data の初期値を定義
if (!isset($_SESSION[$rate_key])) {
    $_SESSION[$rate_key] = [
        'failure_time_stamp' => [], // 失敗時刻のタイムスタンプ配列
        'block_until' => 0, // ブロック解除時刻
    ];
}

$rate_data = $_SESSION[$rate_key];

//  閾値の設定
$failure_threshold_high = 10; // 高頻度閾値
$failure_threshold_medium = 3; // 中頻度閾値
$max_failure_time_stamp = 20; // 最大失敗時刻数
$time_window = 60*5; // 時間窓（5分）
$block_time = 60*15; // ブロック時間（15分）
$current_time = time();

//$security_level の初期値を設定
$former_security_level = SecurityException::LEVEL_LOW; // 1



try{

//  $rate_data['failure_time_stamp'] から
//  $time_window より古いタイムスタンプを削除
$rate_data['failure_time_stamp'] = array_filter(
    $rate_data['failure_time_stamp'],
    function ($timestamp) use ($current_time, $time_window) {
        return ($timestamp > $current_time - $time_window);
    }
);
//  現在ブロック中か確認
if ($current_time - $rate_data['block_until'] > 0) {
    $remaining_block_time = $rate_data['block_until'] - $current_time;

    $current_security_level = SecurityException::LEVEL_CRITICAL;

    // ブロック中にアクセスがあるときは失敗時刻を追加
    $rate_data['failure_time_stamp'][]= $current_time;

    //  失敗時刻の数が最大値を超えた場合は古いものを削除
    if (count($rate_data['failure_time_stamp']) > $max_failure_time_stamp) {
        $rate_data['failure_time_stamp'] = array_slice(
            $rate_data['failure_time_stamp'],
            -$max_failure_time_stamp
        );
    }

    if ($current_security_level > $former_security_level) {
        $security_level = $current_security_level;
    } else {
        $security_level = $former_security_level;
    }

}

//  $rate_data['failure_time_stamp'] の数を求める
$failure_count = count($rate_data['failure_time_stamp']);

//  $failure_count が $failure_threshold_high 以上の場合はブロック
if ($failure_count >= $failure_threshold_high) {
    // ブロック解除時刻を設定
    $rate_data['block_until'] = $current_time + $block_time;
    $current_security_level = SecurityException::LEVEL_HIGH;

    //  失敗時刻を追加
    $rate_data['failure_time_stamp'][]= $current_time;
    //  失敗時刻の数が最大値を超えた場合は古いものを削除
    if (count($rate_data['failure_time_stamp']) > $max_failure_time_stamp) {
        $rate_data['failure_time_stamp'] = array_slice(
            $rate_data['failure_time_stamp'],
            -$max_failure_time_stamp
        );
    }
    
} elseif ($failure_count >= $failure_threshold_medium) {
    // 中頻度閾値を超えた場合の処理
    $current_security_level = SecurityException::LEVEL_MEDIUM;

    
} else {
    // 低頻度の場合の処理
    $current_security_level = SecurityException::LEVEL_LOW;
}

if ($current_security_level > $former_security_level) {
        $security_level = $current_security_level;
    } else {
        $security_level = $former_security_level;
    }


$former_security_level = $security_level;

$_SESSION[$rate_key] = $rate_data;

//  security_level が LEVEL_HIGH 以上の場合は例外をスロー
if ($security_level >= SecurityException::LEVEL_HIGH) {
    $remaining_block_time = $rate_data['block_until'] - $current_time;
    throw CSRFException::fromCurrentRequest(
        'アクセスが制限されています。'.$remaining_block_time.'秒後に再試行してください',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        $security_level,
        null
    );
}

//  security_level が LEVEL_MEDIUM の場合も例外をスロー
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
    //  security_level を取得
    $security_level = $e->getSecurityLevel();
    //  $_SESSION に保存されている rate_data を一時保管
    $stored_rate_data = $_SESSION[$rate_key];

    session_reset();

    $_SESSION[$rate_key] = $stored_rate_data;

    //  index.php にリダイレクト
    header('Location: index.php?error=' . urlencode($e->getMessage()));
    exit;
}
// rate_limiter ここまで
