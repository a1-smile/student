<?php


//  ファイルを安全に読み込むための関数を定義しているファイルを読み込みます。
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
    die("想定していない例外が発生しました。");
    exit;
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHPエラー [$errno]: $errstr in $errfile:$errline");
    http_response_code(500);
    die("想定されていない不具合が発生しました、エラーハンドラー。");
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


try {
    // データベース接続状態の確認
    error_log("DBManagerオブジェクト確認します。");
    if (!($dbm instanceof DBManager)) {
        error_log("DBManagerオブジェクトが未生成です。例外をスローします。");
        throw new RuntimeException('オブジェクトの未生成');
    }
    
} catch (DatabaseException $e) {
    // DatabaseExceptionの詳細処理
    error_log($e->getLogMessage());
    
    $driver_code = $e->getDriverCode();
    switch ($driver_code) {
        case 2002:
        case 2003:
        case 2006:
            // 接続エラー
            die('データベースサーバーに接続できません。<br>
                 しばらく待ってから再試行してください。<br>
                 <a href="index.php">戻る</a>');
            break;
        case 1062:
            // 重複エラー
            die('データの重複エラーが発生しました。<br>
                 エラーID: ' . uniqid() . '<br>
                 <a href="index.php">戻る</a>');
            break;
        default:
            die('データベースエラーが発生しました。<br>
                 エラーID: ' . uniqid() . '<br>
                 <a href="index.php">戻る</a>');
    }
} catch (PDOException $e) {
    // PDOExceptionを詳細にログ記録してからラッピング
    $sqlstate = $e->getCode();
    $error_info = $e->errorInfo;
    $driver_code = $error_info[1] ?? null;
    
    $log_message = sprintf(
        "PDOエラー - get_student() - ID: %s, SQLSTATE: %s, ドライバーコード: %s, メッセージ: %s, ファイル: %s, 行: %d, IP: %s, セッションID: %s",
        $id,
        $sqlstate,
        $driver_code,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $_SERVER['REMOTE_ADDR'],
        session_id()
    );
    error_log($log_message);
    
    // RuntimeExceptionでラッピングして再スロー
    throw new RuntimeException(
        'データベースエラーが発生しました',
        is_numeric($driver_code) ? (int)$driver_code : 0,
        $e
    );

}  catch (RuntimeException $e) {
    // ラップされたPDOExceptionと通常のRuntimeExceptionを処理
    $previous = $e->getPrevious();
    
    if ($previous instanceof PDOException) {
        // データベースエラーの処理
        //  getCode()は「大まかな分類」、SQLSTATEコード
        // errorInfo[1]は「詳細な分類」と覚えておくと、適切な使い分けができます！
        //  PDOException の errorInfo は、配列で、[0] が SQLSTATE, [1] がドライバー固有のエラーコード, [2] がエラーメッセージです。
        //     $error_info = [
        //     0 => 'SQLSTATEコード',        // getCode()と同じ
        //     1 => 'ドライバー固有コード',     // MySQL: 数値、PostgreSQL: 文字列など
        //     2 => 'ドライバー固有メッセージ'  // 詳細なエラーメッセージ
        //      ];

        $pdo_code = $previous->errorInfo[1] ?? $previous->getCode();
        
        switch ($pdo_code) {
            case 2002:
            case 2003:
            case 2006:
                die('データベースサーバーに接続できません。しばらく待ってから再試行してください。<br>
                     <a href="index.php">学生一覧に戻る</a>');
                break;
            case 1054:
            case 1146:
                die('データベース構造に問題があります。管理者にお問い合わせください。<br>
                     エラーID: ' . uniqid());  // ID を生成します。
                break;
            case 1062:
                die('データの重複エラーが発生しました。管理者にお問い合わせください。<br>
                     エラーID: ' . uniqid());  // ID を生成します。
                break;
            default:
                die('データベースエラーが発生しました。管理者にお問い合わせください。<br>
                     エラーID: ' . uniqid() . '<br>
                     <a href="index.php">学生一覧に戻る</a>');
        }
    } else {
        // 通常のRuntimeException（オブジェクト未生成など）
        error_log("実行時エラー: " . $e->getMessage());
        die('システム実行時エラーが発生しました。管理者にお問い合わせください。<br>
             エラーID: ' . uniqid() . '<br>
             <a href="index.php">学生一覧に戻る</a>');
    }
} catch (Exception $e) {
    // その他の予期しないエラー
    $log_message = sprintf(
        "予期しないエラー - get_student() - ID: %s, メッセージ: %s, ファイル: %s, 行: %d, IP: %s",
        $id,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $_SERVER['REMOTE_ADDR']
    );
    error_log($log_message);

    die('システムエラーが発生しました。管理者にお問い合わせください。<br>
         エラーID: ' . uniqid() . '<br>
         <a href="index.php">学生一覧に戻る</a>');
}   
    

// } catch (PDOException $e) {
//     // データベース固有のエラー
//     $error_code = $e->getCode();
//     // 主要なエラーコード
//     // 接続: 2002, 2003, 08000系
//     // 構造: 1054, 1146, 42S02系
//     // 制約: 1062, 23000系
//     // 構文: 1064, 42000系
//     $error_message = $e->getMessage();
    
//     //  sprint()は、%s の部分に変数を埋め込んで文字列を生成します。
//     //  戻り値は、生成された文字列です。

//     $log_message = sprintf(
//         "PDOエラー - get_student() - ID: %s, コード: %s, メッセージ: %s, ファイル: %s, 行: %d, IP: %s",
//         $id,
//         $error_code,
//         $error_message,
//         $e->getFile(),
//         $e->getLine(),
//         $_SERVER['REMOTE_ADDR']
//     );
//     error_log($log_message);
    
//     // エラーコードに応じた処理
//     switch ($error_code) {
//         case 2002: // 接続エラー
//             die('データベースサーバーに接続できません。しばらく待ってから再試行してください。');
//             break;
//         case 1054: // 不明なカラム
//             die('データベース構造に問題があります。管理者にお問い合わせください。');
//             break;
//         default:
//             die('データベースエラーが発生しました。管理者にお問い合わせください。');
//     }
// } catch (BusinessLogicException $e) {
//     // ビジネスロジックの矛盾（本来起こりえない状況）

//     //  getContext()は、 private$context のゲッターです。
//     $context = $e->getContext();
//     $log_message = sprintf(
//         "ビジネスロジックエラー - ID: %s, メッセージ: %s, コンテキスト: %s, IP: %s, 時刻: %s",
//         $context['id'] ?? 'unknown',
//         $e->getMessage(),
//         json_encode($context, JSON_UNESCAPED_UNICODE),
//         $_SERVER['REMOTE_ADDR'],
//         date('Y-m-d H:i:s')
//     );
//     error_log($log_message);
    
//     // エラーコードに応じた処理
//     switch ($e->getCode()) {
//         case BusinessLogicException::ERR_DATA_NOT_FOUND :
//             die('指定された学生情報が見つかりません。データの整合性に問題が発生しました。<br>
//                  管理者に報告されました。<br>
//                  エラーID: ' . uniqid() . '<br>
//                  <a href="index.php">学生一覧に戻る</a>');
//             break;
            
//         default:
//             die('データの整合性に問題が発生しました。管理者に報告されました。<br>
//                  エラーID: ' . uniqid() . '<br>
//                  <a href="index.php">学生一覧に戻る</a>');
//     }
// }catch (RuntimeException $e) {
//     // オブジェクトの未生成
//     error_log("クラスをnewできていません。: " . $e->getMessage());
//     die('プログラミング上で、
//          オブジェクトの生成に失敗しました。<br>
//          エラーID: ' . uniqid() . '<br>
//          <a href="index.php">学生一覧に戻る</a>');


//  以下のCSRFたいさくにデータベースを使用するので、
//  PDOオブジェクトを取得します。
$dbm->connect();
$pdo = $dbm->get_db();




//  CSRF対策
//  4種のrate_key を定義します。

/*------------------------------------
  device_id クッキー準備（1年有効）
------------------------------------*/
    // if (empty($_COOKIE['device_id'])) {
    //     $id = bin2hex(random_bytes(16));
    //     setcookie(
    //         'device_id', // 名前
    //         $id, // 値
    //         time() + 86400 * 365, // 有効期限（1年後）
    //         "/", // パス :サイト内全域で有効
    //         "", // ドメイン :指定なしで現在のドメイン
    //         false, // HTTPS限定か？==> false
    //         true //  JavaScriptからアクセス不可==> true
    //     );
    //     $_COOKIE['device_id'] = $id;
    // }
set_device_id_cookie();
/*------------------------------------
  IPプレフィックス取得
------------------------------------*/
// IPプレフィックス取得
$ipv4_blocks = $_ENV['IP_CHECK_IPV4_BLOCKS'] ?? 2;
$ipv6_blocks = $_ENV['IP_CHECK_IPV6_BLOCKS'] ?? 3;

$ip_prefix = get_ip_prefix_for_session($ipv4_blocks, $ipv6_blocks);
/*------------------------------------
  session_id hash化
------------------------------------*/
    $session_id = session_id();
    $session_id_hash = hash('sha256', $session_id);
/*------------------------------------
  レート制限用キー生成（4層）
------------------------------------*/
$ip_key        = "ip:" . $_SERVER['REMOTE_ADDR'];
$session_key   = "session:" . $session_id_hash;
$device_key    = "device:" . $_COOKIE['device_id'];
$ip_prefix_key = "ip_prefix:" . $ip_prefix;

/*------------------------------------
  閾値設定
 */
const RATE_LIMITS = [
    'ip' => [ 'window' => 300, 'max_failures' => 1000, 'soft_failure' => 300], // 5分で1000回
    'session' => [ 'window' => 300, 'max_failures' => 10, 'soft_failure' => 3], // 5分で10回
    'device' => [ 'window' => 300, 'max_failures' => 30, 'soft_failure' => 10], // 5分で30回
    'ip_prefix' => [ 'window' => 300, 'max_failures' => 5000, 'soft_failure' => 1500], // 5分で5000回
];

const BLOCK_DURATION_SESSION = 1800; // 30分
const BLOCK_DURATION_DEVICE  = 1800; // 30分


try {
    error_log("csrf_token_time を確認します。");
    if (!isset($_SESSION['csrf_token_time'])) {
        error_log('[CSRF VALIDATE] token_time missing; backfill now (DEV ONLY)');
        $_SESSION['csrf_token_time'] = time(); // 開発中のみ。原因が判明したら必ず削除
    }
    error_log("csrf_token_time があります。");
    error_log(sprintf(
        "[CSRF VALIDATE] SID=%s token_time=%s now=%s",
        session_id(),
        $_SESSION['csrf_token_time'],
        time()
    ));
    // token の型式チェック
    error_log("validate_csrf_token1() をよびだします。");
    validate_csrf_token1();
} catch (CSRFException $e) {
    //  失敗として記録
    error_log("CSRF例外をキャッチしました。失敗をデータベースに記録します。");
    record_failure($pdo, $ip_key);
    record_failure($pdo, $session_key);
    record_failure($pdo, $device_key);
    record_failure($pdo, $ip_prefix_key);

    //  unset トークン
    unset_token();

    //  httpレスポンスコード403を設定
    http_response_code(403);  // Forbidden アクセス禁止
    //  error page にリダイレクト
    header('Location: error_page.php');

}  
    // リファラーチェック（追加のセキュリティ）
    $referer = $_SERVER['HTTP_REFERER'] ?? ''; //  アクセス元のURL
    $host = $_SERVER['HTTP_HOST'] ?? '';       //  アクセス先のドメイン
    
    //  strpos() は、部分文字列の位置を検索します。
    //  文字列の一致を探す関数。
    if (empty($referer) || strpos($referer, $host) === false) {
        // 警告レベル（ブロックはしない）
        // 補助的な監視に留めるため、処理を止めない。

        error_log("警告: 不審なリファラー - リファラー: {$referer}, ホスト: {$host}, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    
    // レート制限チェック
try {
    error_log("レート制限チェックを開始します。");
    rate_limit_check1($pdo, $ip_key, $session_key, $device_key, $ip_prefix_key);
} catch (CSRFException $e) {
    //  失敗として記録
    record_failure($pdo, $ip_key);
    record_failure($pdo, $session_key);
    record_failure($pdo, $device_key);
    record_failure($pdo, $ip_prefix_key);

    //  unset トークン
    unset_csrf_token();

    //  security level を取得
    $security_level = $e->getSecurityLevel();

    if ($security_level === SecurityException::LEVEL_CRITICAL) {
        // 致命的レベルの場合は、block_page.php にリダイレクト
        // httpレスポンスコード429を設定
        http_response_code(429);  // Too Many Requests
        header('Location: block_page.php');
    }elseif ($security_level === SecurityException::LEVEL_HIGH) {
    //  httpレスポンスコード403を設定
    http_response_code(403);  // Forbidden アクセス禁止
    //  recaptcha にリダイレクト
    header('Location: recaptcha.php');
    exit;
    }   
}
    // トークンの一致確認
    try {
    $post_token = $_POST['csrf_token']??'';
    $session_token = $_SESSION['csrf_token']??'';

        // 追加: デバッグログ
        error_log(sprintf(
            "[CSRF DEBUG] SID=%s POST=%s SESSION=%s COOKIES=%s",
            session_id(),
            $post_token,
            $session_token,
            json_encode($_COOKIE, JSON_UNESCAPED_UNICODE)
        ));






    if (!hash_equals($post_token, $session_token)) {
        throw CSRFException::fromCurrentRequest(
            'CSRFトークンが一致しません - 攻撃の可能性',
            SecurityException::SEC_CSRF_ATTACK,
            [], 
            SecurityException::LEVEL_MEDIUM,
            null);
    }
    } catch (CSRFException $e) {
        //  失敗として記録
        record_failure($pdo, $ip_key);
        record_failure($pdo, $session_key);
        record_failure($pdo, $device_key);
        record_failure($pdo, $ip_prefix_key);
        //  unset トークン
        unset_token();

        //  httpレスポンスコード403を設定
        http_response_code(403);  // Forbidden アクセス禁止
        //  error page にリダイレクト
        header('Location: error_page.php');
        exit;
    }

    //  トークンの使用後破棄
    unset_token();

    //  以上で CSRF対策 が完了しました。

    //  ログイン成功時はここで、session_regenerate_id(true) を実行。
    //  これにより、セッションIDが新しいものに置き換えられ、
    //  古いセッションIDは無効化されます。

    //  DDoS 対策として
    //  これ以前の処理でに必要上に
    //  session_destroy()
    //  session_unset()
    //  session_start()
    //  を実行しない方針です。
    
    
    
    
    

        //  POST メソッドで送信されたかを確認し、
    //  そうでない場合は、不正なアクセスとして処理を終了します。
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new SecurityException('POSTメソッド以外のアクセスです');
    }
} catch (SecurityException $e) {
    // HTTPステータスコード405を設定
    http_response_code(405);  // Method Not Allowed
    
    // Allowヘッダーでサポートするメソッドを明示
    header('Allow: POST');
    
    // Cache-Controlヘッダーでキャッシュを無効化
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // セキュリティ関連エラー（不正な形式）
    $log_message = sprintf(
        "セキュリティエラー - メソッド検証 - エラー: %s, メソッド: %s, IP: %s, UA: %s, リファラー: %s, セッションID: %s, 時刻: %s",
        $e->getMessage(),
        $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        $_SERVER['HTTP_REFERER'] ?? 'unknown',
        session_id(),
        date('Y-m-d H:i:s')
    );
    error_log($log_message);
    
    session_unset();
    session_destroy();
    
    die('405 Method Not Allowed<br>
         このリソースではPOSTメソッドのみサポートされています。<br>
         不正なアクセス方法が検出されました。<br>
         エラーID: ' . uniqid() . '<br>
         <a href="index.php">学生一覧画面</a>から正しい手順で操作してください。');
}


//  このファイルの主な内容は、
//  function validate_csrf_token() として
// CSRFトークンの型式チェックを行う関数を定義します。

// 設定値を定数化

/**
 * function validate_csrf_token()
 * CSRF token の型式チェック
 * @throws CSRFException if invalid token
 */
function validate_csrf_token1(): void {
    //  通信メソッドを確認
    error_log("通信メソッドを確認します。");
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    error_log("POSTメソッドです。CSRFトークンを検証します。");
    error_log("csrf_token_time を確認します。");
    if (!isset($_SESSION['csrf_token_time'])) {
        error_log('[CSRF VALIDATE] token_time missing; backfill now (DEV ONLY)');
        $_SESSION['csrf_token_time'] = time(); // 開発中のみ。原因が判明したら必ず削除
    }
    error_log("csrf_token_time があります。");
    error_log(sprintf(
        "[CSRF VALIDATE] SID=%s token_time=%s now=%s",
        session_id(),
        $_SESSION['csrf_token_time'],
        time()
    ));
    //  sessionにトークンがセットされていなければ
    //  throw CSRFException::fromCurrentRequest(
    //  'CSRFトークンが存在しません');
    error_log("csrf_token がsessionにあるか確認します。");
    if (!isset($_SESSION['csrf_token'])) {
        error_log("セッションにCSRFトークンが存在しません。例外をスローします。");
        throw CSRFException::fromCurrentRequest(
            'セッションにCSRFトークンが存在しません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    error_log("csrf_token がsessionにあります。");
    $session_token = $_SESSION['csrf_token'];

    //  sessionにトークンタイムがセットされていなければ
    //  throw CSRFException::fromCurrentRequest(
    //  'CSRFトークンタイムが存在しません');
    if (!isset($_SESSION['csrf_token_time'])) {
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        throw CSRFException::fromCurrentRequest(
            'CSRFトークンタイムが存在しません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    $token_time = $_SESSION['csrf_token_time'];
    //  トークンタイムが30分以上前なら
    //  throw CSRFException::fromCurrentRequest(
    //  'CSRFトークンの有効期限が切れています');
    error_log("CSRFトークンが時間切れか確認します。");
    if (time() - $token_time > CSRF_TOKEN_TTL) {
        error_log("CSRFトークンの有効期限が切れています。例外をスローします。");
        throw CSRFException::fromCurrentRequest(
            'CSRFトークンの有効期限が切れています',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    //  POSTされたトークンがあるか確認
    error_log("POSTされたCSRFトークンがあるか確認します。");
    if (!isset($_POST['csrf_token'])) {
        error_log("POSTされたCSRFトークンが存在しません。例外をスローします。");
        throw CSRFException::fromCurrentRequest(
            'POSTのCSRFトークンが存在しません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    $post_token = $_POST['csrf_token'];
    // 追加: 受信値とセッション値をログ
    error_log("POSTされたCSRFトークンがあります。");
    error_log(sprintf(
        "[CSRF VALIDATE] SID=%s POST=%s SESSION=%s",
        session_id(),
        $post_token,
        $_SESSION['csrf_token'] ?? ''
    ));



    //  token が文字列か確認
    //  random_bytes()はバイナリデータで
    //  bin2hex()で16進数文字列に変換される
    error_log("CSRFトークンが文字列か確認します。");
    if (!is_string($post_token)) {
        error_log("POSTされたCSRFトークンが文字列ではありません。例外をスローします。");
        $message = 'POSTされたCSRFトークンが文字列ではありません';
        throw CSRFException::fromCurrentRequest(
            $message,
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    error_log("post CSRFトークンが文字列です。");
    error_log("session CSRFトークンが文字列か確認します。");
    if (!is_string($session_token)) {
        error_log("セッションのCSRFトークンが文字列ではありません。例外をスローします。");
        throw CSRFException::fromCurrentRequest(
            'セッションのCSRFトークンが文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    //  token の長さを確認
    //  bin2hex(random_bytes(32)) は64文字の16進数文字列
    //  bin2hex()で64文字になるのは
    //  32バイトのバイナリデータを16進数に変換するため
    //  64文字でなければ不正
    //  strlen()はバイト数を返す
    error_log("post CSRFトークンの長さを確認します。");
    if (strlen($post_token) !== CSRF_TOKEN_LENGTH) {
        error_log("POSTされたCSRFトークンの長さが不正です。例外をスローします。");
        throw CSRFException::fromCurrentRequest(
            'ポストCSRFトークンの長さが不正です',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    error_log("post CSRFトークンの長さは正しいです。");
    error_log("session CSRFトークンの長さを確認します。");
    if (strlen($session_token) !== CSRF_TOKEN_LENGTH) {
        error_log("セッションのCSRFトークンの長さが不正です。例外をスローします。");
        throw CSRFException::fromCurrentRequest(
            'セッションのCSRFトークンの長さが不正です',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
}
error_log("session CSRFトークンの長さは正しいです。");


    //  ctype_xdigit()で16進数文字列か確認
    error_log("POSTされたCSRFトークンが16進数文字列か確認します。");
    if (!ctype_xdigit($post_token) ) {
        error_log("POSTされたCSRFトークンが16進数文字列ではありません。例外をスローします。");
        throw CSRFException::fromCurrentRequest(
            'POSTされたCSRFトークンが16進数文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    error_log("POSTされたCSRFトークンは16進数文字列です。");
    error_log("セッションのCSRFトークンが16進数文字列か確認します。");
    if (!ctype_xdigit($session_token) ) {
        error_log("セッションのCSRFトークンが16進数文字列ではありません。例外をスローします。");
        throw CSRFException::fromCurrentRequest(
            'セッションのCSRFトークンが16進数文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    error_log("セッションのCSRFトークンは16進数文字列です。");
    error_log("CSRFトークンの型式チェックが完了しました。");
    error_log("validate_csrf_token1() から戻ります。");
    //  以上で token の型式チェックが完了しました。
}

/**
 * 4層レート制限チェック
 * @param string $ip_key
 * @param string $session_key
 * @param string $device_key
 * @param string $ip_prefix_key
 * @param PDO $pdo
 * @throws CSRFException レート制限超過時
 */

function rate_limit_check1(
    PDO $pdo,
    string $ip_key, 
    string $session_key,
    string $device_key,
    string $ip_prefix_key): void {
error_log("rate_limit_check1() を開始します。");
/*------------------------------------
  事前ブロック確認（session / device）
------------------------------------*/
error_log("ブロックされているか確認します。");
if (is_blocked($pdo, $session_key)) {
    error_log("セッションが一時的にブロックされています。例外をスローします。");
    throw CSRFException::fromCurrentRequest(
        'セッションが一時的にブロックされています',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_CRITICAL,
        null
    );
}
if (is_blocked($pdo, $device_key)) {
    error_log("デバイスが一時的にブロックされています。例外をスローします。");
    throw CSRFException::fromCurrentRequest(
        'デバイスが一時的にブロックされています',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_CRITICAL,
        null
    );
}
error_log("ブロックされていません。");
error_log("4層レート制限を実施します。");

/*------------------------------------
  4層レート制限の実施
------------------------------------*/

// 第1層：IP（5分で100回）
//  5分間の失敗タイムスタンプを配列で取得
$failure_array = get_failures($pdo, $ip_key, RATE_LIMITS['ip']['window']);
//  失敗回数をカウント
$failure_count = count($failure_array);
if ($failure_count 
    >=
    RATE_LIMITS['ip']['max_failures']) {
    throw CSRFException::fromCurrentRequest(
        'IPアドレスからのリクエストが多すぎます',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_CRITICAL,
        null
    );
}elseif ($failure_count 
    >=
    RATE_LIMITS['ip']['soft_failure']) {
    throw CSRFException::fromCurrentRequest(
        'IPアドレスからのリクエストが多めです',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_HIGH,
        null
    );
    }

// 第2層：セッションID（5分で10回） ← 本命
//  5分間の失敗タイムスタンプを配列で取得
$failure_array = get_failures($pdo, $session_key, RATE_LIMITS['session']['window']);
//  失敗回数をカウント
$failure_count = count($failure_array);
if ($failure_count 
    >=
    RATE_LIMITS['session']['max_failures']) {
           // 閾値超え → ブロック登録
    record_block($pdo, $session_key, BLOCK_DURATION_SESSION);
    throw CSRFException::fromCurrentRequest(
        'セッションからのリクエストが多すぎます',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_CRITICAL,
        null
    );
}elseif ($failure_count 
        >=
        RATE_LIMITS['session']['soft_failure']) {
    throw CSRFException::fromCurrentRequest(
        'セッションからのリクエストが多めです',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_HIGH,
        null
    );
    }

// 第3層：device_id（5分で30回）
//  5分間の失敗タイムスタンプを配列で取得
$failure_array = get_failures($pdo, $device_key, RATE_LIMITS['device']['window']);
//  失敗回数をカウント
$failure_count = count($failure_array);
if ($failure_count 
    >=
    RATE_LIMITS['device']['max_failures']) {
            // 閾値超え → ブロック登録
    record_block($pdo, $device_key, BLOCK_DURATION_DEVICE);
        throw CSRFException::fromCurrentRequest(
            'デバイスからのリクエストが多すぎます',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_CRITICAL,
            null
        );
    }elseif ($failure_count 
    >=
    RATE_LIMITS['device']['soft_failure']) {
        throw CSRFException::fromCurrentRequest(
            'デバイスからのリクエストが多めです',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_HIGH,
            null
        );
    }
    
    // 第4層：IPプレフィックス（5分で1000回）
    //  5分間の失敗タイムスタンプを配列で取得
    $failure_array = get_failures($pdo, $ip_prefix_key, RATE_LIMITS['ip_prefix']['window']);
    //  失敗回数をカウント
    $failure_count = count($failure_array);
    if ($failure_count 
    >=
    RATE_LIMITS['ip_prefix']['max_failures']) {
        throw CSRFException::fromCurrentRequest(
            'IPプレフィックスからのリクエストが多すぎます',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_CRITICAL,
            null
        );
    }elseif ($failure_count 
    >=
    RATE_LIMITS['ip_prefix']['soft_failure']) {
        throw CSRFException::fromCurrentRequest(
            'IPプレフィックスからのリクエストが多めです',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_HIGH,
            null
        );
    }
}


 

