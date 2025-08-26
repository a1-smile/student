<?php
//  student_edit.php は学生情報を編集または削除するかを確認するページです。
//  画面遷移は、
//  遷移元は index.php からの POST のみです。
// <form action="student_edit.php" method="post" class="table-form">
//     <input type="hidden" name="id" value="{$id}">
//     <input type="hidden" name="data" value="delete">
//     <input type="hidden" name="csrf_token" value="{$token}">
//     <button type="submit">削除確認へ...</button>
// </form>
    
// <form action="student_edit.php" method="post" class="table-form">
//     <input type="hidden" name="id" value="{$id}">
//     <input type="hidden" name="data" value="update">
//     <input type="hidden" name="csrf_token" value="{$token}">
//     <button type="submit">編集確認へ...</button>
// </form>
//  POST で受け取る値は、id, data, csrf_token です。

//  遷移先は条件分岐して、
//  student_update.php または student_delete.php です。
//  メソッドは post です。
//  送信する値は、id, data, csrf_token です。
// function show_operations($id,$data,$operation, $post_file, $token) {
//         echo <<<OPERATIONS
//         <form action="{$post_file}" method="post" class="operation-form">
//             <input type="hidden" name="id" value="{$id}">
//             <input type="hidden" name="data" value="{$data}">
//             <input type="hidden" name="csrf_token" value="{$token}">
//             <input type="submit" value="{$operation}">
//         </form>
//         OPERATIONS;
//     }

//  データベース操作は、

// セッション開始前に安全なクッキー設定
// ただし、開発環境ではHTTPSが使えない場合もあります。
// その場合は、'secure' => false に設定します。
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

//  セッションを開始します。
session_start();

//  共通ファイルを読み込みます。

