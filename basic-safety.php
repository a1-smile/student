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
    http_response_code(500); // Internal Server Error 内部サーバーエラー
    die("想定していない例外が発生しました。");
    exit;
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHPエラー [$errno]: $errstr in $errfile:$errline");
    http_response_code(500); // Internal Server Error 内部サーバーエラー
    die("想定されていない不具合が発生しました、エラーハンドラー。");
    exit;
});

//  データベース処理のために $pdo を取得します。
try {
    $dbm->connect();
    $pdo = $dbm->get_db();
} catch (PDOException $e) {
    $error_id = uniqid('db_');
    error_log('DB接続に失敗: ' . $e->getMessage());
    die("システムエラーが発生しました。エラーID: $error_id");
} catch (Exception $e) {
    $error_id = uniqid('unexpected_');
    error_log('予期しないエラーが発生しました: ' . $e->getMessage());
    die("予期しないエラーが発生しました。エラーID: $error_id");
}



//  ユーザーエージェントをチェックする
try {
    user_agent_check($pdo);
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
    error_log("不正なIPアドレス - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", エラー: " . $e->getMessage() . ", UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));

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








//  CSRF対策
//  4種のrate_key を定義します。

/*------------------------------------
device_id クッキー準備（1年有効）
------------------------------------*/
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
$device_key    = "device:" . ($_COOKIE['device_id'] ?? '');
$ip_prefix_key = "ip_prefix:" . $ip_prefix;

/*------------------------------------
閾値設定
*/
const RATE_LIMITS = [
    'ip' => ['window' => 300, 'max_failures' => 1000, 'soft_failure' => 300], // 5分で1000回
    'session' => ['window' => 300, 'max_failures' => 10, 'soft_failure' => 3], // 5分で10回
    'device' => ['window' => 300, 'max_failures' => 30, 'soft_failure' => 10], // 5分で30回
    'ip_prefix' => ['window' => 300, 'max_failures' => 5000, 'soft_failure' => 1500], // 5分で5000回
];

const BLOCK_DURATION_SESSION = 1800; // 30分
const BLOCK_DURATION_DEVICE  = 1800; // 30分


// メソッド検証は最初に実施（GET等を早期排除）
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new SecurityException('POSTメソッド以外のアクセスです');
    }
} catch (SecurityException $e) {
    http_response_code(405);
    header('Allow: POST');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    error_log(sprintf(
        "セキュリティエラー - メソッド検証 - エラー: %s, メソッド: %s, IP: %s, UA: %s, リファラー: %s, セッションID: %s, 時刻: %s",
        $e->getMessage(),
        $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        $_SERVER['HTTP_REFERER'] ?? 'unknown',
        session_id(),
        date('Y-m-d H:i:s')
    ));
    session_unset();
    session_destroy();
    die('405 Method Not Allowed<br>このリソースではPOSTメソッドのみサポートされています。<br>不正なアクセス方法が検出されました。<br>エラーID: ' . uniqid() . '<br><a href="index.php">学生一覧画面</a>から正しい手順で操作してください。');
}

// ----- Content-Type のチェック -----
try {
    content_type_check();
} catch (CSRFException $e) {
    record_failure($pdo, $ip_key);
    record_failure($pdo, $session_key);
    record_failure($pdo, $device_key);
    record_failure($pdo, $ip_prefix_key);
    //  unset トークン
    unset_token();
    //遷移先でステータスを設定する設計
    header('Location: error_page.php', true, 302);
    exit;
}

// ここから先は「正規のフォーム POST」だけ通過

//  Origin/Hostの整合性確認
try {
    origin_host_check();
} catch (CSRFException $e) {
    record_failure($pdo, $ip_key);
    record_failure($pdo, $session_key);
    record_failure($pdo, $device_key);
    record_failure($pdo, $ip_prefix_key);
    //  unset トークン
    unset_token();
    //遷移先でステータスを設定する設計
    header('Location: error_page.php', true, 302);
    exit;
}





try {
    // token の型式チェック
    validate_csrf_token();
} catch (CSRFException $e) {
    //  失敗として記録
    record_failure($pdo, $ip_key);
    record_failure($pdo, $session_key);
    record_failure($pdo, $device_key);
    record_failure($pdo, $ip_prefix_key);

    //  unset トークン
    unset_token();

    //  遷移先でステータスを設定する設計
    header('Location: error_page.php', true, 302);
    exit;
}
// リファラーチェック（追加のセキュリティ）
$result = referer_check();
if (!$result) {
    // 警告レベル（ブロックはしない）
    // 補助的な監視に留めるため、処理を止めない。
    $referer = $_SERVER['HTTP_REFERER'] ?? 'unknown';
    $host = $_SERVER['HTTP_HOST'] ?? 'unknown';
    error_log("警告: 不審なリファラー - リファラー: {$referer}, ホスト: {$host}, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

// レート制限チェック
try {
    rate_limit_check($pdo, $ip_key, $session_key, $device_key, $ip_prefix_key);
} catch (CSRFException $e) {
    //  失敗として記録
    record_failure($pdo, $ip_key);
    record_failure($pdo, $session_key);
    record_failure($pdo, $device_key);
    record_failure($pdo, $ip_prefix_key);

    //  unset トークン
    unset_token();

    //  security level を取得
    $security_level = $e->getSecurityLevel();
    try {
        if ($security_level === SecurityException::LEVEL_CRITICAL) {
            //  致命的レベルの場合は、log_out.php にリダイレクト
            //  遷移先でステータスを設定する設計
            header('Location: log_out.php', true, 302);
            exit;
        } elseif ($security_level === SecurityException::LEVEL_HIGH) {
            //  log_out_session_reset_.php にリダイレクト
            //  遷移先でステータスを設定する設計
            header('Location: log_out_session_reset_.php', true, 302);
            exit;
        } elseif ($security_level === SecurityException::LEVEL_MEDIUM) {
            //  recaptcha にリダイレクト
            //  遷移先でステータスを設定する設計
            header('Location: recaptcha.php', true, 302);
            exit;
        } else {
            //  プログラムがおかしい場合は、
            // LogicException をスロー。
            throw new LogicException(
                '未知のセキュリティレベルです',
                0,
                null
            );
            //  その他のレベルの場合は、一般的なエラーページにリダイレクト
            //  遷移先でステータスを設定する設計
        }
    } catch (LogicException $e) {
        // システムエラー
        $session_id = session_id();
        error_log("レート制限チェックエラー - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", エラー: " . $e->getMessage() . ", セッションID: " . $session_id);

        session_unset();
        session_destroy();
        session_write_close();
        http_response_code(500);  // Internal Server Error 内部サーバーエラー
        die('システムエラーが発生しました。管理者にお問い合わせください。<br>
         エラーID: ' . uniqid() . '<br>');
    }
}

// トークンの一致確認
try {
    $post_token = $_POST['csrf_token'];
    $session_token = $_SESSION['csrf_token'];
    if (!hash_equals($post_token, $session_token)) {
        throw CSRFException::fromCurrentRequest(
            'CSRFトークンが一致しません - 攻撃の可能性',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
} catch (CSRFException $e) {
    //  失敗として記録
    record_failure($pdo, $ip_key);
    record_failure($pdo, $session_key);
    record_failure($pdo, $device_key);
    record_failure($pdo, $ip_prefix_key);
    //  unset トークン
    unset_token();

    // 302で遷移。最終ステータスは遷移先で設定する設計
    header('Location: error_page.php', true, 302);
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
