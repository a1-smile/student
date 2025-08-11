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
        //  サニタイズ
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
echo "DBManagerクラスの存在確認: " . (class_exists('DBManager') ? 'あり' : 'なし') . "<br>";

try {
    echo "DBManagerのインスタンス作成を開始...<br>";
    $dbm = new DBManager();
    echo "DBManagerのインスタンス作成成功！<br>";
    echo "\$dbm の型: " . gettype($dbm) . "<br>";
    echo "\$dbm instanceof DBManager: " . ($dbm instanceof DBManager ? 'true' : 'false') . "<br>";
} catch (Exception $e) {
    echo "例外が発生しました: " . $e->getMessage() . "<br>";
    echo "ファイル: " . $e->getFile() . "<br>";
    echo "行: " . $e->getLine() . "<br>";
    error_log('DBManager のインスタンス作成に失敗: ' . $e->getMessage());
    die('DBManager のインスタンス作成に失敗しました');
}
echo "common.php の最後で \$dbm の状態: " . (isset($dbm) ? 'セット済み' : '未セット') . "<br>";
//  デバッグ用コード終了

try {
    $dbm = new DBManager();
} catch (Exception $e) {
    error_log('DBManager のインスタンス作成に失敗: ' . $e->getMessage());
    die('DBManager のインスタンス作成に失敗しました');
}
?>