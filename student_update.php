<?php
    // セッション開始前に安全なクッキー設定
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
    // 以上は、セッション開始の前に設定を行う必要があります
                  

//  ファイルを安全に読み込むための関数定義したファイルを読み込みます。
//  安全なファイルパスを取得する safe_file_path() 関数が定義されています。
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
//  すでにセッションが始まっている場合は何もしません。
initializeSecureSession();

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
//  セッション開始確認
//  session timeout 設定
//  想定外のエラーに備えたエラーハンドラ
//  ユーザーエージェントチェック
//  IPアドレスの先頭部分チェック
//  が完了しました。


    //  通信形式を取得します
    $method = $_SERVER['REQUEST_METHOD'];
    
    
    //  POST か GET のどちらかで処理を分岐します。
    //  POST の場合は、student_edit.php からのPOSTでの遷移を想定しています。
    //  GET の場合は、post_data.php からのGETでの遷移を想定しています。
    
    if ($method === 'POST'){
        //  student_edit.php からのPOSTでの遷移を想定しています
        update_post($dbm);
    }elseif ($method === 'GET') {
        //  post_data.php からのGETでの遷移を想定しています
        update_get($dbm);
    }else {
        //  POST GET のどちらでもない場合は
        //  不正なアクセスとして処理を終了します。
        session_unset();  //  id が残る。
        session_destroy();  //  destroy だけではデータが残る可能性がある。
        die('POST でも GET でもない不正なアクセスです');
    }  
    

    function update_post($dbm) {
        //  POST メソッドで送信される場合は、
        //  student_edit.php からの遷移を想定しています。
        
        //  POST データが送信されているかを確認します。
        if (!isset($_POST['id']) || !isset($_POST['data']) || !isset($_POST['csrf_token'])) {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('POST データが送信されていません');
        }
        //  POST からデータを取得します。
        $old_id  = $_POST['id'];
        $data    = $_POST['data'];
        $token_p = $_POST['csrf_token'];
        //  $id, $data, $token の値をサニタイズします。
        $old_id  = htmlspecialchars($old_id, ENT_QUOTES, 'UTF-8');
        $data    = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        $token_p = htmlspecialchars($token_p, ENT_QUOTES, 'UTF-8');

        //  SESSION からトークンを取得します。
        if (!isset($_SESSION['csrf_token'])) {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('SESSION からトークンが取得できません');
        }else {
            $token_s = $_SESSION['csrf_token'];
        }
        //  トークンが一致するかを確認します。
        if ($token_p !== $token_s) {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('トークンが一致しません');
        }


        //  $old_id に対応する学生情報をデータベースから取得します。
        $member = $dbm->get_student($old_id);
        //  学生情報が存在しない場合は、エラーメッセージを表示します。
        if ($member === []) {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('指定された学生番号は存在しません');
        }
        //  $member から学生情報を取得します。
        $id    = $member['id'];
        $name  = $member['name'];
        $grade = $member['grade'];
        //  サニタイズ
        $id    = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $grade = htmlspecialchars($grade, ENT_QUOTES, 'UTF-8');

        //  HTML で h1 タグを表示します。
        show_top('学生情報の更新');
        //  学生情報の更新フォームを表示します。
        show_update($id, $name, $grade, $old_id, $token_p);
        //  「学生情報一覧へ戻る」を表示します。
        show_bottom(true);
    }

    function update_get() {
        //  GET メソッドで送信される場合は、
        //  post_data.php からの遷移を想定しています。
        //  SESSION からデータを取得します。
        if (isset($_SESSION['token_re'])) {
            $token_re = $_SESSION['token_re'];
        } else {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('SESSION から$token_reが取得できません');
        }

        if (isset($_SESSION['id'])) {
            $id = $_SESSION['id'];
        } else {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('SESSION から$idが取得できません');
        }

        if (isset($_SESSION['grade'])) {
            $grade = $_SESSION['grade'];
        } else {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('SESSION から$gradeが取得できません');
        }

        if (isset($_SESSION['name'])) {
            $name = $_SESSION['name'];
        } else {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('SESSION から$nameが取得できません');
        }

        if (isset($_SESSION['old_id'])) {
            $old_id = $_SESSION['old_id'];
        } else {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('SESSION から$old_idが取得できません');
        }

        if (isset($_SESSION['csrf_token'])) {
            $token_s = $_SESSION['csrf_token'];
        } else {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('SESSION から$token_sが取得できません');
        }

        //  XSS 対策
        $token_re  = htmlspecialchars($token_re, ENT_QUOTES, 'UTF-8');
        $id        = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $name      = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $grade     = htmlspecialchars($grade, ENT_QUOTES, 'UTF-8');
        $old_id    = htmlspecialchars($old_id, ENT_QUOTES, 'UTF-8');
        $token_s   = htmlspecialchars($token_s, ENT_QUOTES, 'UTF-8');

        //  CSRF トークンのチェック
        if ($token_re !== $token_s) {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('CSRF トークンが一致しません');
        }
        //  共通の関数を読み込みます。
        if (file_exists(__DIR__."/common.php")) {
            require_once (__DIR__."/common.php");
        } else {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('common.php が見つかりません');
        }
        //  HTML で h1 タグを表示します。
        show_top('更新情報を再入力してください。');
        //  学生情報の更新フォームを表示します。
        show_update($id, $name, $grade, $old_id, $token_re);
        //  「学生情報一覧へ戻る」を表示します。
        show_bottom(true);
    }



