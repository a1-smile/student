<?php

//  共通ファイルを読み込みます
$files_required = [
    'common/html_functions.php',
    'common/dbmanager.php',
    'common/data_check.php',
    'user-agent-check.php',
    'ip-check.php',
    'handle-timeout.php',
    'validate-u-a.php',
    'generate-token.php',
    'get-value-from-post-or-get.php',
    'initializeSecureSession.php',
    'exceptions.php',
    'session-reset.php',
    'unset-token.php',
    'validate-csrf-token.php',
    'record-failure.php',
    'get-failure.php',
    'clean-old-logs.php',
    'record-block.php',
    'is-blocked.php',
    'rate-limit-check.php',
    'set-device-id-cookie.php',
    'get-ip-prefix.php'
];

foreach ($files_required as $file_name) {
try {
    $real_path = safe_file_path($file_name);
    require_once $real_path;
} catch (InvalidArgumentException $e) {
    die("引数エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
} catch (LogicException $e) {
    die("ℹ️ ファイルは既に読み込み済みです: " . $e->getMessage() . "<br>");    
} catch (RuntimeException $e) {
    die("ファイル読み込みエラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
} catch (ParseError $e) {
    die("構文エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
} catch (Error $e) {
    die("致命的エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
} catch (Exception $e) {
    die("予期しないエラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
}    
}



//  function get_error() を定義します。
//  $error は、
//  エラーメッセージを格納する変数です。
//  初期値は空文字列です。
//  $_SESSION['error'] が存在する場合は、
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
//  $SESSION['error'] の値は、
//  post_data.php において、
//  データベースの更新や削除に失敗した場合に
//  エラーメッセージを設定し error 
//  パラメータとしてリダイレクトされます。
//  post_data.php では、
//  check_input 関数で $error を参照渡しして
//  受け取り、エラーメッセージを設定します。
//
//
//  DBManager クラスのインスタンスを作成し、
//  $dbm に代入します。

//  デバッグ用コード(コメントアウトしています、必要に応じてコメントを外して実行)
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
//  この段階で $dbm は定義されていることは
//  確認済みです。


