<?php
// csrf_protection_only_post.php
/**
 * csrf_protection_only_post.php
 * POSTメソッドのみを想定したページ
 * に対するCSRF対策を実施します。
 */
try {
    // データベース接続状態の確認
    if (!($dbm instanceof DBManager)) {
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




//  以下のCSRF対策にデータベースを使用するので、
//  PDOオブジェクトを取得します。
$dbm->connect();
$pdo = $dbm->get_db();




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
    'ip' => [ 'window' => 300, 'max_failures' => 1000, 'soft_failure' => 300], // 5分で1000回
    'session' => [ 'window' => 300, 'max_failures' => 10, 'soft_failure' => 3], // 5分で10回
    'device' => [ 'window' => 300, 'max_failures' => 30, 'soft_failure' => 10], // 5分で30回
    'ip_prefix' => [ 'window' => 300, 'max_failures' => 5000, 'soft_failure' => 1500], // 5分で5000回
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
    try{
    content_type_check();
    } catch (CSRFException $e) {
        record_failure($pdo, $ip_key);
        record_failure($pdo, $session_key);
        record_failure($pdo, $device_key);
        record_failure($pdo, $ip_prefix_key);
        //  unset トークン
        unset_token();
        //遷移先でステータスを設定する設計
        header('Location: error_page.php', true, 302); exit;
    }

// ここから先は「正規のフォーム POST」だけ通過

//  Origin/Hostの整合性確認
    try{
    origin_host_check();
    } catch (CSRFException $e) {
        record_failure($pdo, $ip_key);
        record_failure($pdo, $session_key);
        record_failure($pdo, $device_key);
        record_failure($pdo, $ip_prefix_key);
        //  unset トークン
        unset_token();
        //遷移先でステータスを設定する設計
        header('Location: error_page.php', true, 302); exit;
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
    header('Location: error_page.php', true, 302); exit;
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

    if ($security_level === SecurityException::LEVEL_CRITICAL) {
        //  致命的レベルの場合は、block_page.php にリダイレクト
        //  遷移先でステータスを設定する設計
        header('Location: block_page.php', true, 302); exit;
    }elseif ($security_level === SecurityException::LEVEL_HIGH) {
        //  recaptcha にリダイレクト
        //  遷移先でステータスを設定する設計
        header('Location: recaptcha.php', true, 302); exit;
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
        unset_token();

        // 302で遷移。最終ステータスは遷移先で設定する設計
        header('Location: error_page.php', true, 302); exit;
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
    
    