//         if (!isset($_SESSION['csrf_token'])) {
//             session_unset();  //  id が残る。
//             session_destroy();  //  destroy だけではデータが残る可能性がある。
//             die('POST で、CSRF トークンが設定されていません');
//         }
//         if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
//             session_unset();  //  id が残る。
//             session_destroy();
//             die('POST で、CSRF トークンが一致しません');
//         } 
    

//     if ($method === 'GET') {
//         //  GET メソッドで送信される場合は、
//         //  post_data.php からの遷移を想定しています。
//         //  post_data.php では、
//         //  $_SESSION['re_token'] にトークンを保存しています。
//         if (!isset($_SESSION['token_re'])) {
//             session_unset();  //  id が残る。
//             session_destroy();  //  destroy だけではデータが残る可能性がある。
//             die('安全対策のため、学生情報一覧画面から再操作を行ってください');
//         }
//     }
        
    
//     //  共通の関数を読み込みます。
//     //  common.php が存在するかを確認します。
//     //  存在しない場合は、エラーメッセージを表示します。
//         if (file_exists(__DIR__."/common.php")) {
//         require_once (__DIR__."/common.php");
//     }else {
//         die('common.php が見つかりません');
//     }

//     //  $old_id に $_POST['id'] の値をセットします。
//     //  $_POST['id'] が設定されていない場合は、null をセットします。
//     $old_id = $_POST['id'] ?? null;
//     if ($old_id === null) {
//         die('不正なアクセス');
//     }
//     //  POST で送信される値は、$id と トークン、update です。
//     //  学生情報を取得します。
//     $member = $dbm->get_student($old_id);
//     //  $member は連想配列の形で学生情報が格納されます。
//     // 学生情報が存在しない場合は、$member は [] となります。
//     if (empty($member)) {
//         die('指定された学生情報が存在しません');
//     }
//     $id    = $member['id'];
//     $name  = $member['name'];
//     $grade = $member['grade'];

//     //  h1 タグで学生情報の更新を表示します。
//     show_top('学生情報の更新');
//     //  学生情報の更新フォームを表示します。
//     show_update($id, $name, $grade, $old_id, $token);
//     //  それぞれ、デフォルトの値を設定します。
//     //  入力されなければ、デフォルトの値が送信されます。
//     //  更新ボタンをクリックすると、post_data.php にデータが送信されます。
//     //  post_data.php では、$_POST['data']の値に応じて
//     //  データベースの更新や削除を行います。
//     //  data => 'update' の場合は
//     //  学生情報の更新を行い、
//     //  data => 'delete' の場合は
//     //  学生情報の削除を行います。
//     //
//     //  「学生情報一覧に戻る」リンクを表示します。
//     show_bottom(true);
// 
?>