<?php
/**
 * 改善されたレート制限システム
 * - スライディングウィンドウ: 5分間
 * - 最大記録件数: 各50件
 * - 古いデータの自動削除
 */
function advancedRateLimit(string $ip): array {
    $rate_key = "csrf_attempts_{$ip}";
    $current_time = time();
    
    // 初期化
    if (!isset($_SESSION[$rate_key])) {
        $_SESSION[$rate_key] = [
            'failures' => [],
            'successes' => [],
            'blocked_until' => 0,
            'session_resets' => 0,
            'last_reset_time' => 0
        ];
    }
    
    $data = $_SESSION[$rate_key];
    
    // ✅ 1. 時間ベースのクリーンアップ（5分間）
    $window = 300; // 5分
    $data['failures'] = array_filter($data['failures'], function($time) use ($current_time, $window) {
        return ($current_time - $time) < $window;
    });
    
    $data['successes'] = array_filter($data['successes'], function($time) use ($current_time, $window) {
        return ($current_time - $time) < $window;
    });
    
    // ✅ 2. 件数制限（最大50件）
    $max_records = 50;
    
    if (count($data['failures']) > $max_records) {
        // 古いものから削除（配列の先頭から削除）
        $data['failures'] = array_slice($data['failures'], -$max_records);
    }
    
    if (count($data['successes']) > $max_records) {
        $data['successes'] = array_slice($data['successes'], -$max_records);
    }
    
    // 既存のロジック...
    $_SESSION[$rate_key] = $data;
    return $data;
}