<?php
session_set_cookie_params([
    'lifetime' => 0,           // ブラウザを閉じるとクッキー削除
    'path'     => '/',         // サイト全体で有効
    'domain'   => '',            // 現在のドメインで有効
    'secure'   => true,          // HTTPSのみクッキーを送信
//  'secure'   => false,       // 開発環境ではHTTPSが使えない場合はfalseに設定

    'httponly' => true,        // JSアクセス禁止
    'samesite' => 'Strict'     // 他サイトからのリクエストでは
]);                            // クッキーを送らない  (CSRF対策)

//  以上は、セッション開始の前に設定を行う必要があります


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
session_start();
//  session timeout を設定します。
handle_session_timeout();


//  ユーザーエージェントをチェックする
try{
    user_agent_check();
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



// ここまでで、セッションの安全性を確保しました。


    //  リクエストメソッドを確認します。
    $method = $_SERVER['REQUEST_METHOD'];

 //  GET メソッドのみのアクセスを想定
 try {
    if ($method !== 'GET') {
       throw new InvalidArgumentException("不正なリクエストメソッド: {$method}");
    }
    } catch (InvalidArgumentException $e) {
    error_log("不正なリクエストメソッド - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", メソッド: {$method}");
    session_unset();
    session_destroy();
    session_write_close();
    http_response_code(405);  // Method Not Allowed 許可されていないメソッド
    die('不正なリクエストです。<br>
         エラーID: ' . uniqid() . '<br>
         <a href="index.php">学生一覧に戻る</a>');
    } catch (Exception $e) {
    error_log("予期しないエラー - リクエストメソッドチェック - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", エラー: " . $e->getMessage());
    session_unset();
    session_destroy();
    session_write_close();
    http_response_code(500);  // Internal Server Error 内部サーバーエラー
    die('システムエラーが発生しました。管理者にお問い合わせください。<br>
         エラーID: ' . uniqid() . '<br>
         <a href="index.php">学生一覧に戻る</a>');
}


    show_top('学生情報の追加');
    //  POST メソッドで送信するため、ワンタイムトークンを生成します。
    $token = generate_csrf_token();
    //  想定外のミスを避けるために、トークンを htmlspecialchars でエスケープします。
    //  次の関数 show_input() でトークンが POST で送信されます。 
    show_input(htmlspecialchars($token, ENT_QUOTES, 'UTF-8'));

    //  true なら、「学生情報の一覧に戻る」というリンクを表示します。
    show_bottom(true);

?>