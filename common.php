<?php
//  file 読み込み用の path を取得する関数 safe_file_path() を定義します。
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

function safe_file_path1($file_name, $options = []) {
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
//  共通ファイルを読み込みます
$files_required = [
    'common/html_functions.php',
    'common/dbmanager.php',
    'common/data_check.php',
    'user-agent-check.php',
    'ip-check.php',
    'handle-timeout.php',
    'validate-u-a.php',
    'generate-token.php',
    'get-value-from-post-or-get.php',
    'session-set-cookie-params.php'
];

foreach ($files_required as $file_name) {
try {
    $real_path = safe_file_path1($file_name);
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
}



//  function get_error() を定義します。
//  $error は、
//  エラーメッセージを格納する変数です。
//  初期値は空文字列です。
//  $_SESSION['error'] が存在する場合は、
//  $error にその値を格納します。
//  $error を戻り値としてかえします。
function get_error() {
    $error = '';
    if (isset($_SESSION['error'])) {
        $error = $_SESSION['error'];
        //  エスケープ
        $error = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
        unset($_SESSION['error']); //  エラーを表示した後は、セッションから削除します。
    }
    return $error;
}
//  function get_error() 内の
//  $SESSION['error'] の値は、
//  post_data.php において、
//  データベースの更新や削除に失敗した場合に
//  エラーメッセージを設定し error 
//  パラメータとしてリダイレクトされます。
//  post_data.php では、
//  check_input 関数で $error を参照渡しして
//  受け取り、エラーメッセージを設定します。
//
//
//  DBManager クラスのインスタンスを作成し、
//  $dbm に代入します。

//  デバッグ用コード(コメントアウトしています、必要に応じてコメントを外して実行)
//  DBManager クラスの存在確認とインスタンス作成のテストコードです。
//  このコードは、コメントアウトされています。
//  必要に応じてコメントを外して実行できます。
// echo "DBManagerクラスの存在確認: " . (class_exists('DBManager') ? 'あり' : 'なし') . "<br>";

// try {
//     echo "DBManagerのインスタンス作成を開始...<br>";
//     $dbm = new DBManager();
//     echo "DBManagerのインスタンス作成成功！<br>";
//     echo "\$dbm の型: " . gettype($dbm) . "<br>";
//     echo "\$dbm instanceof DBManager: " . ($dbm instanceof DBManager ? 'true' : 'false') . "<br>";
// } catch (Exception $e) {
//     echo "例外が発生しました: " . $e->getMessage() . "<br>";
//     echo "ファイル: " . $e->getFile() . "<br>";
//     echo "行: " . $e->getLine() . "<br>";
//     error_log('DBManager のインスタンス作成に失敗: ' . $e->getMessage());
//     die('DBManager のインスタンス作成に失敗しました');
// }
// echo "common.php の最後で \$dbm の状態: " . (isset($dbm) ? 'セット済み' : '未セット') . "<br>";
// //  デバッグ用コード終了

try {
    $dbm = new DBManager();
} catch (Exception $e) {
    error_log('DBManager のインスタンス作成に失敗: ' . $e->getMessage());
    die('DBManager のインスタンス作成に失敗しました');
}
//  この段階で $dbm は定義されていることは
//  確認済みです。


//  ベース例外クラス

/**
 * アプリケーション例外の基本クラス
 * @param string $message エラーメッセージ
 * @param int $code エラーコード
 * @param array $context 追加のコンテキスト情報
 * @param Throwable|null $previous 前の例外
 */
//  getLogMessage() でエラーメッセージを文字列で返す
abstract class ApplicationException extends Exception {
    protected array $context = [];
    
    public function __construct($message = "", $code = 0, $context = [], ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->context = is_array($context) ? $context : [];
    }
    
    public function getContext() {
        return $this->context;
    }
    
    public function getLogMessage() {
        return sprintf(
            "%s - メッセージ: %s, コンテキスト: %s, IP: %s, 時刻: %s",
            get_class($this),
            $this->getMessage(),
            json_encode($this->context, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',  // アクセス元IPアドレス
             // $_SERVER['REMOTE_ADDR'] は、クライアントのIPアドレスを取得するためのスーパーグローバル変数です。
            date('Y-m-d H:i:s')
        );
    }
}




    // 詳細なコンテキスト情報を収集(例)
    $context_example = [
        // セッション状態の詳細分析
        'session_analysis' => [
            'session_id' => session_id(),
            'session_status' => session_status(),
            'csrf_token_exists' => isset($_SESSION['csrf_token']),
            'csrf_token_time_exists' => isset($_SESSION['csrf_token_time']),
            'csrf_token_length' => isset($_SESSION['csrf_token']) ? strlen($_SESSION['csrf_token']) : 0,
            'csrf_token_time_value' => $_SESSION['csrf_token_time'] ?? null,
            'session_keys' => array_keys($_SESSION ?? []),
            'session_data_count' => count($_SESSION ?? [])
        ],
        
        // リクエスト詳細
        'request_details' => [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time())
        ],
        
        // POSTデータ分析（値は含めない）
        'post_analysis' => [
            'csrf_token_exists' => isset($_POST['csrf_token']),
            'csrf_token_length' => isset($_POST['csrf_token']) ? strlen($_POST['csrf_token']) : 0,
            'post_keys' => array_keys($_POST ?? []),
            'post_count' => count($_POST ?? []),
            'id_exists' => isset($_POST['id']),
            'data_exists' => isset($_POST['data'])
        ],
        
        // 攻撃パターン分析
        'attack_analysis' => [
            'type' => 'CSRF_TOKEN_MISSING',
            'severity' => 'HIGH',
            'possible_causes' => [
                'session_expired',
                'direct_access',
                'session_manipulation',
                'csrf_bypass_attempt'
            ],
            'suspicious_indicators' => [
                'no_referer' => empty($_SERVER['HTTP_REFERER']),
                'invalid_referer' => !empty($_SERVER['HTTP_REFERER']) && 
                                   strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) === false,
                'empty_user_agent' => empty($_SERVER['HTTP_USER_AGENT']),
                'automated_access' => strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'curl') !== false ||
                                     strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'wget') !== false ||
                                     strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'bot') !== false
            ]
        ],
        
        // タイミング情報
        'timing_info' => [
            'detection_time' => date('Y-m-d H:i:s'),
            'detection_timestamp' => time(),
            'session_start_time' => $_SESSION['session_start'] ?? 'unknown',
            'last_activity' => $_SESSION['last_activity'] ?? 'unknown',
            'page_count' => $_SESSION['page_count'] ?? 0
        ],
        
        // システム状態
        'system_state' => [
            'php_session_status' => [
                PHP_SESSION_DISABLED => 'DISABLED',
                PHP_SESSION_NONE => 'NONE', 
                PHP_SESSION_ACTIVE => 'ACTIVE'
            ][session_status()] ?? 'UNKNOWN',
            'session_timeout' => ini_get('session.gc_maxlifetime'),
            'cookie_lifetime' => ini_get('session.cookie_lifetime')
        ]
    ];
    
    
    
    
