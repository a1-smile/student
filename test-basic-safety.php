<?php

function test_user_agent_check(PDO $pdo): void {
    // UA未送信は警告のみ（他レイヤで防御する方針は維持）

    /* 一方で、ua が session 継続中に代わるなどの
      異常検出は行うと、セキュリティ強化に寄与する*/

    $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
     error_log("test_user_agent_check: UA: " . $current_user_agent . ", IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", セッションID: " . (session_id() ?: 'unknown'));
    if ($current_user_agent === '') {
        error_log(sprintf(
            '警告: User-Agentが未送信です - IP:%s, URI:%s, セッションID:%s',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['REQUEST_URI'] ?? 'unknown',
            session_id() ?: 'unknown'
        ));
        return;
    }

    $session_id = session_id() ?: 'unknown';
    error_log("test_user_agent_check: セッションID: " . $session_id);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';


    // 初回アクセス時に simple UA を記録

    //  現在の simple UA を取得
    $current_simple_ua = get_simple_ua($current_user_agent);
    error_log("test_user_agent_check: current_simple_ua: " . $current_simple_ua);
    //  first simple UA が未設定なら現在の simple UA を保存
    //  この処理は保険的に行う（本来は設定されているはず）
    if (!isset($_SESSION['first_simple_ua'])) {
        $_SESSION['first_simple_ua'] = $current_simple_ua;
    }
    //  first simple UA を取得
    $first_simple_ua = $_SESSION['first_simple_ua'];
    error_log("test_user_agent_check: first_simple_ua: " . $first_simple_ua);
    // UA 不一致判定
    //  ミスマッチなら 1,  マッチなら 0
    $is_ua_mismatch = ($first_simple_ua !== $current_simple_ua) ? 1 : 0;

    // 直近1分間のアクセス数 / UA不一致数の初期化
    $access_count_last_minute   = ['session_id_access_count'=>0, 'ip_address_access_count'=>0];
    $mismatch_count_last_minute = ['session_id_mismatch_count'=>0, 'ip_address_mismatch_count'=>0];

        

    // アクセスログを記録
    ua_log_access($pdo, $session_id, $ip_address, $current_simple_ua, $is_ua_mismatch);

    // 10回に1回古いログを削除（1日より前のデータ）
    ua_maybe_cleanup_old_logs($pdo);

            // 直近1分間のアクセス数 / UA不一致数を取得
    $access_count_last_minute = ua_get_access_count_last_minute($pdo, $session_id, $ip_address);


    $mismatch_count_last_minute = ua_get_mismatch_count_last_minute($pdo, $session_id, $ip_address);

    

    // しきい値
    // 共有 ip アドレス環境を考慮して、
    //  ip アドレスベースのしきい値は
    // セッションIDベースのしきい値よりも緩やかに設定
    // （例: セッションIDベースの10倍）
    $ACCESS_THRESHOLD_ERROR       = ['session'=>30, 'ip'=>300];  // 1分間のアクセス回数でエラー扱い


    //  test 用閾値 テストのみ有効化
    $ACCESS_THRESHOLD_ERROR       = ['session'=>5, 'ip'=>7];  // 1分間のアクセス回数でエラー扱い


    $ACCESS_THRESHOLD_RECAPTCHA   = ['session'=>60, 'ip'=>600]; // 1分間のアクセス回数で reCAPTCHA へ


    //  test 用閾値 テストのみ有効化
    $ACCESS_THRESHOLD_RECAPTCHA   = ['session'=>10, 'ip'=>15];


    $MISMATCH_THRESHOLD_RECAPTCHA = ['session'=>5,  'ip'=>50]; // UA不一致回数で reCAPTCHA へ


    //  test 用閾値 テストのみ有効化
    $MISMATCH_THRESHOLD_RECAPTCHA = ['session'=>2,  'ip'=>3];


    // access が $ACCESS_THRESHOLD_RECAPTCHA を超えた場合は recaptcha.php へリダイレクト
    if ($access_count_last_minute['session_id_access_count'] >= $ACCESS_THRESHOLD_RECAPTCHA['session']) {
        $context = ua_build_context([
            'reason'                    => 'ACCESS_RATE_RECAPTCHA',
            'access_count_last_minute[\'session_id_access_count\']'  => $access_count_last_minute['session_id_access_count'],
            'mismatch_count_last_minute[\'session_id_mismatch_count\']'=> $mismatch_count_last_minute['session_id_mismatch_count'],
            'redirect_target'           => 'recaptcha.php',
        ]);
        $redirect_target = SessionHijackingException::REDIRECT_TARGET_RECAPTCHA;

        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（高頻度アクセス: reCAPTCHA 推奨）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }

    if ($access_count_last_minute['ip_address_access_count'] >= $ACCESS_THRESHOLD_RECAPTCHA['ip']) {
        $context = ua_build_context([
            'reason'                    => 'ACCESS_RATE_RECAPTCHA',
            'access_count_last_minute[\'ip_address_access_count\']'  => $access_count_last_minute['ip_address_access_count'],
            'mismatch_count_last_minute[\'ip_address_mismatch_count\']'=> $mismatch_count_last_minute['ip_address_mismatch_count'],
            'redirect_target'           => 'recaptcha.php',
        ]);

        $redirect_target = SessionHijackingException::REDIRECT_TARGET_RECAPTCHA;
        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（高頻度アクセス: reCAPTCHA 推奨）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }


    // mismatch が $MISMATCH_THRESHOLD_RECAPTCHA を超えた場合は recaptcha.php へリダイレクト
    if ($mismatch_count_last_minute['session_id_mismatch_count'] >= $MISMATCH_THRESHOLD_RECAPTCHA['session']) {
        $context = ua_build_context([
            'reason'                    => 'UA_MISMATCH_RECAPTCHA',
            'access_count_last_minute[\'session_id_access_count\']'  => $access_count_last_minute['session_id_access_count'],
            'mismatch_count_last_minute[\'session_id_mismatch_count\']'=> $mismatch_count_last_minute['session_id_mismatch_count'],
            'redirect_target'           => 'recaptcha.php',
        ]);

        $redirect_target = SessionHijackingException::REDIRECT_TARGET_RECAPTCHA;
        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（UA不一致多発: reCAPTCHA 推奨）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }

    if ($mismatch_count_last_minute['ip_address_mismatch_count'] >= $MISMATCH_THRESHOLD_RECAPTCHA['ip']) {
        $context = ua_build_context([
            'reason'                    => 'UA_MISMATCH_RECAPTCHA',
            'access_count_last_minute[\'ip_address_access_count\']'  => $access_count_last_minute['ip_address_access_count'],
            'mismatch_count_last_minute[\'ip_address_mismatch_count\']'=> $mismatch_count_last_minute['ip_address_mismatch_count'],
            'redirect_target'           => 'recaptcha.php',
        ]);

        $redirect_target = SessionHijackingException::REDIRECT_TARGET_RECAPTCHA;
        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（UA不一致多発: reCAPTCHA 推奨）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }

    

    // 単発の UA 不一致は error_page.php へリダイレクト
    if ($is_ua_mismatch === 1) {
        $context = ua_build_context([
            'reason'          => 'UA_MISMATCH_SINGLE',
            'first_simple_ua' => $first_simple_ua,
            'current_simple_ua'=> $current_simple_ua,
            'redirect_target' => 'error_page.php',
        ]);

        $redirect_target = SessionHijackingException::REDIRECT_TARGET_ERROR_PAGE;
        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（UA不一致）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }

    // access が $ACCESS_THRESHOLD_ERROR を超えた場合は error_page.php へリダイレクト
    if ($access_count_last_minute['session_id_access_count'] >= $ACCESS_THRESHOLD_ERROR['session']) {
        $context = ua_build_context([
            'reason'                    => 'ACCESS_RATE_WARNING',
            'access_count_last_minute[\'session_id_access_count\']'  => $access_count_last_minute['session_id_access_count'],
            'mismatch_count_last_minute[\'session_id_mismatch_count\']'=> $mismatch_count_last_minute['session_id_mismatch_count'],
            'redirect_target'           => 'error_page.php',
        ]);

        $redirect_target = SessionHijackingException::REDIRECT_TARGET_ERROR_PAGE;
        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（高頻度アクセス）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }

    if ($access_count_last_minute['ip_address_access_count'] >= $ACCESS_THRESHOLD_ERROR['ip']) {
        $context = ua_build_context([
            'reason'                    => 'ACCESS_RATE_WARNING',
            'access_count_last_minute[\'ip_address_access_count\']'  => $access_count_last_minute['ip_address_access_count'],
            'mismatch_count_last_minute[\'ip_address_mismatch_count\']'=> $mismatch_count_last_minute['ip_address_mismatch_count'],
            'redirect_target'           => 'error_page.php',
        ]);

        $redirect_target = SessionHijackingException::REDIRECT_TARGET_ERROR_PAGE;
        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（高頻度アクセス）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }

    // ここまで到達した場合は異常なし
    return;
}


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



//  データベース処理のために $pdo を取得します。
try{
    $dbm->connect();
    $pdo = $dbm->get_db();
  }catch(PDOException $e){
    $error_id = uniqid('db_');
    error_log('DB接続に失敗: ' . $e->getMessage());
    die("システムエラーが発生しました。エラーID: $error_id");
  }catch(Exception $e){
    $error_id = uniqid('unexpected_');
    error_log('予期しないエラーが発生しました: ' .$e->getMessage());
    die("予期しないエラーが発生しました。エラーID: $error_id");
  }



//  ユーザーエージェントをチェックする
try{
    test_user_agent_check($pdo);
} catch (SessionHijackingException $e) {
    $log_message = $e->getLogMessage();
    error_log($log_message);
    
    //  session 完全廃棄
    session_unset();
    session_destroy();
    session_write_close();

    $redirect_target = $e->getRedirectTarget();
    switch ($redirect_target) {
        case SessionHijackingException::REDIRECT_TARGET_RECAPTCHA:
            http_response_code(302);  // Found 一時的リダイレクト
            //  呼び出し先でアクセス禁止403のステータスコードを設定する方針
            header('Location: recaptcha.php', true, 302);
            exit;
        case SessionHijackingException::REDIRECT_TARGET_ERROR_PAGE:
        default:
            http_response_code(302);  // Found 一時的リダイ302
            //  呼び出し先でアクセス禁止403のステータスコードを設定する方針
            // http_response_code(403);  // Forbidden アクセス禁止
            header('Location: error_page.php', true, 302);
            exit;
    }
    
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

error_log("ユーザーエージェントチェック完了 - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
// // IPアドレスの先頭部分をチェックする
// // ここでは、IPv4とIPv6の両方に対応した方法を示します。

// try {
//     $ipv4_blocks = 2;
//     $ipv6_blocks = 3;
//     ip_check_for_session($ipv4_blocks, $ipv6_blocks);
// } catch (SessionHijackingException $e) {
//     $log_message = $e->getLogMessage();
//     error_log($log_message);
    
//     //  session 完全廃棄
//     session_unset();
//     session_destroy();
//     session_write_close();
//     http_response_code(403);  // Forbidden アクセス禁止
//     die('セッションハイジャック攻撃検出<br>
//          セキュリティ上の理由により処理を中断します。<br>
//          この攻撃は記録され、管理者に通報されました。<br>
//          攻撃検出ID: ' . uniqid() . '<br>
//          正常な操作を行う場合は、トップページから再開してください。');
// } catch (InvalidArgumentException $e) {
//     // 不正なIPアドレス形式
//     error_log("不正なIPアドレス - IP:".($_SERVER['REMOTE_ADDR'] ?? 'unknown').", エラー: " . $e->getMessage() . ", UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
    
//     session_unset();
//     session_destroy();
//     session_write_close();
//     http_response_code(400);  // Bad Request 不正なリクエスト
//     die('不正なアクセスです。サポートされていないネットワーク環境からのアクセスです。');
         
// } catch (RuntimeException $e) {
//     // システムエラー
    
//     $session_id = session_id();
//     error_log("IPアドレス処理エラー - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", エラー: " . $e->getMessage() . ", セッションID: " . $session_id);

//     session_unset();
//     session_destroy();
//     session_write_close();
//     http_response_code(500);  // Internal Server Error 内部サーバーエラー
//     die('システムエラーが発生しました。ネットワーク環境を確認してください。<br>
//          エラーID: ' . uniqid() . '<br>
//          <a href="index.php">学生一覧に戻る</a>');
         
// } catch (Exception $e) {
//     // その他の予期しないエラー
//     error_log("予期しないエラー - IPアドレス処理 - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", エラー: " . $e->getMessage());
    
//     session_unset();
//     session_destroy();
//     session_write_close();
//     http_response_code(500);  // Internal Server Error 内部サーバーエラー
//     die('システムエラーが発生しました。管理者にお問い合わせください。<br>
//          エラーID: ' . uniqid() . '<br>
//          <a href="index.php">学生一覧に戻る</a>');
// }
// //  以上で
// //  共通ファイル読み込み
// //  セキュアなクッキー設定
// //  セッション開始
// //  session timeout 設定
// //  想定外のエラーに備えたエラーハンドラ
// //  ユーザーエージェントチェック
// //  IPアドレスの先頭部分チェック
// //  が完了しました。








// //  CSRF対策
// //  4種のrate_key を定義します。

// /*------------------------------------
// device_id クッキー準備（1年有効）
// ------------------------------------*/
// set_device_id_cookie();
// /*------------------------------------
// IPプレフィックス取得
// ------------------------------------*/
// // IPプレフィックス取得
// $ipv4_blocks = $_ENV['IP_CHECK_IPV4_BLOCKS'] ?? 2;
// $ipv6_blocks = $_ENV['IP_CHECK_IPV6_BLOCKS'] ?? 3;

// $ip_prefix = get_ip_prefix_for_session($ipv4_blocks, $ipv6_blocks);
// /*------------------------------------
// session_id hash化
// ------------------------------------*/
// $session_id = session_id();
// $session_id_hash = hash('sha256', $session_id);
// /*------------------------------------
// レート制限用キー生成（4層）
// ------------------------------------*/
// $ip_key        = "ip:" . $_SERVER['REMOTE_ADDR'];
// $session_key   = "session:" . $session_id_hash;
// $device_key    = "device:" . ($_COOKIE['device_id'] ?? '');
// $ip_prefix_key = "ip_prefix:" . $ip_prefix;

// /*------------------------------------
// 閾値設定
// */
// const RATE_LIMITS = [
//     'ip' => [ 'window' => 300, 'max_failures' => 1000, 'soft_failure' => 300], // 5分で1000回
//     'session' => [ 'window' => 300, 'max_failures' => 10, 'soft_failure' => 3], // 5分で10回
//     'device' => [ 'window' => 300, 'max_failures' => 30, 'soft_failure' => 10], // 5分で30回
//     'ip_prefix' => [ 'window' => 300, 'max_failures' => 5000, 'soft_failure' => 1500], // 5分で5000回
// ];

// const BLOCK_DURATION_SESSION = 1800; // 30分
// const BLOCK_DURATION_DEVICE  = 1800; // 30分


// // メソッド検証は最初に実施（GET等を早期排除）
// try {
//     if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//         throw new SecurityException('POSTメソッド以外のアクセスです');
//     }
// } catch (SecurityException $e) {
//     http_response_code(405);
//     header('Allow: POST');
//     header('Cache-Control: no-cache, no-store, must-revalidate');
//     header('Pragma: no-cache');
//     header('Expires: 0');
//     error_log(sprintf(
//         "セキュリティエラー - メソッド検証 - エラー: %s, メソッド: %s, IP: %s, UA: %s, リファラー: %s, セッションID: %s, 時刻: %s",
//         $e->getMessage(),
//         $_SERVER['REQUEST_METHOD'] ?? 'unknown',
//         $_SERVER['REMOTE_ADDR'] ?? 'unknown',
//         $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
//         $_SERVER['HTTP_REFERER'] ?? 'unknown',
//         session_id(),
//         date('Y-m-d H:i:s')
//     ));
//     session_unset();
//     session_destroy();
//     die('405 Method Not Allowed<br>このリソースではPOSTメソッドのみサポートされています。<br>不正なアクセス方法が検出されました。<br>エラーID: ' . uniqid() . '<br><a href="index.php">学生一覧画面</a>から正しい手順で操作してください。');
// }

// // ----- Content-Type のチェック -----
//     try{
//     content_type_check();
//     } catch (CSRFException $e) {
//         record_failure($pdo, $ip_key);
//         record_failure($pdo, $session_key);
//         record_failure($pdo, $device_key);
//         record_failure($pdo, $ip_prefix_key);
//         //  unset トークン
//         unset_token();
//         //遷移先でステータスを設定する設計
//         header('Location: error_page.php', true, 302); exit;
//     }

// // ここから先は「正規のフォーム POST」だけ通過

// //  Origin/Hostの整合性確認
//     try{
//     origin_host_check();
//     } catch (CSRFException $e) {
//         record_failure($pdo, $ip_key);
//         record_failure($pdo, $session_key);
//         record_failure($pdo, $device_key);
//         record_failure($pdo, $ip_prefix_key);
//         //  unset トークン
//         unset_token();
//         //遷移先でステータスを設定する設計
//         header('Location: error_page.php', true, 302); exit;
//     }





// try {
//     // token の型式チェック
//     validate_csrf_token();
// } catch (CSRFException $e) {
//     //  失敗として記録
//     record_failure($pdo, $ip_key);
//     record_failure($pdo, $session_key);
//     record_failure($pdo, $device_key);
//     record_failure($pdo, $ip_prefix_key);

//     //  unset トークン
//     unset_token();

//     //  遷移先でステータスを設定する設計
//     header('Location: error_page.php', true, 302); exit;
// }  
//     // リファラーチェック（追加のセキュリティ）
//     $result = referer_check();
//     if (!$result) {
//         // 警告レベル（ブロックはしない）
//         // 補助的な監視に留めるため、処理を止めない。
//         $referer = $_SERVER['HTTP_REFERER'] ?? 'unknown';
//         $host = $_SERVER['HTTP_HOST'] ?? 'unknown';
//         error_log("警告: 不審なリファラー - リファラー: {$referer}, ホスト: {$host}, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
//     }
    
//     // レート制限チェック
// try {
//     rate_limit_check($pdo, $ip_key, $session_key, $device_key, $ip_prefix_key);
// } catch (CSRFException $e) {
//     //  失敗として記録
//     record_failure($pdo, $ip_key);
//     record_failure($pdo, $session_key);
//     record_failure($pdo, $device_key);
//     record_failure($pdo, $ip_prefix_key);

//     //  unset トークン
//     unset_token();

//     //  security level を取得
//     $security_level = $e->getSecurityLevel();

//     if ($security_level === SecurityException::LEVEL_CRITICAL) {
//         //  致命的レベルの場合は、block_page.php にリダイレクト
//         //  遷移先でステータスを設定する設計
//         header('Location: block_page.php', true, 302); exit;
//     }elseif ($security_level === SecurityException::LEVEL_HIGH) {
//         //  recaptcha にリダイレクト
//         //  遷移先でステータスを設定する設計
//         header('Location: recaptcha.php', true, 302); exit;
//     }   
// }
//     // トークンの一致確認
//     try {
//     $post_token = $_POST['csrf_token'];
//     $session_token = $_SESSION['csrf_token'];
//     if (!hash_equals($post_token, $session_token)) {
//         throw CSRFException::fromCurrentRequest(
//             'CSRFトークンが一致しません - 攻撃の可能性',
//             SecurityException::SEC_CSRF_ATTACK,
//             [], 
//             SecurityException::LEVEL_MEDIUM,
//             null);
//     }
//     } catch (CSRFException $e) {
//         //  失敗として記録
//         record_failure($pdo, $ip_key);
//         record_failure($pdo, $session_key);
//         record_failure($pdo, $device_key);
//         record_failure($pdo, $ip_prefix_key);
//         //  unset トークン
//         unset_token();

//         // 302で遷移。最終ステータスは遷移先で設定する設計
//         header('Location: error_page.php', true, 302); exit;
//     }

//     //  トークンの使用後破棄
//     unset_token();

//     //  以上で CSRF対策 が完了しました。

//     //  ログイン成功時はここで、session_regenerate_id(true) を実行。
//     //  これにより、セッションIDが新しいものに置き換えられ、
//     //  古いセッションIDは無効化されます。

//     //  DDoS 対策として
//     //  これ以前の処理でに必要上に
//     //  session_destroy()
//     //  session_unset()
//     //  session_start()
//     //  を実行しない方針です。
    
    
    
    
    

  


 

