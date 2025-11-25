<?php

//  POST メソッドで送信されたデータを処理するためのスクリプトです。
//  データベースの更新や削除を行い、結果に応じてリダイレクトします。
//  セキュリティー対策として、POST 元で SESSION を開始し、
//  CSRF トークンを生成して、
//  フォームに埋め込み、このファイルで確認します。
//  CSRF トークンは、KEY 'csrf_token' に対応するようにします。

//  ファイルを安全に読み込むための関数を読み込みます。
//  安全なファイルパスを取得する safe_file_path() 関数を取得します。
require_once __DIR__ . '/safe-path.php';
//  共通ファイルを読み込みます。
$file_name = 'common.php';
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

//  セッションを開始します。
initializeSecureSession();
// セッション開始前に安全なクッキー設定しています。
// ただし、開発環境ではHTTPSが使えない場合もあります。
// その場合は、'secure' => false に設定します。
// session_set_cookie_params([
//     'lifetime' => 0,           // ブラウザを閉じるとクッキー削除
//     'path'     => '/',         // サイト全体で有効
//     'domain'   => '',            // 現在のドメインで有効
//     'secure'   => true,          // HTTPSのみクッキーを送信
// //  'secure'   => false,       // 開発環境ではHTTPSが使えない場合はfalseに設定

//     'httponly' => true,        // JSアクセス禁止
//     'samesite' => 'Strict'     // 他サイトからのリクエストでは
// ]);                            // クッキーを送らない  (CSRF対策)

//  以上は、セッション開始の前に設定を行う必要があります

//  session timeout を設定します。
handle_session_timeout();

// 想定外のエラーに備えて、エラーハンドラと例外ハンドラを設定します。
set_exception_handler(function ($e) {
    error_log("未処理の例外: " . $e->getMessage());
    http_response_code(500);
    echo "想定していない例外が発生しました。";
    exit;
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHPエラー [$errno]: $errstr in $errfile:$errline");
    http_response_code(500);
    echo "想定されていない不具合が発生しました。";
    exit;
});




