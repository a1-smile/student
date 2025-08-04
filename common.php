<?php
require_once ("common/html_functions.php");
require_once ("common/DBManager.php");
require_once ("common/data_check.php");

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
$dbm = new DBManager();
?>