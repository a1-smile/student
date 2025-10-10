<?php
//  セッション タイムアウト処理
function handle_session_timeout() {
    $session_timeout = 600; // 10分
    
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        
        if ($inactive_time > $session_timeout) {
            // タイムアウト処理
            $user_name = $_SESSION['user_name'] ?? 'ゲスト'; // ユーザー名を保存（あれば）
            
            // セッションを完全にクリア
            session_unset();
            session_destroy();
            
            // 新しいセッションを開始
            session_start();
            
            // タイムアウト情報を新しいセッションに保存
            $_SESSION['timeout_info'] = [
                'message' => "お疲れ様でした。セッションがタイムアウトしました。",
                'inactive_minutes' => round($inactive_time / 60),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // ログを記録
            error_log("セッションタイムアウト - ユーザー: {$user_name}, 非アクティブ時間: {$inactive_time}秒");
            
            // リダイレクト
            header('Location: index.php');
            exit();
        }
    }
    
    // last_activityが存在しない場合やタイムアウトしていない場合は、現在の時刻を更新
    $_SESSION['last_activity'] = time();
}