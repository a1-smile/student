<?php
//  index.php では、データベースからすべての学生情報を取得し、表示します。
//  画面遷移は、student_edit.php （form タグで、メソッドは POST で、）
//            student_input.php （a タグで、）
//            form から送信するデーターは、データベースから取得した学生情報の
//            idと
//            csrf_token です。


// <form action="student_edit.php" method="post" class="table-form">
//             <input type="hidden" name="id" value="{$id}">
//             <input type="hidden" name="data" value="update">
//             <input type="hidden" name="csrf_token" value="{$token}">
//             <button type="submit">編集確認へ...</button>
//         </form>

//  <form action="student_edit.php" method="post" class="table-form">
//             <input type="hidden" name="id" value="{$id}">
//             <input type="hidden" name="data" value="delete">
//             <input type="hidden" name="csrf_token" value="{$token}">
//             <button type="submit">削除確認へ...</button>
//         </form>
//    


//  必要なファイルを読み込むのに必要な関数を定義します。
//  common.php にある safe_file_path() と重複読み込みを防ぐために
//  safe_file_path1() としています。
/**
 * safe_file_path1 - path validation and normalization
 * 
 * @param string $file_name 読み込むファイル名
 * （関数を実行するディレクトリを基準にした相対パス）
 * （同じディレクトリ内のファイルならファイル名）
 * @param array $options オプション設定
 * @throws InvalidArgumentException 引数が無効な場合
 * @throws RuntimeException ファイル操作エラーの場合
 * @throws LogicException ファイルが既に読み込まれている場合
 * @return string $real_path 正規化されたファイルパス
 */
//  使用例
// try {
//     // 1. 安全なファイルパスの取得
//     $safe_path = safe_file_path('common.php');
    
//     // 2. グローバルスコープでrequire_once実行
//     require_once $safe_path;
// } catch (InvalidArgumentException $e) {
//     die("引数エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
// } catch (LogicException $e) {
//     die("ℹ️ ファイルは既に読み込み済みです: " . $e->getMessage() . "<br>");    
// } catch (RuntimeException $e) {
//     die("ファイル読み込みエラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
// } catch (ParseError $e) {
//     die("構文エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
// } catch (Error $e) {
//     die("致命的エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
// } catch (Exception $e) {
//     die("予期しないエラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
// }

function safe_file_path($file_name, $options = []) {
    // デフォルト設定
    $default_options = [
        'max_file_size' => 1024 * 1024,  // 1MB
        'allowed_extensions' => ['php', 'inc'],
        'check_syntax' => false,  // 事前構文チェック（重い処理）
        'log_level' => 'error'    // ログレベル
    ];
    
    $options = array_merge($default_options, $options);
    
    try {
        // 1. 入力値検証
        if (empty($file_name) || !is_string($file_name)) {
            throw new InvalidArgumentException('ファイル名が無効です');
        }
        
        if (strlen($file_name) > 255) {
            throw new InvalidArgumentException('ファイル名が長すぎます（255文字以内）');
        }
        
        // 2. 危険な文字の検出
        if (strpbrk($file_name, "\0\r\n\t") !== false) {
            throw new InvalidArgumentException('ファイル名に不正な文字が含まれています');
        }
        
        // 3. 拡張子チェック
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($file_extension, $options['allowed_extensions'], true)) {
            throw new InvalidArgumentException("許可されていない拡張子です: {$file_extension}");
        }
        
        // 4. パス構築と正規化
        $file_path = __DIR__ . DIRECTORY_SEPARATOR . $file_name;
        $real_path = realpath($file_path);
        
        if ($real_path === false) {
            throw new RuntimeException("ファイルが見つからないか、パスが無効です: {$file_name}");
        }
        
        // 5. 基本ディレクトリ内チェック（セキュリティ）
        $base_dir = realpath(__DIR__);
        if (strpos($real_path, $base_dir . DIRECTORY_SEPARATOR) !== 0) {
            throw new RuntimeException("ベースディレクトリ外のファイルです: {$real_path}");
        }
        
        // 6. ファイル存在確認（realpathでチェック済みだが明示的に）
        if (!file_exists($real_path)) {
            throw new RuntimeException("ファイルが存在しません: {$real_path}");
        }
        
        // 7. ファイル形式確認
        if (!is_file($real_path)) {
            throw new RuntimeException("指定されたパスはファイルではありません: {$real_path}");
        }
        
        // 8. 権限確認
        if (!is_readable($real_path)) {
            throw new RuntimeException("ファイルに読み取り権限がありません: {$real_path}");
        }
        
        // 9. ファイルサイズチェック
        $file_size = filesize($real_path);
        if ($file_size === false) {
            throw new RuntimeException("ファイルサイズの取得に失敗しました: {$real_path}");
        }
        
        if ($file_size > $options['max_file_size']) {
            throw new RuntimeException(
                "ファイルサイズが上限を超えています: " . 
                number_format($file_size) . " bytes (上限: " . 
                number_format($options['max_file_size']) . " bytes)"
            );
        }
        
        // 10. 重複読み込みチェック
        if (in_array($real_path, get_included_files(), true)) {            
                throw new LogicException("ファイルは既に読み込まれています: {$real_path}");        
        }
        
        // 11. 事前構文チェック（オプション）
        if ($options['check_syntax']) {
            $syntax_check = shell_exec("php -l " . escapeshellarg($real_path) . " 2>&1");
            if (strpos($syntax_check, 'No syntax errors') === false) {
                throw new RuntimeException("構文エラーが検出されました: {$real_path}");
            }
        }

        if ($options['log_level'] === 'info') {
            error_log("情報: ファイルパスの検証に成功しました: {$real_path}");
        }
        
        return $real_path; // 検証済みパスを返す
    
    } catch (InvalidArgumentException $e) {
            error_log("引数エラー - {$file_name}: " . $e->getMessage());
            throw $e; // 呼び出し元で処理
    
    } catch (LogicException $e) {            
        if ($options['log_level'] === 'error') {
            error_log("情報: ファイルは既に読み込まれています: {$file_name}");
        }
        throw $e; // 呼び出し元で処理    
    
    
    } catch (RuntimeException $e) {
        error_log("ファイル処理エラー - {$file_name}: " . $e->getMessage());
        throw $e; // 呼び出し元で処理
    } catch (Exception $e) {
        $error_msg = "予期しないエラー - {$file_name}: " . get_class($e) . " - " . 
                     $e->getMessage() . "\nTrace: " . $e->getTraceAsString();
        error_log($error_msg);
        throw new RuntimeException("予期しないエラーが発生しました: {$file_name}", 0, $e);
    }
}


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
//  セキュアなクッキーを設定しています。
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


