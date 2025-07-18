<?php

//  POST メソッドで送信されたデータを処理するためのスクリプトです。
//  データベースの更新や削除を行い、結果に応じてリダイレクトします。
//  セキュリティー対策として、POST 元で SESSION を開始し、
//  CSRF トークンを生成して、
//  フォームに埋め込み、このファイルで確認します。
//  CSRF トークンは、KEY 'csrf_token' に対応するようにします。

//  セキュアなクッキーを使用
    // session_start() の前に設定する必要があります。
// セキュアなクッキーを使用することで、
// セッションIDがHTTPS接続でのみ送信されるようにします。

// セッション開始前に安全なクッキー設定
// ただし、開発環境ではHTTPSが使えない場合もあ。
// その場合は、'secure' => false に設定します。
session_set_cookie_params([
    'lifetime' => 0,           // ブラウザを閉じるとクッキー削除
    'path' => '/',         // サイト全体で有効
    'domain' => '',            // 現在のドメインで有効
    'secure' => true,          // HTTPSのみクッキーを送信
    // 'secure' => false,       // 開発環境ではHTTPSが使えない場合はfalseに設定

    'httponly' => true,        // JSアクセス禁止
    'samesite' => 'Strict'     // 他サイトからのリクエストでは
]);                            // クッキーを送らない  (CSRF対策)

 // 以上は、セッション開始の前に設定を行う必要があります
//  セッションを開始します。
session_start();
//  session timeout を設定します。
//  ここでは、10 分に設定しています。
$session_timeout = 600; // 10分（600秒）
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // セッションの有効期限が切れた場合は
    //  session_regenerate_id(true) を呼び出して
    // セッションIDを再生成します。
    session_regenerate_id(true);
}else {
    // セッションの有効期限が切れていない場合は
    // 最終アクティビティのタイムスタンプを更新します。
    $_SESSION['last_activity'] = time();
}

//  ユーザーエージェントをチェックする
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} elseif ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    // ユーザーエージェントが異なる場合はセッションを破棄
    session_unset();
    session_destroy();
    die('ユーザーエージェント一致しません。');
}


// IPアドレスの先頭部分をチェックする
// ここでは、IPv4とIPv6の両方に対応した方法を示します。
//  ip アドレスがipv4 かipv6をチェック

// 現在のアクセス元IP
$ip = $_SERVER['REMOTE_ADDR'];

// 初回アクセス時は記録しておく
if (!isset($_SESSION['ip_prefix'])) {
    // IPv6かどうかを判定
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // IPv6 → コロン区切りの先頭3ブロックだけ記録（例：2001:0db8:85a3）
        $parts = explode(':', $ip);
        $_SESSION['ip_prefix'] = implode(':', array_slice($parts, 0, 3));
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        // IPv4 → ドット区切りの先頭2ブロックだけ記録（例：192.168）
        $parts = explode('.', $ip);
        $_SESSION['ip_prefix'] = implode('.', array_slice($parts, 0, 2));
    } else {
        die('不正なIPアドレス');
    }
} else {
    // すでに記録済み → 同じ接続元か確認
    $prefix = $_SESSION['ip_prefix'];

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        $current_prefix = implode(':', array_slice($parts, 0, 3));
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $current_prefix = implode('.', array_slice($parts, 0, 2));
    } else {
        die('不正なIPアドレス');
    }

    if ($current_prefix !== $prefix) {
        // IPの接続元が変わった → セッションを破棄
        // 
        session_unset();  //  id が残る。
        session_destroy();  //  destroy だけではデータが残る可能性がある。
        die('セッションハイジャック防止のため再入力が必要です');
    }
}


//  POST メソッドで送信されたかを確認し、
//  そうでない場合は、不正なアクセスとして処理を終了します。
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('通信形式が一致しません');
}

//  CSRF トークンの検証を行います。
//  フォームから送信されたトークンとセッションに保存されているトークンを比較します。
        // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        //     die('トークンが一致しません。');
        // }
if (!isset($_POST['csrf_token'])) {
    die('トークンが送信されていません。');
}
$post_token    = $_POST['csrf_token'] ;
var_dump($post_token);
$session_token = $_SESSION['csrf_token'];
var_dump($session_token);
if ($post_token !== $session_token) {
    
    session_unset();
    die('トークンが一致しません。');
}

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
//  common.php に記述または読み込まれているため、
//  common.php を読み込みます。
    if (file_exists(__DIR__."/common.php")) {
        require_once (__DIR__."/common.php");
    }else {
        die('common.php が見つかりません');
    }
    
    //  $_POST['data'] の値がセットされているかを確認します。
    // セットされていない場合は、不正なアクセスとして処理を終了します。
    if (!isset($_POST['data'])) {
        die('処理が指定されていません');
    }
    //  $_POST の値を取得します。
    $data = $_POST['data'];
        if (isset($_POST["id"])) {
        $id = $_POST["id"];
        }
        if (isset($_POST["name"])) {
        $name = $_POST["name"];
        }
        if (isset($_POST["grade"])) {
        $grade = $_POST["grade"];
        }
        if (isset($_POST["old_id"])) {
        $old_id = $_POST["old_id"];
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

        $token = urlencode($_SESSION['csrf_token']);
        header("Location: student_input.php?error={$error}&csrf_token={$token}");
        exit();
                
            }
            //  $dbm インスタンスの if_id_exists() メソッドを使用して、
            //  学生 ID がすでに存在するかを確認します。
            //  commoon.php で $dbm = new DBManager(); としているので、
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
                header("Location: student_input.php?error={$error}");
                exit();
            }

            //  学生情報をデータベースに挿入します。
            $result = $dbm->insert_student($id, $name, $grade);
            if ($result === false) {
                //  挿入処理に失敗した場合は、
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $error = "学生情報の登録に失敗しました";
                header("Location: student_input.php?error={$error}");
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
                header("Location: student_update.php?error={$error}&id={$old_id}");
                exit();
            }
            //  新しく更新する学生 ID がすでに存在するかを確認します。
            if ($dbm->if_id_exists($id) === true && $id !== $old_id) {
                //  学生 ID がすでに存在する場合は、
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $error = "学生 ID {$id} はすでに存在します";
                header("Location: student_update.php?error={$error}&id={$old_id}");
                exit();
            }
            $result = $dbm->update_student($id, $name, $grade, $old_id);
            if ($result === false) {
                //  更新処理に失敗した場合は、  
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $error = "学生情報の更新に失敗しました";
                header("Location: student_update.php?error={$error}&id={$old_id}");
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
                $error = "学生 ID {$id} は存在しません";
                header("Location: student_delete.php?error={$error}&id={$id}");
                exit();
            }
            $result = $dbm->delete_student($id);
            if ($result === false) {
                //  削除処理に失敗した場合は、
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $error = "学生情報の削除に失敗しました";
                header("Location: student_delete.php?error={$error}&id={$id}");
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
            $error = "不正なデータが送信されました";
            header("Location: index.php?error={$error}");
            exit();
        }





?>