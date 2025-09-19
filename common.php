<?php
if (file_exists(__DIR__."/common/html_functions.php")) {
    require_once (__DIR__."/common/html_functions.php");
}else {
    die('html_functions.php が見つかりません');
}
if (file_exists(__DIR__."/common/dbmanager.php")) {
    require_once (__DIR__."/common/dbmanager.php");
} else {
    die('dbmanager.php が見つかりません');
}
if (file_exists(__DIR__."/common/data_check.php")) {
    require_once (__DIR__."/common/data_check.php");
} else {
    die('data_check.php が見つかりません');
}
//  セッション タイムアウト処理
function handle_session_timeout() {
    $session_timeout = 600; // 10分
    
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        
        if ($inactive_time > $session_timeout) {
            // タイムアウト処理
            $user_name = $_SESSION['user_name'] ?? 'ゲスト'; // ユーザー名を保存（あれば）
            
            // セッションを完全にクリア
            session_unset();
            session_destroy();
            
            // 新しいセッションを開始
            session_start();
            
            // タイムアウト情報を新しいセッションに保存
            $_SESSION['timeout_info'] = [
                'message' => "お疲れ様でした。セッションがタイムアウトしました。",
                'inactive_minutes' => round($inactive_time / 60),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // ログを記録
            error_log("セッションタイムアウト - ユーザー: {$user_name}, 非アクティブ時間: {$inactive_time}秒");
            
            // リダイレクト
            header('Location: index.php');
            exit();
        }
    }
    
    // タイムアウトしていない場合
    $_SESSION['last_activity'] = time();
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
//  DBManager クラスのインスタンスを作成します。
//  $dbm に代入します。

//  デバッグ用コード
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