//  ユーザーエージェントをvalidateして
//  安全ならsessionに保存します。
//  $_SESSION['user_agent'] = $user_agent;


try {
    validate_user_agent();
    } catch (InvalidArgumentException $e) {
        $code = (int)$e->getCode();
        $attack_id = uniqid('UA_INVALID_');
        
        error_log("[SECURITY] User-Agent検証失敗 - AttackID: {$attack_id}, " .
        "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . 
        ", エラー: " . $e->getMessage());
        switch ($code) {
        case 4001: // 長さ異常 → 400
            session_unset();
            session_destroy();
            session_write_close();
            http_response_code(400);
            die("不正なアクセスです。<br>正常なブラウザからアクセスしてください。<br>攻撃ID: {$attack_id}");
                
        case 4031: // 攻撃的UA → 403（または302でerror_page.phpへ）
            session_unset();
            session_destroy();
            session_write_close();
            // 直接403応答
            http_response_code(302);
            //  302 リダイレクト（最終ステータスは遷移先で設定 403）
            header('Location: error_page.php', true, 302); exit;
                    
        case 4291: // 高頻度検知（任意運用） → 429 or recaptchaへ
            // セッションは維持して reCAPTCHA に誘導
                header('Location: recaptcha.php', true, 302);
                exit;
                        
            default: // 未分類は 400 として処理
            session_unset();
            session_destroy();
            session_write_close();
            http_response_code(400);
            die("不正なアクセスです。<br>攻撃ID: {$attack_id}");
        }
                        
    } catch (RuntimeException $e) {
        $error_id = uniqid('UA_SYSTEM_');
                            
        error_log("[ERROR] User-Agent検証システムエラー - ErrorID: {$error_id}, " .
        "エラー: " . $e->getMessage());
                            
        http_response_code(500); // Internal Server Error
        die("システムエラーが発生しました。<br>
            エラーID: {$error_id}<br>
            <a href=\"index.php\">再試行する</a>");
    }
                            
                            
    // 初回アクセス時に simple UA を記録
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

/****************************************/
//  interface の実装が完成して、読み込んだら
//  以下のコードを有効にしてください。
//    $current_simple_ua = RequestContentImplementation::makeSimpleUa($user_agent);

//  まだ、interface の実装が完成していない場合は、
//  get_simple_ua() を直接呼び出して、
// 簡易的なユーーエージェント情報を取得します。
    $current_simple_ua = get_simple_ua($user_agent);

/****************************************/


    if (!isset($_SESSION['first_simple_ua'])) {
        $_SESSION['first_simple_ua'] = $current_simple_ua;
    }




//  IPアドレスの先頭部分を取得して、
//  セッションに保存します。

try {
    $ipv4_blocks = 2;
    $ipv6_blocks = 3;
    $_SESSION['ip_prefix'] = get_ip_prefix_for_session($ipv4_blocks, $ipv6_blocks);
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


    
    
//  HTMLの開始タグ、h1 タグを表示
show_top();

// すべての学生情報を取得します。
try{
    $members = $dbm->get_allstudents();
} catch (Exception $e) {
    // エラーメッセージを表示します。
    echo '<p>データーベースエラーが発生しました: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    $members = [];
}

if ($members === []) {
    // 学生情報が存在しない場合は、メッセージを表示します。
    echo '<p>データベースエラーまたは、学生情報が登録されていません。</p>';
}else {
        // 学生情報が存在する場合は、学生情報を表示します。
//  トークンを生成します。
$token = generate_csrf_token();
//  show_student_list() において、
//  $token はエスケープされた状態で渡されます。
show_student_list($members);
}

//  新しい学生情報を登録するためのリンクを表示します。
//  このリンクは、student_input.php に遷移します。
echo '<a href="student_input.php">新しい学生情報を登録する</a>';
echo '<br><br>';
//  HTML の閉じタグを表示します。
show_bottom();
?>
    