/**
 * セキュリティ例外
 * @param string $message エラーメッセージ
 * @param int $code エラーコード
 * @param array $context 追加のコンテキスト情報
 * @param Throwable|null $previous 前の例外
 * @param int $securityLevel セキュリティレベル
 */
//  getLogMessage() でエラーメッセージを文字列で返す
class SecurityException extends ApplicationException {
    const LEVEL_LOW = 1;
    const LEVEL_MEDIUM = 2;
    const LEVEL_HIGH = 3;
    const LEVEL_CRITICAL = 4;

    // セキュリティエラーコード定数
    const SEC_CSRF_ATTACK = 3001;
    const SEC_SESSION_HIJACK = 3002;
    const SEC_DIRECTORY_TRAVERSAL = 3003;
    const SEC_INVALID_TOKEN = 3004;
    const SEC_BRUTEFORCE_ATTEMPT = 3005;

    
    private $securityLevel;
    //  const をクラス内で参照する場合は、self:: を使う。
    public function __construct($message = "", $code = 0, $context = [], $securityLevel = self::LEVEL_MEDIUM, ?Throwable $previous = null) {
        parent::__construct($message, $code, $context, $previous);
        $this->securityLevel = $securityLevel;
    }
    
    public function getSecurityLevel() {
        return $this->securityLevel;
    }
    