//  ユーザーエージェントをチェックする
try{
    userAgentCheck();
} catch (SessionHijackingException $e) {
    $log_message = $e->getLogMessage();
    error_log($log_message);
    
    //  session 完全廃棄
    session_unset();
    session_destroy();
    session_write_close();
    http_response_code(403);  // Forbidden アクセス禁止
    die('セッションハイジャック攻撃検出<br>
         セキュリティ上の理由により処理を中断します。<br>
         この攻撃は記録され、管理者に通報されました。<br>
         攻撃検出ID: ' . uniqid() . '<br>
         正常な操作を行う場合は、トップページから再開してください。');
} catch (Exception $e) {
    // その他の予期しないエラー
    error_log("予期しないエラー - ユーザーエージェントチェック - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", エラー: " . $e->getMessage());
    
    session_unset();
    session_destroy();
    session_write_close();
    http_response_code(500);  // Internal Server Error 内部サーバーエラー
    die('システムエラーが発生しました。管理者にお問い合わせください。<br>
         エラーID: ' . uniqid() . '<br>
         <a href="index.php">学生一覧に戻る</a>');
}

// IPアドレスの先頭部分をチェックする
// ここでは、IPv4とIPv6の両方に対応した方法を示します。

try {
    $ipv4_blocks = 2;
    $ipv6_blocks = 3;
    ip_check_for_session($ipv4_blocks, $ipv6_blocks);
} catch (SessionHijackingException $e) {
    $log_message = $e->getLogMessage();
    error_log($log_message);
    
    //  session 完全廃棄
    session_unset();
    session_destroy();
    session_write_close();
    http_response_code(403);  // Forbidden アクセス禁止
    die('セッションハイジャック攻撃検出<br>
         セキュリティ上の理由により処理を中断します。<br>
         この攻撃は記録され、管理者に通報されました。<br>
         攻撃検出ID: ' . uniqid() . '<br>
         正常な操作を行う場合は、トップページから再開してください。');
} catch (InvalidArgumentException $e) {
    // 不正なIPアドレス形式
    error_log("不正なIPアドレス - IP:".($_SERVER['REMOTE_ADDR'] ?? 'unknown').", エラー: " . $e->getMessage() . ", UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
    
    session_unset();
    session_destroy();
    session_write_close();
    http_response_code(400);  // Bad Request 不正なリクエスト
    die('不正なアクセスです。サポートされていないネットワーク環境からのアクセスです。');
         
} catch (RuntimeException $e) {
    // システムエラー
    
    $session_id = session_id();
    error_log("IPアドレス処理エラー - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", エラー: " . $e->getMessage() . ", セッションID: " . $session_id);

    session_unset();
    session_destroy();
    session_write_close();
    http_response_code(500);  // Internal Server Error 内部サーバーエラー
    die('システムエラーが発生しました。ネットワーク環境を確認してください。<br>
         エラーID: ' . uniqid() . '<br>
         <a href="index.php">学生一覧に戻る</a>');
         
} catch (Exception $e) {
    // その他の予期しないエラー
    error_log("予期しないエラー - IPアドレス処理 - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", エラー: " . $e->getMessage());
    
    session_unset();
    session_destroy();
    session_write_close();
    http_response_code(500);  // Internal Server Error 内部サーバーエラー
    die('システムエラーが発生しました。管理者にお問い合わせください。<br>
         エラーID: ' . uniqid() . '<br>
         <a href="index.php">学生一覧に戻る</a>');
}
//  以上で
//  共通ファイル読み込み
//  セキュアなクッキー設定
//  セッション開始
//  session timeout 設定
//  想定外のエラーに備えたエラーハンドラ
//  ユーザーエージェントチェック
//  IPアドレスの先頭部分チェック
//  が完了しました。

//  POST メソッドで送信されたかを確認し、
//  token の検証を行います。
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {

//  CSRF トークンの検証を行います。
//  フォームから送信されたトークンとセッションに保存されているトークンを比較します。
        // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        //     die('トークンが一致しません。');
        // }
if (!isset($_POST['csrf_token'])) {
    die('トークンが送信されていません。');
}
if (!isset($_SESSION['csrf_token'])) {
    die('セッションにトークンが保存されていません。');
}
$post_token    = htmlspecialchars($_POST['csrf_token'], ENT_QUOTES, 'UTF-8');

$session_token = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');

if (!hash_equals($post_token, $session_token)) {

    session_unset();
    session_destroy();
    die('トークンが一致しません。');
}
}else {
    //  POST メソッドで送信されていない場合は、
    //  不正なアクセスとして処理を終了します。
    die('通信形式が一致しません。POST メソッドで送信してください。');
}

//  token を使い捨てます。
        
        unset($_SESSION['csrf_token']);
//  
//  POST データを処理するためのコードをここに記述します。


//  また、データベースの更新や削除を行う前に、
//  入力データの検証を行うことが重要です。
//  $_POST['data'] の値に応じて、
//  'update' の場合は学生情報の更新を行い、
//  'delete' の場合は学生情報の削除を行います。
//  'create' の場合は新規学生情報の登録を行います。
//  処理が成功した場合は、
//  学生一覧ページ index.php にリダイレクトし、
//  処理が失敗した場合はエラーメッセージを $error に代入
//  POST もとのページにリダイレクトします。
//  data base を管理するためのクラス DBManager、
//  HTML 関数を定義する html_functions.php、
//  入力チェックを行う data_check.php、
//  エラーメッセージを取得するための get_error() 関数は、

    //  $_POST['data'] の値がセットされているかを確認します。
    // セットされていない場合は、不正なアクセスとして処理を終了します。
    if (!isset($_POST['data'])) {
        die('処理が指定されていません');
    }else {
        $data = $_POST['data'];
    }
    //  $_POST の値を取得します。
        if (isset($_POST["id"])) {
        $id = $_POST["id"];
        } else {
            die('学生 ID が指定されていません');
        }

        if ($data === 'update' or $data === 'create') {
            if (isset($_POST["name"])) {
            $name = $_POST["name"];
            }else {
                die('名前が指定されていません');
            }
            if (isset($_POST["grade"])) {
            $grade = $_POST["grade"];
            }else {
                die('学年が指定されていません');
            }
            if (isset($_POST["old_id"])) {
            $old_id = $_POST["old_id"];
            }else {
                die('更新前の学生 ID が指定されていません');
            }
        }


//  

        //  session にデータを保存します。
        $_SESSION['id']       = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $_SESSION['data']     = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

        if (isset($name)){
            $_SESSION['name']   = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        }
        if (isset($grade)){
            $_SESSION['grade']  = htmlspecialchars($grade, ENT_QUOTES, 'UTF-8');
        }
        if (isset($old_id)){
            $_SESSION['old_id'] = htmlspecialchars($old_id, ENT_QUOTES, 'UTF-8');
        }
        
        

        //  データー挿入処理
        if ($data === 'create') {
            //  check_input() 関数を使用して、
            //  入力データの検証を行います。
            if (check_input($id, $name, $grade, $error) === false) {
                //  入力データの検証に失敗した場合は、
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。 
                //  $error は参照渡しで渡されるため、
                //  check_input() 関数内で
                //  エラーメッセージが設定されます。

        //  $_SESSION にエラーメッセージを保存します。
                $_SESSION['error'] = $error;
        header("Location: student_input.php");
        exit();
                
            }
            //  $dbm インスタンスの if_id_exists() メソッドを使用して、
            //  学生 ID がすでに存在するかを確認します。
            //  common.php で $dbm = new DBManager(); としているので、
            //  $dbm インスタンスを使用します。
            if ($dbm->if_id_exists($id) === true) {
                //  学生 ID がすでに存在する場合は、
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                //  fi_id_exists() メソッドは、
                //  get_student() メソッドを使用しています。
                //  get_student() メソッドは、
                //  student テーブルから学生情報を取得するためのメソッドです。
                //  execute() はレコードが存在しなくても
                //  エラーにはなりません。
                //  fetchALL() は、レコードが存在しない場合は空の配列を返します。
                //  この仕組みを利用して、ID の存在チェックを行います。
                $error = "学生 ID {$id} はすでに存在します";
                $_SESSION['error'] = $error;
                header("Location: student_input.php");
                exit();
            }

            //  学生情報をデータベースに挿入します。
            $result = $dbm->insert_student($id, $name, $grade);
            if ($result === false) {
                //  挿入処理に失敗した場合は、
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $error = "学生情報の登録に失敗しました";
                $_SESSION['error'] = $error;
                header("Location: student_input.php");
                exit();
            }
            //  挿入処理が成功した場合は、
            //  学生一覧ページ index.php にリダイレクトします。
            header("Location: index.php");
            exit();
        } else if ($data === 'update') {
            //  学生情報の更新処理を行います。
            //  check_input() 関数を使用して、
            //  入力データの検証を行います。
            if (check_input($id, $name, $grade, $error) === false) {
                //  入力データの検証に失敗した場合は、
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $_SESSION['error'] = $error;
                header("Location: student_update.php");
                exit();
            }
            //  新しく更新する学生 ID がすでに存在するかを確認します。
            if ($dbm->if_id_exists($id) === true && $id !== $old_id) {
                //  学生 ID がすでに存在する場合は、
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $error = "学生 ID {$id} はすでに存在します";
                $_SESSION['error'] = $error;
                header("Location: student_update.php");
                exit();
            }
            $result = $dbm->update_student($id, $name, $grade, $old_id);
            if ($result === false) {
                //  更新処理に失敗した場合は、  
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $error = "学生情報の更新に失敗しました";
                $_SESSION['error'] = $error;
                header("Location: student_update.php");
                exit();
            }
            //  更新処理が成功した場合は、
            //  学生一覧ページ index.php にリダイレクトします。
            header("Location: index.php");
            exit();
        } else if ($data === 'delete') {
            //  学生情報の削除処理を行います。
            //  $dbm->if_id_exists() メソッドを使用して、
            //  学生 ID が存在するかを確認します。
            if ($dbm->if_id_exists($id) === false) {
                //  学生 ID が存在しない場合は、
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $error = "学生 ID {$id} はデータベースで見つかりません";
                $_SESSION['error'] = $error;// get_error() で取得
                $_SESSION['id'] = $id; //  削除確認ページで使用するため
                //  student_delete.php にリダイレクトします。
                header("Location: student_delete.php");
                exit();
            }
            $result = $dbm->delete_student($id);
            if ($result === false) {
                //  削除処理に失敗した場合は、
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $error = "学生情報の削除に失敗しました";
                $_SESSION['error'] = $error;
                header("Location: student_delete.php");
                exit();
            }
            //  削除処理が成功した場合は、
            //  学生一覧ページ index.php にリダイレクトします。
            header("Location: index.php");
            exit();
        }else {
            //  不正なデータが送信された場合は、
            //  エラーメッセージを $error に代入し、
            //  index.php にリダイレクトします。
            $error = "data が update, delete, create のいずれでもありません";
            $_SESSION['error'] = $error;
            //  index.php にリダイレクトします。
            header("Location: index.php");
            exit();
        }





?>