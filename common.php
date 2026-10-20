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
    'get-ip-prefix.php',
    'referer_check.php',
    'content_type_check.php',
    'origin_host_check.php',
    'get_simple_ua.php'
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

//  device_id クッキーをセットします。
set_device_id_cookie();

//  function get_error() を定義します。
//  $error は、
//  エラーメッセージを格納する変数です。
//  初期値は空文字列です。
//  $_SESSION['error'] が存在する場合は、
//  $error にその値を格納します。
//  $error を戻り値としてかえします。
function get_error()
{
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

//  .env ファイルの読み込みと環境変数の検証を行います。

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Dotenv\Exception\InvalidPathException;
use Dotenv\Exception\ValidationException;

try {
    require_once __DIR__ . '/common/get-db-env.php';
} catch (InvalidFileException $e) {
    error_log($e->getMessage());
    exit('.env ファイルの形式が正しくありません。');
} catch (InvalidPathException $e) {
    error_log($e->getMessage());
    exit('.env ファイルが見つかりません');
} catch (ValidationException $e) {
    error_log($e->getMessage());
    exit('必須の環境変数が設定されていません: ');
}

// 環境変数を取得
$host = $_ENV['DB_HOST'];
$name = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$password = $_ENV['DB_PASSWORD'];

//  インスタンス化 しかしまだ PDO 接続はしていない

$dbm = new DBManager(
    $host,
    $name,
    $user,
    $password
);

// 一度 pdo 接続

try {
    //  ここで PDO 接続を試みる場合は、DBManager 内で行われる
    $dbm->connect(); //  DBManager 内で PDO 接続
} catch (PDOException $e) {
    error_log('PDO 接続に失敗: ' . $e->getMessage());
    die('PDO 接続に失敗しました');
}

//  ここまでで $dbm は PDO 接続済みの状態です。

$pdo_connect = $dbm->get_db();

//  切断
$dbm->disconnect(); //  DBManager 内で PDO 接続を切断

//  ここで $pdo_connect は切断前の PDO 接続オブジェクトを保持しています。

$pdo_disconnect = $dbm->get_db();