    public function isCritical() {
        return $this->securityLevel >= self::LEVEL_HIGH;
//  return 条件式の値を返す
//         $this->securityLevel: インスタンスのセキュリティレベル（数値）
//         >=: 以上（大なりイコール）
//         self::LEVEL_HIGH: 定数の値（3）
//         結果: セキュリティレベルが「高」以上かを判定するboolean値を返す
    }
}

// CSRF攻撃専用例外
//  context =[
//            'previous_ip_prefix' => 'abc.123.45',
//            'current_ip_prefix'  => 'xyz.789.01',
//            'session_id'       => 'session_123456'
//           ]
//  message : "CSRFトークンが無効です" 
//            "ipアドレスが変更されました"
class CSRFException extends SecurityException {
    public function __construct($message = "", $code = 0, $context = [], ?Throwable $previous = null) {
        parent::__construct($message, $code, $context, self::LEVEL_HIGH, $previous);
    }
}

// データベース例外

//  $e=new PDOException(...) の場合、
//  $e->getCode() は SQLSTATEコードを返す。
//  $e->errorInfo[0] は SQLSTATEコードを返す。
//  $e->errorInfo[1] は ドライバーコードを返す。
//  $e->errorInfo[2] は ドライバーのエラーメッセージを返す。

//  $context の例:
//        [
//         'table' => 'students',
//         'operation' => 'INSERT'
//        ]
/**
 * 使用例:
 * } catch (PDOException $e) {
 * //  PDOException から DatabaseException を作成。
 * $e = DatabaseException::fromPDOException($e, ['context_1' => $situation]);
 * //  DatabaseException として再スロー。
 * throw $e;
 * ここまで、関数内のことが多い。
 * 以下は、呼び出し元での処理例。
 *  }catch (DatabaseException $e) {
 * //  DatabaseException をキャッチして処理。
 * $message = $e->getLogMessage();
 * error_log($message);
 * die('データベースエラーが発生しました。管理者に通報されました。<br><br>');
 * }
 * 
 * @param string $message エラーメッセージ
 * @param int $code エラーコード
 * @param array $context 追加のコンテキスト情報
 * @param string|null $sqlState SQLSTATEコード
 * @param mixed $driverCode ドライバーコード
 * @param Throwable|null $previous 前の例外
 */
//  getLogMessage() でエラーメッセージを文字列で返す（継承）
//  PDOException をラップしてから再スロー、呼び出しもとでDatabaseExceptionをcatch。
class DatabaseException extends ApplicationException {
    private ?string $sqlState;   //  PDOException の errorInfo[0] に対応
    private        $driverCode; //  PDOException の errorInfo[1] に対応
                                //  あえて型宣言しない int, null, string などが入る可能性があるため
    public function __construct($message = "", $code = 0, $context = [], $sqlState = null, $driverCode = null, ?Throwable $previous = null) {
        parent::__construct($message, $code, $context, $previous);
        $this->sqlState = $sqlState;
        $this->driverCode = $driverCode;
    }
        // ファクトリメソッド: PDOExceptionからDatabaseExceptionを作成
    public static function fromPDOException(PDOException $e, array $context = []) {
        return new self(  // 'self' = DatabaseException
            $e->getMessage(),                    // PDOExceptionのメッセージ
            0,                                  // アプリケーション独自コード
            $context,                           // 追加コンテキスト
            $e->errorInfo[0] ?? $e->getCode(),  // SQLSTATE
            $e->errorInfo[1] ?? null,           // ドライバーコード
            $e                                  // 元のPDOException
        );
    }
    

    public function getSqlState() {
        return $this->sqlState;
    }
    
    public function getDriverCode() {
        return $this->driverCode;
    }
    
