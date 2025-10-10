<?php

//  POST メソッドで送信されたデータを処理するためのスクリプトです。
//  データベースの更新や削除を行い、結果に応じてリダイレクトします。
//  セキュリティー対策として、POST 元で SESSION を開始し、
//  CSRF トークンを生成して、
//  フォームに埋め込み、このファイルで確認します。
//  CSRF トークンは、KEY 'csrf_token' に対応するようにします。

//  必要なファイルを読み込むのに必要な関数を定義します。
//  common.php にある safe_file_path1() と重複読み込みを防ぐために
//  safe_file_path() としています。
/**
 * safe_file_path - path validation and normalization
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

//  POST メソッドで送信されたかを確認し、
//  token の検証を行います。
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {

//  CSRF トークンの検証を行います。
//  フォームから送信されたトークンとセッションに保存されているトークンを比較します。
        // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        //     die('トークンが一致しません。');
        // }
if (!isset($_POST['csrf_token'])) {
    die('トークンが送信されていません。');
}
if (!isset($_SESSION['csrf_token'])) {
    die('セッションにトークンが保存されていません。');
}
$post_token    = htmlspecialchars($_POST['csrf_token'], ENT_QUOTES, 'UTF-8');

$session_token = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');

if (!hash_equals($post_token, $session_token)) {

    session_unset();
    session_destroy();
    die('トークンが一致しません。');
}
}else {
    //  POST メソッドで送信されていない場合は、
    //  不正なアクセスとして処理を終了します。
    die('通信形式が一致しません。POST メソッドで送信してください。');
}

//  token を使い捨てます。
        
        unset($_SESSION['csrf_token']);
//  
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

    //  $_POST['data'] の値がセットされているかを確認します。
    // セットされていない場合は、不正なアクセスとして処理を終了します。
    if (!isset($_POST['data'])) {
        die('処理が指定されていません');
    }else {
        $data = $_POST['data'];
    }
    //  $_POST の値を取得します。
        if (isset($_POST["id"])) {
        $id = $_POST["id"];
        } else {
            die('学生 ID が指定されていません');
        }

        if ($data === 'update' or $data === 'create') {
            if (isset($_POST["name"])) {
            $name = $_POST["name"];
            }else {
                die('名前が指定されていません');
            }
            if (isset($_POST["grade"])) {
            $grade = $_POST["grade"];
            }else {
                die('学年が指定されていません');
            }
            if (isset($_POST["old_id"])) {
            $old_id = $_POST["old_id"];
            }else {
                die('更新前の学生 ID が指定されていません');
            }
        }


//  

        //  session にデータを保存します。
        $_SESSION['id']       = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $_SESSION['data']     = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

        if (isset($name)){
            $_SESSION['name']   = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        }
        if (isset($grade)){
            $_SESSION['grade']  = htmlspecialchars($grade, ENT_QUOTES, 'UTF-8');
        }
        if (isset($old_id)){
            $_SESSION['old_id'] = htmlspecialchars($old_id, ENT_QUOTES, 'UTF-8');
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

        //  $_SESSION にエラーメッセージを保存します。
                $_SESSION['error'] = $error;
        header("Location: student_input.php");
        exit();
                
            }
            //  $dbm インスタンスの if_id_exists() メソッドを使用して、
            //  学生 ID がすでに存在するかを確認します。
            //  common.php で $dbm = new DBManager(); としているので、
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
                $_SESSION['error'] = $error;
                header("Location: student_input.php");
                exit();
            }

            //  学生情報をデータベースに挿入します。
            $result = $dbm->insert_student($id, $name, $grade);
            if ($result === false) {
                //  挿入処理に失敗した場合は、
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $error = "学生情報の登録に失敗しました";
                $_SESSION['error'] = $error;
                header("Location: student_input.php");
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
                $_SESSION['error'] = $error;
                header("Location: student_update.php");
                exit();
            }
            //  新しく更新する学生 ID がすでに存在するかを確認します。
            if ($dbm->if_id_exists($id) === true && $id !== $old_id) {
                //  学生 ID がすでに存在する場合は、
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $error = "学生 ID {$id} はすでに存在します";
                $_SESSION['error'] = $error;
                header("Location: student_update.php");
                exit();
            }
            $result = $dbm->update_student($id, $name, $grade, $old_id);
            if ($result === false) {
                //  更新処理に失敗した場合は、  
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $error = "学生情報の更新に失敗しました";
                $_SESSION['error'] = $error;
                header("Location: student_update.php");
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
                $error = "学生 ID {$id} はデータベースで見つかりません";
                $_SESSION['error'] = $error;// get_error() で取得
                $_SESSION['id'] = $id; //  削除確認ページで使用するため
                //  student_delete.php にリダイレクトします。
                header("Location: student_delete.php");
                exit();
            }
            $result = $dbm->delete_student($id);
            if ($result === false) {
                //  削除処理に失敗した場合は、
                //  エラーメッセージを $error に代入し、
                //  POST もとのページにリダイレクトします。
                $error = "学生情報の削除に失敗しました";
                $_SESSION['error'] = $error;
                header("Location: student_delete.php");
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
            $error = "data が update, delete, create のいずれでもありません";
            $_SESSION['error'] = $error;
            //  index.php にリダイレクトします。
            header("Location: index.php");
            exit();
        }





?>