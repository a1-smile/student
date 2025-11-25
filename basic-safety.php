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





//  CSRF対策
//  4種のrate_key を定義します。

/*------------------------------------
  device_id クッキー準備（1年有効）
------------------------------------*/
if (empty($_COOKIE['device_id'])) {
    $id = bin2hex(random_bytes(16));
    setcookie(
        'device_id', // 名前
        $id, // 値
        time() + 86400 * 365, // 有効期限（1年後）
        "/", // パス :サイト内全域で有効
        "", // ドメイン :指定なしで現在のドメイン
        false, // HTTPS限定か？==> false
        true //  JavaScriptからアクセス不可==> true
    );
    $_COOKIE['device_id'] = $id;
}
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
$session_key   = "sess:" . $session_id_hash;
$device_key    = "dev:" . $_COOKIE['device_id'];
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
    // token の型式チェック
    validate_csrf_token();
} catch (CSRFException $e) {
    //  失敗として記録
    record_failure($pdo, $ip_key);
    record_failure($pdo, $session_key);
    record_failure($pdo, $device_key);
    record_failure($pdo, $ip_prefix_key);

    //  unset トークン
    unset_csrf_token();

    //  httpレスポンスコード403を設定
    http_response_code(403);
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
    rate_limit_check($pdo, $ip_key, $session_key, $device_key, $ip_prefix_key);
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
        // httpレスポンスコード403を設定
        http_response_code(403);
        header('Location: block_page.php');
    }elseif ($security_level === SecurityException::LEVEL_HIGH) {
    //  httpレスポンスコード403を設定
    http_response_code(403);
    //  recaptcha にリダイレクト
    header('Location: recaptcha.php');
    exit;
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
            null);
    }
    } catch (CSRFException $e) {
        //  失敗として記録
        record_failure($pdo, $ip_key);
        record_failure($pdo, $session_key);
        record_failure($pdo, $device_key);
        record_failure($pdo, $ip_prefix_key);
        //  unset トークン
        unset_csrf_token();

        //  httpレスポンスコード403を設定
        http_response_code(403);
        //  error page にリダイレクト
        header('Location: error_page.php');
    }
    
    
    
    
    

        //  POST メソッドで送信されたかを確認し、
    //  そうでない場合は、不正なアクセスとして処理を終了します。
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new SecurityException('POSTメソッド以外のアクセスです');
    }
} catch (SecurityException $e) {
    // HTTPステータスコード405を設定
    http_response_code(405);
    
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


    //  $_POST['id'] が設定されているかを確認します。
    //  設定されていない場合は、不正なアクセスとして処理を終了します。
    // try catch 構文で$POST['id']の存在を確認します。
    try {
        $id = $_POST['id'] ?? null;
        if ($id === null) {
            //  例外をスローします。BusinessLogicException
            throw new BusinessLogicException('$id が送信されていません');
        }
    } catch (BusinessLogicException $e) {
        $log_message = sprintf(
            "ビジネスロジックエラー - ID未送信 - エラー: %s, IP: %s, UA: %s, リファラー: %s, セッションID: %s, 時刻: %s",
            $e->getMessage(),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $_SERVER['HTTP_REFERER'] ?? 'unknown',
            session_id(),
            date('Y-m-d H:i:s')
        );
        error_log($log_message);
        die('情報が適切に送信されていません。<br>
             エラーID: ' . uniqid() . '<br>
             <a href="index.php">学生一覧に戻る</a>');
    }
    //  $id をエスケープ
    $id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');

    //  get_student() メソッドは
    //  $id を引数に取り、学生情報を取得します。
    //  戻り値は、連想配列の形で学生情報が格納されます。
    //  学生情報が存在しない場合は、null を返します。
    // 

// 
try {
    // データベース接続状態の確認
    if (!($dbm instanceof DBManager)) {
        throw new RuntimeException('オブジェクトの未生成');
    }
    
    // 学生情報の取得
    $member = $dbm->get_student($id);
    
    // ロジック上ありえない状況（ビジネスロジックの矛盾）
if ($member === null) {
    throw new BusinessLogicException(
        'データの整合性に問題があります。index.phpから正常に遷移したはずのIDでデータが見つかりません',
        BusinessLogicException::ERR_DATA_NOT_FOUND 
,
        [
            'id' => $id,
            'session_id' => session_id(),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            'stamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]
    );
}   //  BusinessLogicException::ERR_DATA_NOT_FOUND 
    //  は２番目の引数でエラーコードです。
    //  Exception のエラーコードは自分で定義できます。
    //  クラスで const と宣言しているので :: でアクセスできます。
    //  3 番目の引数は、エラーのコンテキスト情報を含む連想配列です。
    //  BusinessLogicException のコンストラクタで設定しました。

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
                 <a href="index.php">学生一覧に戻る</a>');
            break;
        case 1062:
            // 重複エラー
            die('データの重複エラーが発生しました。<br>
                 エラーID: ' . uniqid() . '<br>
                 <a href="index.php">学生一覧に戻る</a>');
            break;
        default:
            die('データベースエラーが発生しました。<br>
                 エラーID: ' . uniqid() . '<br>
                 <a href="index.php">学生一覧に戻る</a>');
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
        '学生情報の取得中にデータベースエラーが発生しました',
        is_numeric($driver_code) ? (int)$driver_code : 0,
        $e
    );

} catch (BusinessLogicException $e) {
    // ビジネスロジックエラー
    $context = $e->getContext();
    $log_message = sprintf(
        "ビジネスロジックエラー - ID: %s, メッセージ: %s, コンテキスト: %s, IP: %s, 時刻: %s",
        $context['id'] ?? 'unknown',
        $e->getMessage(),
        json_encode($context, JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'],
        date('Y-m-d H:i:s')
    );
    error_log($log_message);
    
    switch ($e->getCode()) {
        case BusinessLogicException::ERR_DATA_NOT_FOUND :
            die('指定された学生情報が見つかりません。データの整合性に問題が発生しました。<br>
                 管理者に報告されました。<br>
                 エラーID: ' . uniqid() . '<br>
                 <a href="index.php">学生一覧に戻る</a>');
            break;
        default:
            die('データの整合性に問題が発生しました。管理者に報告されました。<br>
                 エラーID: ' . uniqid() . '<br>
                 <a href="index.php">学生一覧に戻る</a>');
    }

} catch (RuntimeException $e) {
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


    //  <h1> を表示します。
    show_top('個別の学生情報');

    //  table で学生情報を表示します。
    show_student($member);

    //  学生情報を更新するか、削除するかを
    //  条件分岐します。
    //  $_POST['data'] の値が 'update' の場合は
    //  $operation = '更新します';
    //  $post_file = 'student_update.php';

    //  $_POST['data'] の値が 'delete' の場合は
    //  $operation = '削除します';
    //  $post_file = 'student_delete.php';
    $operation = '';
    $post_file = '';

try {
    // データの取得と基本検証
    $raw_data = $_POST['data'] ?? '';
    $data = htmlspecialchars($raw_data, ENT_QUOTES, 'UTF-8');
    
    // データが空の場合
    //  InvalidArgumentException は
    // 引数の値が無効な場合に投げる
    // プログラマーのミスや不正な入力を検出
    // LogicExceptionの子クラス（論理エラー系）

    if ($data === '') {
        throw new InvalidArgumentException('data パラメーターが指定されていません');
    }
    
    // 有効な値のチェック
    $valid_operations = ['update', 'delete'];
    if (!in_array($data, $valid_operations)) {
        throw new InvalidArgumentException('不正な操作が指定されました: ' . $data);
    }
    
    // 操作の種類に応じた設定
    switch ($data) {
        case 'update':
            $operation = '更新ページへ...';
            $post_file = 'student_update.php';
            break;
            
        case 'delete':
            $operation = '削除ページへ...';
            $post_file = 'student_delete.php';
            break;
            
        default:
            // この分岐は上記のチェックで回避されるはずだが、安全のため
            throw new InvalidArgumentException('予期しない操作です: ' . $data);
    }
    
    // 遷移先ファイルの存在確認
    if (!file_exists(__DIR__ . '/' . $post_file)) {
        throw new RuntimeException('遷移先ファイルが見つかりません: ' . $post_file);
    }
    
} catch (InvalidArgumentException $e) {
    // 不正なパラメーター（攻撃の可能性）
    $log_message = sprintf(
        "不正なdataパラメーター - 値: %s, エラー: %s, IP: %s, UA: %s, リファラー: %s, セッションID: %s, 時刻: %s",
        $raw_data ?? 'null',
        $e->getMessage(),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        $_SERVER['HTTP_REFERER'] ?? 'unknown',
        session_id(),
        date('Y-m-d H:i:s')
    );
    error_log($log_message);
    
    // セキュリティ上の理由でセッションを無効化
    session_unset();
    session_destroy();
    
    die('不正なアクセスが検出されました。セキュリティ上の理由により処理を中断します。<br>
         この操作は記録されました。<br>
         エラーID: ' . uniqid() . '<br>
         <a href="index.php">学生一覧に戻る</a>');
         
} catch (RuntimeException $e) {
    // システムエラー（ファイル不存在など）
    error_log("システムエラー - data処理 - エラー: " . $e->getMessage() . ", IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    die('システムエラーが発生しました。管理者にお問い合わせください。<br>
         エラーID: ' . uniqid() . '<br>
         <a href="index.php">学生一覧に戻る</a>');
         
} catch (Exception $e) {
    // その他の予期しないエラー
    error_log("予期しないエラー - data処理 - エラー: " . $e->getMessage());
    
    die('予期しないエラーが発生しました。管理者にお問い合わせください。<br>
         エラーID: ' . uniqid() . '<br>
         <a href="index.php">学生一覧に戻る</a>');
}
    
    
    //  学生情報を削除するか、更新するかを
    //  選択します。
    //  「学生情報を削除」ボタンまたは「学生情報を更新」ボタンを表示します。
    //  あらためて、$id, $data, $operation, $post_file, をエスケープします。
    $id        = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    $data      = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    $operation = htmlspecialchars($operation, ENT_QUOTES, 'UTF-8');
    $post_file = htmlspecialchars($post_file, ENT_QUOTES, 'UTF-8');
    
    //  トークンを再生成します。
    $token = bin2hex(random_bytes(32));
    $token = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
    $_SESSION['csrf_token'] = $token;

    //  show_operations() 関数を呼び出して、操作ボタンを表示します。
    show_operations($id, $data, $operation, $post_file, $token);
    //  「学生情報一覧に戻る」リンクを表示します。
    show_bottom(true);

?>