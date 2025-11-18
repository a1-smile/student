<?php
//rate_limiter

//  rate_key の生成
$rate_key = 'csrf_validate_' . session_id();
// rate_data の初期値を定義
if (!isset($_SESSION['rate_key'])) {
    $_SESSION['rate_key'] = [
    'failure_time_stamp' => [], // 失敗時刻のタイムスタンプ配列
    'block_until' => 0, // ブロック解除時刻
    ];
}

$rate_data = $_SESSION['rate_key'];

//  rate_limiter の設定
$failure_threshold_high = 10; // 高頻度閾値
$failure_threshold_medium = 3; // 中頻度閾値
$time_window = 60*5; // 時間窓（5分）
$block_time = 60*15; // ブロック時間（15分）
$current_time = time();

//$security_level の初期値を設定

//  $rate_data['failure_time_stamp'] から
//  $time_window より古いタイムスタンプを削除
$rate_data['failure_time_stamp'] = array_filter(
    $rate_data['failure_time_stamp'],
    function ($timestamp) use ($current_time, $time_window) {
        return ($timestamp > $current_time - $time_window);
    }
);

//  現在ブロック中か確認
if ($current_time < $rate_data['block_until']) {
    $remaining_block_time = $rate_data['block_until'] - $current_time;
    throw CSRFException::fromCurrentRequest(
        'アクセスが制限されています。'.$remaining_block_time.'秒後に再試行してください',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_HIGH,
        null
    );
}

