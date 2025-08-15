<?php
if (file_exists(__DIR__."/common/html_functions.php")) {
    require_once (__DIR__."/common/html_functions.php");
}else {
    die('html_functions.php が見つかりません');
}
if (file_exists(__DIR__."/common/dbmanager.php")) {
    require_once (__DIR__."/common/dbmanager.php");
} else {
    die('dbmanager.php が見つかりません');
}
if (file_exists(__DIR__."/common/data_check.php")) {
    require_once (__DIR__."/common/data_check.php");
} else {
    die('data_check.php が見つかりません');
}
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
            
            // ログを記録（任意）
            error_log("セッションタイムアウト - ユーザー: {$user_name}, 非アクティブ時間: {$inactive_time}秒");
            
            // リダイレクト
            header('Location: index.php');
            exit();
        }
    }
    
    // タイムアウトしていない場合
    $_SESSION['last_activity'] = time();
}

//  function get_error() を定義します。
//  $error は、
//  エラーメッセージを格納する変数です。
//  初期値は空文字列です。
//  GET メソッドのパラメータ 'error' が存在する場合は、
//  $error にその値を格納します。
//  $error を戻り値としてかえします。
function get_error() {
    $error = '';
    if (isset($_SESSION['error'])) {
        $error = $_SESSION['error'];
        //  エスケープ
        $error = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
        unset($_SESSION['error']); //  エラーを表示した後は、セッションから削除します。
    }
    return $error;
}
//  function get_error() 内の
//  $_GET['error'] の値は、
//  post_data.php において、
//  データベースの更新や削除に失敗した場合に
//  エラーメッセージを設定し error 
//  パラメータとしてリダイレクトされます。
//  post_data.php では、
//  check_input 関数で $error を参照渡しして
//  受け取り、エラーメッセージを設定します。
//
//
//  DBManager クラスのインスタンスを作成します。
//  $dbm に代入します。

//  デバッグ用コード
//  DBManager クラスの存在確認とインスタンス作成のテストコードです。
//  このコードは、コメントアウトされています。
//  必要に応じてコメントを外して実行できます。
// echo "DBManagerクラスの存在確認: " . (class_exists('DBManager') ? 'あり' : 'なし') . "<br>";

// try {
//     echo "DBManagerのインスタンス作成を開始...<br>";
//     $dbm = new DBManager();
//     echo "DBManagerのインスタンス作成成功！<br>";
//     echo "\$dbm の型: " . gettype($dbm) . "<br>";
//     echo "\$dbm instanceof DBManager: " . ($dbm instanceof DBManager ? 'true' : 'false') . "<br>";
// } catch (Exception $e) {
//     echo "例外が発生しました: " . $e->getMessage() . "<br>";
//     echo "ファイル: " . $e->getFile() . "<br>";
//     echo "行: " . $e->getLine() . "<br>";
//     error_log('DBManager のインスタンス作成に失敗: ' . $e->getMessage());
//     die('DBManager のインスタンス作成に失敗しました');
// }
// echo "common.php の最後で \$dbm の状態: " . (isset($dbm) ? 'セット済み' : '未セット') . "<br>";
// //  デバッグ用コード終了

try {
    $dbm = new DBManager();
} catch (Exception $e) {
    error_log('DBManager のインスタンス作成に失敗: ' . $e->getMessage());
    die('DBManager のインスタンス作成に失敗しました');
}
?>