try {
    //  __DIR__ は、現在のスクリプトが存在するディレクトリのパスを取得します。
    $common_file = __DIR__ . "/common.php";
    
    // セキュリティチェック：ディレクトリトラバーサル対策
    //  realpath() は、指定されたパスの正規化を行い、
    //  シンボリックリンクを解決し、相対パスを絶対パスに変換します。
    $real_common_path = realpath($common_file);
    $real_base_path = realpath(__DIR__);
    
    if ($real_common_path === false) {
        throw new RuntimeException('common.phpのパスが無効です');
    }
    //  strpos() は、文字列内での位置を検索します。
    //  real_common_path が real_base_path のサブパスであることを確認します。
    //  これにより、ディレクトリトラバーサル攻撃を防ぎます。
    if (strpos($real_common_path, $real_base_path) !== 0) {
        throw new SecurityException('不正なファイルパスが指定されました');
    }
    
    // ファイル存在・権限チェック
    if (!file_exists($real_common_path)) {
        throw new RuntimeException('common.phpが見つかりません: ' . $real_common_path);
    }
    
    if (!is_readable($real_common_path)) {
        throw new RuntimeException('common.phpの読み込み権限がありません: ' . $real_common_path);
    }
    
    if (!is_file($real_common_path)) {
        throw new RuntimeException('common.phpが通常ファイルではありません: ' . $real_common_path);
    }
    
    // ファイルサイズチェック（異常に大きなファイルの検出）
    $file_size = filesize($real_common_path);
    if ($file_size === false) {
        throw new RuntimeException('common.phpのファイルサイズを取得できません');
    }
    
    if ($file_size > 1024 * 1024) { // 1MB以上
        throw new RuntimeException('common.phpのファイルサイズが異常です: ' . $file_size . ' bytes');
    }
    
    if ($file_size === 0) {
        throw new RuntimeException('common.phpが空ファイルです');
    }
    
    // ファイル読み込み
    require_once $real_common_path;
    
    // 重要な定義の存在確認
    $required_classes = ['DBManager', 'BusinessLogicException', 'SecurityException', 'CSRFException'];
    $required_functions = ['handle_session_timeout', 'show_top', 'show_student', 'show_operations', 'show_bottom'];
    
    foreach ($required_classes as $class_name) {
        if (!class_exists($class_name)) {
            throw new RuntimeException("必要なクラスが定義されていません: {$class_name}");
        }
    }
    
    foreach ($required_functions as $function_name) {
        if (!function_exists($function_name)) {
            throw new RuntimeException("必要な関数が定義されていません: {$function_name}");
        }
    }
    
    // $dbmに DBManagerをインスタンス化して代入できたかを確認します。
    if (!isset($dbm) || !($dbm instanceof DBManager)) {
        throw new RuntimeException('データベースマネージャーが正しく初期化されていません');
    }
    
} catch (SecurityException $e) {
    // セキュリティ関連エラー
    error_log("セキュリティエラー - common.php読み込み - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", メッセージ: " . $e->getMessage());
    die('セキュリティエラーが発生しました。管理者に通報されました。');
    
} catch (ParseError $e) {
    // PHP構文エラー
    error_log("構文エラー - common.php - ファイル: " . $e->getFile() . ", 行: " . $e->getLine() . ", メッセージ: " . $e->getMessage());
    die('設定ファイルに構文エラーがあります。管理者にお問い合わせください。<br>
         エラーID: ' . uniqid());
         
} catch (Error $e) {
    // Fatal Error
    error_log("致命的エラー - common.php - メッセージ: " . $e->getMessage() . ", ファイル: " . $e->getFile() . ", 行: " . $e->getLine());
    die('システムの初期化でエラーが発生しました。管理者にお問い合わせください。<br>
         エラーID: ' . uniqid());
         
} catch (RuntimeException $e) {
    // ファイル関連・初期化エラー
    error_log("初期化エラー - common.php - メッセージ: " . $e->getMessage() . ", 現在ディレクトリ: " . __DIR__);
    die('システムファイルの読み込みまたは初期化に失敗しました。<br>
         管理者にお問い合わせください。<br>
         エラーID: ' . uniqid());
         
} catch (Exception $e) {
    // その他の予期しないエラー
    error_log("予期しないエラー - common.php読み込み - メッセージ: " . $e->getMessage() . ", トレース: " . $e->getTraceAsString());
    die('システムの初期化で予期しないエラーが発生しました。管理者にお問い合わせください。<br>
         エラーID: ' . uniqid());
}


//  session timeout を設定します。
handle_session_timeout();
    

//  ユーザーエージェントをチェックする
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} elseif (!hash_equals($_SESSION['user_agent'], $_SERVER['HTTP_USER_AGENT'])) {
    // ユーザーエージェントが前回と異なる場合はセッションを破棄
    session_unset();
    session_destroy();
    die('不正なアクセスです。新しい端末からのアクセス、もしくは通信環境が変わったため、再入力が必要です');
}


// IPアドレスの先頭部分をチェックする
// ここでは、IPv4とIPv6の両方に対応した方法を示します。
//  ip アドレスがipv4 かipv6をチェック

try {
    // 現在のアクセス元IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (empty($ip)) {
        throw new RuntimeException('IPアドレスが取得できません');
    }
    
    // 初回アクセス時の処理
    if (!isset($_SESSION['ip_prefix'])) {
        // IPアドレスの形式判定と処理
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            if (count($parts) < 3) {
                throw new RuntimeException('IPv6アドレスの形式が不正です');
            }
            $_SESSION['ip_prefix'] = implode(':', array_slice($parts, 0, 3));
            
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) < 2) {
                throw new RuntimeException('IPv4アドレスの形式が不正です');
            }
            $_SESSION['ip_prefix'] = implode('.', array_slice($parts, 0, 2));
            
        } else {
            throw new InvalidArgumentException('サポートされていないIPアドレス形式: ' . $ip);
        }
        
    } else {
        // 既存のセッションとの比較
        $stored_prefix = $_SESSION['ip_prefix'];
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            if (count($parts) < 3) {
                throw new RuntimeException('IPv6アドレスの形式が不正です');
            }
            $current_prefix = implode(':', array_slice($parts, 0, 3));
            
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) < 2) {
                throw new RuntimeException('IPv4アドレスの形式が不正です');
            }
            $current_prefix = implode('.', array_slice($parts, 0, 2));
            
        } else {
            throw new InvalidArgumentException('サポートされていないIPアドレス形式: ' . $ip);
        }
        
        // プレフィックスの比較
        if (!hash_equals($current_prefix, $stored_prefix)) {
            // セキュリティログの記録
            error_log("IPプレフィックス不一致 - 保存済み: {$stored_prefix}, 現在: {$current_prefix}, セッションID: " . session_id());
            
            session_unset();
            session_destroy();
            die('新しい接続元からのアクセス、もしくは通信環境が変わったため、再入力が必要です<br>
                 <a href="index.php">学生一覧に戻る</a>');
        }
    }
    
} catch (InvalidArgumentException $e) {
    // 不正なIPアドレス形式
    error_log("不正なIPアドレス - IP: {$ip}, エラー: " . $e->getMessage() . ", UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
    
    session_unset();
    session_destroy();
    die('不正なアクセスです。サポートされていないネットワーク環境からのアクセスです。<br>
         <a href="index.php">学生一覧に戻る</a>');
         
} catch (RuntimeException $e) {
    // システムエラー
    error_log("IPアドレス処理エラー - IP: {$ip}, エラー: " . $e->getMessage() . ", セッションID: " . session_id());
    
    session_unset();
    session_destroy();
    die('システムエラーが発生しました。ネットワーク環境を確認してください。<br>
         エラーID: ' . uniqid() . '<br>
         <a href="index.php">学生一覧に戻る</a>');
         
} catch (Exception $e) {
    // その他の予期しないエラー
    error_log("予期しないエラー - IPアドレス処理 - IP: {$ip}, エラー: " . $e->getMessage());
    
    session_unset();
    session_destroy();
    die('システムエラーが発生しました。管理者にお問い合わせください。<br>
         エラーID: ' . uniqid() . '<br>
         <a href="index.php">学生一覧に戻る</a>');
}


// CSRF対策
try {
    // タイムスタンプベースのトークン検証も追加
    
    // 1. セッショントークンの存在・基本チェック
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        throw new SecurityException('CSRFトークンまたはタイムスタンプがセッションに設定されていません');
    }
    
    $session_token = $_SESSION['csrf_token'];
    $token_time = $_SESSION['csrf_token_time'];
    
    // 2. トークンの有効期限チェック（30分）
    if ((time() - $token_time) > 1800) {
        throw new SecurityException('CSRFトークンの有効期限が切れています');
    }
    
    // 3. POSTトークンの基本チェック
    if (!isset($_POST['csrf_token'])) {
        throw new SecurityException('POSTデータにCSRFトークンが含まれていません');
    }
    
    $post_token = $_POST['csrf_token'];
    
    // 4. トークンの形式・長さチェック
    if (!is_string($session_token) || !is_string($post_token)) {
        throw new SecurityException('CSRFトークンが文字列ではありません');
    }
    
    if (strlen($session_token) !== 64 || strlen($post_token) !== 64) {
        throw new SecurityException('CSRFトークンの長さが異常です');
    }
    
    if (!ctype_xdigit($session_token) || !ctype_xdigit($post_token)) {
        throw new SecurityException('CSRFトークンに無効な文字が含まれています');
    }
    
    // 5. リファラーチェック（追加のセキュリティ）
    $referer = $_SERVER['HTTP_REFERER'] ?? ''; //  アクセス元のURL
    $host = $_SERVER['HTTP_HOST'] ?? '';       //  アクセス先のドメイン
    
    //  strpos() は、部分文字列の位置を検索します。
    //  文字列の一致を探す関数。
    if (empty($referer) || strpos($referer, $host) === false) {
        // 警告レベル（ブロックはしない）
        error_log("警告: 不審なリファラー - リファラー: {$referer}, ホスト: {$host}, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    
    // 6. レート制限チェック（同一IPからの連続アクセス）
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_key = "csrf_attempt_{$ip}";
    
    if (!isset($_SESSION[$rate_key])) {
        $_SESSION[$rate_key] = ['count' => 0, 'last_time' => time()];
    }
    
    $rate_data = $_SESSION[$rate_key];
    $current_time = time();
    
    // 1分間に5回以上の試行は異常
    if (($current_time - $rate_data['last_time']) < 60 && $rate_data['count'] >= 5) {
        throw new SecurityException('CSRFトークン検証の試行回数が上限を超えました');
    }
    
    // レート制限カウンターを更新
    if (($current_time - $rate_data['last_time']) >= 60) {
        $_SESSION[$rate_key] = ['count' => 1, 'last_time' => $current_time];
    } else {
        $_SESSION[$rate_key]['count']++;
        //  時間をリセットすることにより、連続攻撃を防止
        //  攻撃者が1分間隔で攻撃を仕掛けるのを防ぐ
        $_SESSION[$rate_key]['last_time'] = $current_time;
    }
    
    // 7. トークンの一致確認
    if (!hash_equals($post_token, $session_token)) {
        throw new CSRFException('CSRFトークンが一致しません - 攻撃の可能性');
    }
    
    // 8. 使用済みトークンの記録（リプレイ攻撃防止）
    if (!isset($_SESSION['used_tokens'])) {
        $_SESSION['used_tokens'] = [];
    }
    
    if (in_array($post_token, $_SESSION['used_tokens'])) {
        throw new CSRFException('既に使用済みのCSRFトークンです - リプレイ攻撃の可能性');
    }
    
    // 使用済みトークンリストに追加（最大10個まで保持）
    $_SESSION['used_tokens'][] = $post_token;
    if (count($_SESSION['used_tokens']) > 10) {
        array_shift($_SESSION['used_tokens']);
    }
    
    // 9. トークンを使い捨て
    unset($_SESSION['csrf_token']);
    unset($_SESSION['csrf_token_time']);
    $session_token = null;
    unset($_POST['csrf_token']);
    $post_token = null;
    
    // レート制限カウンターをリセット（成功時）
    unset($_SESSION[$rate_key]);
    
    // 成功ログ（デバッグ用）
    // error_log("CSRF検証成功 - IP: {$ip}, セッションID: " . session_id());
    
} catch (CSRFException $e) {
    // CSRF攻撃の詳細ログ
    $attack_details = [
        'type' => 'CSRF_ATTACK',
        'message' => $e->getMessage(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
        'session_id' => session_id(),
        'post_token_length' => isset($_POST['csrf_token']) ? strlen($_POST['csrf_token']) : 0,
        'session_token_exists' => isset($_SESSION['csrf_token']),
        'timestamp' => date('Y-m-d H:i:s'),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ];
    
    error_log("CSRF攻撃検出: " . json_encode($attack_details, JSON_UNESCAPED_UNICODE));
    
    // セッション完全破棄
    session_unset();
    session_destroy();
    
    die('CSRF攻撃が検出されました。<br>
         セキュリティ上の理由により処理を中断します。<br>
         この攻撃は記録され、管理者に通報されました。<br>
         攻撃検出ID: ' . uniqid() . '<br>
         正常な操作を行う場合は、<a href="index.php">学生一覧画面</a>から再開してください。');

} catch (SecurityException $e) {
    // その他のセキュリティエラー
    $security_details = [
        'type' => 'SECURITY_ERROR',
        'message' => $e->getMessage(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'session_id' => session_id(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    error_log("セキュリティエラー: " . json_encode($security_details, JSON_UNESCAPED_UNICODE));
    
    session_unset();
    session_destroy();
    
    die('セキュリティエラーが検出されました。<br>
         不正なアクセスまたはセッション異常の可能性があります。<br>
         エラーID: ' . uniqid() . '<br>
         <a href="index.php">学生一覧画面</a>から正常に操作してください。');
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
        BusinessLogicException::ERR_STUDENT_NOT_FOUND,
        [
            'id' => $id,
            'session_id' => session_id(),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            'stamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]
    );
}   //  BusinessLogicException::ERR_STUDENT_NOT_FOUND
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
        case BusinessLogicException::ERR_STUDENT_NOT_FOUND:
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
//         case BusinessLogicException::ERR_STUDENT_NOT_FOUND:
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