    public function getLogMessage() {
        return sprintf(
            "DatabaseException - メッセージ: %s, SQLSTATE: %s, ドライバーコード: %s, コンテキスト: %s, IP: %s, 時刻: %s",
            $this->getMessage(),
            $this->sqlState ?? 'unknown',
            $this->driverCode ?? 'unknown',
            json_encode($this->context, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            date('Y-m-d H:i:s')
        );
    }
}

// ビジネスロジック例外
/**
 * @param string $message エラーメッセージ
 * @param int $code エラーコード
 * @param array $context 追加のコンテキスト情報
 * @param Throwable|null $previous 前の例外
 */
//  getLogMessage() でエラーメッセージを文字列で返す（継承）
class BusinessLogicException extends ApplicationException {
    const ERR_DATA_NOT_FOUND = 1001;
    const ERR_INVALID_DATA = 1002;
    const ERR_DATABASE_ERROR = 1003;
    const ERR_PERMISSION_DENIED = 1004;
    
    public static function getErrorMessage($code) {
        switch ($code) {
            case self::ERR_DATA_NOT_FOUND:
                return 'データが見つかりません';
            case self::ERR_INVALID_DATA:
                return '無効なデータです';
            case self::ERR_DATABASE_ERROR:
                return 'データベースエラーが発生しました';
            case self::ERR_PERMISSION_DENIED:
                return '権限が不足しています';
            default:
                return '不明なエラーです';
        }
    }
}

// 入力検証例外
/**
 * @param string $message エラーメッセージ
 * @param string|null $field エラーが発生したフィールド名
 * @param string|null $value エラーが発生したフィールドの値
 * @param int $code エラーコード
 * @param array $context 追加のコンテキスト情報
 * @param Throwable|null $previous 前の例外
 * @return getlogMessage() 
 */
class ValidationException extends ApplicationException {
    private ?string $field;
    private         $value;

    public function __construct(string $message = "", ?string $field = null, $value = null, int $code = 0, array $context = [], ?Throwable $previous = null) {
        parent::__construct($message, $code, $context, $previous);
        $this->field = $field;
        $this->value = $value;
    }
    
    public function getField() {
        return $this->field;
    }
    
    public function getValue() {
        return $this->value;
    }
}

/**  session hijacking exception
 * セッションハイジャック攻撃を検出した場合にスローされる例外
 *  @param string $message "IPアドレスが変更されました"  など。
 *  @param int    $code    SecurityException::SEC_SESSION_HIJACK
 *  @param array  $context 以前のIPアドレスや現在のIPアドレス、
 *                セッションIDなどの情報を含めることができます。
 *               例: [
 *                   'previous_ip_prefix' => 'abc.123.45',
 *                   'current_ip_prefix'  => 'xyz.789.01',
 *                   'session_id'         => 'session_123456'
 *                   ]
 * @param Throwable|null $previous 前の例外
 */
class SessionHijackingException extends SecurityException {
     public function __construct($message = "", $code = SecurityException::SEC_SESSION_HIJACK, $context = [], ?Throwable $previous = null) {
        parent::__construct($message, $code, $context, self::LEVEL_HIGH, $previous);
    }

    public function getPrevIp() {
        return $this->context['previous_ip_prefix'] ?? null;
    }

    public function getCurrentIp() {
        return $this->context['current_ip_prefix'] ?? null;
    }

    public function getSessionId() {
        return $this->context['session_id'] ?? null;
    }

     // ApplicationException の getLogMessage() をオーバーライド
    public function getLogMessage() {
        $attack_details = [
            'type' => 'SESSION_HIJACKING',
            'previous_ip' => $this->getPrevIp(),
            'current_ip' => $this->getCurrentIp(),
            'session_id' => $this->getSessionId(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return sprintf(
            "SessionHijackingException - メッセージ: %s, 攻撃詳細: %s, コンテキスト: %s, IP: %s, 時刻: %s",
            $this->getMessage(),
            json_encode($attack_details, JSON_UNESCAPED_UNICODE),
            json_encode($this->context, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            date('Y-m-d H:i:s')
        );
    }
}

