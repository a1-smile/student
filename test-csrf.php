<?php

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
    const SEC_INVALID_REQUEST_METHOD = 3000;
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

/**不正なリクエストメソッド例外
 * HTTPリクエストメソッドが期待されたものと異なる場合にスローされる例外クラス
 * @param string $message "不正なリクエストメソッドです"
 * @param int $code SecurityException::SEC_INVALID_REQUEST_METHOD
 * @param array $context 追加のコンテキスト情報
 * @param int $securityLevel セキュリティレベル（デフォルトはLEVEL_MEDIUM）
 * @param Throwable|null $previous 前の例外        
 */

class InvalidRequestMethodException extends SecurityException {
    public function __construct(
        $message = "不正なリクエストメソッドです", 
        $code = SecurityException::SEC_INVALID_REQUEST_METHOD, 
        $context = [], 
        $securityLevel = self::LEVEL_MEDIUM,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $context, $securityLevel, $previous);
    }
    
    // ヘルパーメソッド、配列として期待されるメソッドを返す
    public function getExpectedMethods(): ?array {
        return $this->context['expected_methods'] ?? null;
    }
    
    public function getActualMethod(): ?string {
        return $this->context['actual_method'] ?? null;
    }


    // 文字列として想定されるメソッドを返す
    public function getExpectedMethod(): ?string {
        $methods = $this->getExpectedMethods();
        return $methods ? implode(', ', $methods) : null;
    }
    
    // ファクトリーメソッド
    // 戻り値が現在のクラスのインスタンスであることを示すため
    // 型宣言で self を使用
    /**
     * 現在のリクエストに基づいて InvalidRequestMethodException を生成するファクトリーメソッド
     * 
     * @param array $expectedMethods 期待されるHTTPメソッドの配列（例: ['POST', 'GET']）
     * @param string|null $customMessage カスタムメッセージ（省略
     * @return self InvalidRequestMethodExceptionのインスタンス
     */
    public static function fromCurrentRequest(array $expectedMethods, string $customMessage = null): self {
        $actualMethod = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        
        $message = $customMessage ?? sprintf(
            "期待されたメソッド: %s, 実際のメソッド: %s",
            implode(', ', $expectedMethods),
            $actualMethod
        );
        
        $context = [
            'expected_methods' => $expectedMethods,// 配列として保存
            'actual_method' => $actualMethod,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            // referer はどのページから移動してきたかを示す。
            'request_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time()),
            'session_id' => session_id()
        ];
        
        return new self(
            $message,
            SecurityException::SEC_INVALID_REQUEST_METHOD,
            $context,
            SecurityException::LEVEL_MEDIUM  
        );
    }
}

// // 使用例
// try {
//     $method = $_SERVER['REQUEST_METHOD'] ?? '';
//     $allowedMethods = ['POST', 'GET'];
    
//     if (!in_array($method, $allowedMethods)) {
//         // 配列で期待メソッドを指定
//         throw InvalidRequestMethodException::fromCurrentRequest($allowedMethods);
//     }
// } catch (InvalidRequestMethodException $e) {
//     // ログ記録
//     error_log($e->getLogMessage());
//     // ユーザーへの通知
//     die('不正なリクエストメソッドです。管理者に通報されました。<br><br>');
// }






/**
 * CSRF攻撃専用例外
 * リクエストのCSRFトークン検証に失敗した場合にスローされる
 * @param string $message "CSRF攻撃検知"(デフォルト)
 * @param int $code SecurityException::SEC_CSRF_ATTACK (3001)
 * @param array $context 追加のコンテキスト情報
 * @param int $securityLevel セキュリティレベル（デフォルトはLEVEL_HIGH）
 * @param Throwable|null $previous 前の例外
 */          
class CSRFException extends SecurityException {
    public function __construct(
        $message = "CSRF攻撃検知", 
        $code = SecurityException::SEC_CSRF_ATTACK, 
        $context = [],
        $securityLevel = self::LEVEL_HIGH,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $context, $securityLevel, $previous);
    }
    //  factory method
    /**
     * 現在のリクエストに基づいて CSRFException を生成するファクトリーメソッド
     * @param string|null $customMessage カスタムメッセージ（省略可能）
     * @return self CSRFExceptionのインスタンス
     */
    public static function fromCurrentRequest(
        string $customMessage = null,
        int $code = SecurityException::SEC_CSRF_ATTACK,
        array $context = [],
        int $securityLevel = SecurityException::LEVEL_HIGH,
        ?Throwable $previous = null
    ): self {
        echo "CSRFException::fromCurrentRequest がよびだされました。<br>";
        
        $message = $customMessage ?? "CSRF攻撃検知";
        
        $context[] = [
            //  urlのドメイン以降がuri
            //  つまり、どのページを要求されたか？
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            //  アクセス元IPアドレス
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            //  どのページから移動してきたか
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            //  date() で日付フォーマットを指定して表示
            //  $_SERVER['REQUEST_TIME'] は、リクエストがサーバーーに到達したタイムスタンプ
            //  time() は、現在のタイムスタンプ
            'request_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time()),
            'session_id' => session_id(),
            //  array_keys() は、配列のキーを取得する関数
            // $_SESSION ?? [] は、$_SESSIONが存在しない場合に空配列を返す
            'session_keys' => array_keys($_SESSION ?? []),
            'post_keys' => array_keys($_POST ?? []),
            'post_count' => count($_POST ?? []),
            'csrf_token_in_post' => isset($_POST['csrf_token']),
            'csrf_token_in_session' => isset($_SESSION['csrf_token']),
            'csrf_token_match' => (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token']) &&
                                  hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'])),
        ];
        $context['csrf_attack_indicators'] = [
    'token_state' => [
        //  セッションにトークンが存在しない場合 true
        'session_token_missing' => !isset($_SESSION['csrf_token']),
        //  セッションにタイムスタンプが存在しない場合true
        'session_time_missing' => !isset($_SESSION['csrf_token_time']),
        'post_token_present' => isset($_POST['csrf_token']),
        'token_mismatch' => isset($_SESSION['csrf_token']) && isset($_POST['csrf_token']) &&
                           !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ],
    'referer_analysis' => [
        //  refererが存在しない場合 true
        'referer_present' => !empty($_SERVER['HTTP_REFERER']),
        'referer_matches_host' => self::isValidReferer(),                                
        'referer_value' => $_SERVER['HTTP_REFERER'] ?? 'none'
    ]
];
        echo "CSRFExceptionを生成します。<br>";
        return new self(
            $message,
            SecurityException::SEC_CSRF_ATTACK,
            $context,
            $securityLevel,
            $previous
        );
    }
    private static function isValidReferer(): bool {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        //  アクセス先のドメイン名、ポート番号を含むホスト名
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        
        if (empty($referer) || empty($currentHost)) {
            return false;
        }
        //  parse_url() は、URLを解析してその構成要素を取得する関数
        //  PHP_URL_HOST は、ホスト名を取得するための定数
        $refererHost = parse_url($referer, PHP_URL_HOST);
        return $refererHost === $currentHost;
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
 * @return getLogMessage() 
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


/**
 * 失敗記録の追加
 * @param string $ip クライアントのIPアドレス
 * @param array $rate_data 現在のレート制限データ
 * @return void
 * $rate_data['failures'] に現在時刻を追加
 * $_SESSION[$rate_key] に更新された $rate_data を保存
 */
function recordFailure(string $ip,array $rate_data): void {
    echo "<h3>recordFailure がよびだされました。</h3>";
    $rate_key = "csrf_attempts_{$ip}";
    $rate_data['failures'][] = time();
    echo "Failures print_rします。:<br>";
    print_r($rate_data['failures']);
    echo "<br>";
    echo "Failuresをvar_dumpします。:<br>";
    var_dump($rate_data['failures']);
    echo "<br>";
    $_SESSION[$rate_key] = $rate_data;
}
/**
 * 成功記録の追加
 * @param string $ip クライアントのIPアドレス
 * @param array $rate_data 現在のレート制限データ
 * @return void
 * $rate_data['successes'] に現在時刻を追加
 * $_SESSION[$rate_key] に更新された $rate_data を保存
 */
function recordSuccess(string $ip,array $rate_data): void {

    echo "<h3>recordSuccess がよびだされました。</h3>";
    $rate_key = "csrf_attempts_{$ip}";
    echo "Successes before adding new time:<br>
    時刻が追加される前の成功記録を表示します。:<br>";
    print_r($rate_data['successes']);
    echo "<br>";
    $rate_data['successes'][] = time();
    echo "Successes after adding new time:<br>
    時刻が追加された後の成功記録を表示します。:<br>";
    print_r($rate_data['successes']);
    echo "<br>";
    $_SESSION[$rate_key] = $rate_data;
}

/**
 * セッション破棄前にセキュリティデータを保存
 * @param string $ip クライアントのIPアドレス
 * @return array $data 保存されたセキュリティデータ
 */
function preserveSecurityData(string $ip): array {
    echo "<h3>preserveSecurityData called</h3>";
    $rate_key = "csrf_attempts_{$ip}";
    $data = $_SESSION[$rate_key] ?? [
        'failures' => [],
        'successes' => [],
        'blocked_until' => 0,
        'session_resets' => 0,
        'last_reset_time' => 0
    ]; 
    echo "Preserved Data:<br>";
    print_r($data);   
    return $data;
}

/**
 * 新しいセッションにセキュリティデータを復元
 * @param string $ip クライアントのIPアドレス
 * @param array $preserved_data 復元するセキュリティデータ
 */
function restoreSecurityData(string $ip, array $preserved_data): void {
    echo "restoreSecurityData called<br><br>";
    $rate_key = "csrf_attempts_{$ip}";
    $_SESSION[$rate_key] = $preserved_data;
    
    // セッション破棄の記録も追加
    $_SESSION['security_events'] = [
        'last_session_destroy' => time(),
        'destroy_reason' => 'critical_csrf_attack',
        'destroy_ip' => $ip
    ];
}

/**
 * セッションリセットの記録
 * token検証失敗数と時刻を記録
 * @param string $ip クライアントのIPアドレス
 * @return void
 * $ip に対する $rate_key がなければ何もしない
 * $_SESSION[$rate_key]['session_resets'] をインクリメント
 * $_SESSION[$rate_key]['last_reset_time'] に現在時刻をセット
 */
function recordSessionReset(string $ip): void {
    echo "<h3>recordSessionReset called</h3>";
    $rate_key = "csrf_attempts_{$ip}";
    if (!isset($_SESSION[$rate_key])) {
        return;
    }
    
    $_SESSION[$rate_key]['session_resets']++;
    echo "Session resets: " . $_SESSION[$rate_key]['session_resets'] . "<br>";
    $_SESSION[$rate_key]['last_reset_time'] = time();
echo "Last reset time: " . date('Y-m-d H:i:s', $_SESSION[$rate_key]['last_reset_time']) . "<br><br>";
}

/**
 * 失敗時のクリーンアップ
 * @param string $failed_token 失敗したCSRFトークン
 * @return void
 * $ip にアクセス元のIPアドレスをセット
 * recordSessionReset() を呼び出しセッションリセットを記録
 * $_SESSION['csrf_token'],
 * $_SESSION['csrf_token_time'],
 * $_POST['csrf_token'] を削除
 * 失敗したトークンを使用済みとして記録
 * sessionが有効なら session_regenerate_id(true)
 * を呼び出しセッションIDを再生成
 */
function cleanupAfterFailure(string $failed_token): void {
    echo "<h3>cleanupAfterFailure がよびだされました。</h3>";
    echo "Failed Token: をvar_dumpします。<br>";
    var_dump($failed_token);
    echo "<br>";
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // ✅ 攻撃検知時は即座にトークンを無効化
    echo "emptyの場合は無視されます。<br>";
    unset($_SESSION['csrf_token']);
    unset($_SESSION['csrf_token_time']);
    unset($_POST['csrf_token']);
    
    // 失敗したトークンも使用済みとして記録（再利用防止）
    echo '<h2>トークンの処理</h2>';
    echo "Failed Token: を表示します。:<br>";
    echo $failed_token."<br>";
    if (!empty($failed_token)) {
        if (!isset($_SESSION['used_csrf_tokens'])) {
            $_SESSION['used_csrf_tokens'] = [];
        }
        echo "failed-tokenを記録します。:<br>";
        $_SESSION['used_csrf_tokens'][] = $failed_token;
        
        echo "Used CSRF Tokensの配列を表示します。:<br>";
        print_r($_SESSION['used_csrf_tokens']);
        echo "同じ配列をvar_dumpします。:<br>";
        var_dump($_SESSION['used_csrf_tokens']);
        echo "<br>";
        if (count($_SESSION['used_csrf_tokens']) > 10) {
            array_shift($_SESSION['used_csrf_tokens']);
            echo "11個以上なら先頭が削除されるはずです。:<br>";
            echo "Used CSRF Tokensの配列をprint_rします。:<br>";
            print_r($_SESSION['used_csrf_tokens']);
            echo "同じ配列をvar_dumpします。:<br>";
            var_dump($_SESSION['used_csrf_tokens']);
            echo "<br>";
        }
    }
}

/** 
 * 成功時のクリーンアップ
 * @param string $used_token 使用されたCSRFトークン
 * @return void
 * $used_token を使用済みトークンとして記録
 * 使用済みトークンの履歴が10件を超えたら古いものを削除
 * $_SESSION['csrf_token'],
 * $_SESSION['csrf_token_time'],
 * $_POST['csrf_token'] を削除
 */
function cleanupAfterSuccess(string $used_token): void {
    echo "<h2>cleanupAfterSuccess がよびだされました。</h2>";
    echo "使用済みトークンの記録、処理をします。:<br>";
    
    // 使用済みトークンとして記録
    if (!isset($_SESSION['used_csrf_tokens'])) {
        $_SESSION['used_csrf_tokens'] = [];
    }
    echo "Used CSRF Tokensの配列を表示します。:<br>";
    print_r($_SESSION['used_csrf_tokens']);
    echo "<br>";
    var_dump($_SESSION['used_csrf_tokens']);
    echo "<br>";
    echo "新しいトークンを追加します。:<br>";
    $_SESSION['used_csrf_tokens'][] = $used_token;
    echo "Used CSRF Tokensの配列を表示します。:<br>";
    print_r($_SESSION['used_csrf_tokens']);
    echo "<br>";
    var_dump($_SESSION['used_csrf_tokens']);
    echo "<br>";
    echo "もし10個を超えたら古いものを削除します。:<br>";
    if (count($_SESSION['used_csrf_tokens']) > 10) {
        array_shift($_SESSION['used_csrf_tokens']);
    }
    echo "Used CSRF Tokens (trimmed):<br>";
    print_r($_SESSION['used_csrf_tokens']);
    echo "<br>";
    var_dump($_SESSION['used_csrf_tokens']);
    echo "<br>";
    
    // トークンを削除
    unset($_SESSION['csrf_token']);
    unset($_SESSION['csrf_token_time']);
    unset($_POST['csrf_token']);
}


/**
 * CSRF攻撃の例外処理を条件分岐で実装
 * @param CSRFException $e スローされた例外
 * @param string $severity 攻撃の重大度 ('low', 'high', 'critical')
 * @param string $ip クライアントのIPアドレス
 * @return void
 * 重大度に応じて以下の処理を実行
 * 'critical': セッションを即座に破棄し、IPを1時間ブロック
 * 'high': トークン無効化とセッションID再生成
 * 'low': 標準的なクリーンアップ
 */
function handleCSRFAttack(CSRFException $e, string $severity, string $ip): void {
    echo "<h3>handleCSRFAttack called with severity: {$severity}</h3>";
    switch ($severity) {
        case 'critical':
            recordSessionReset($ip);
            cleanupAfterFailure($_POST['csrf_token'] ?? '');
            // ✅ セッション破棄前にデータを保存
            $preserved_data = preserveSecurityData($ip);
            // セッション破棄
            session_destroy();
            session_start();
            
            // ✅ セキュリティデータを新セッションに復元
            restoreSecurityData($ip, $preserved_data);

            error_log("重大CSRF攻撃検知 - IP: {$ip}, セッション破棄実行");
            break;
            
        case 'high':
            // ✅ セッションID再生成前に記録
            recordSessionReset($ip);
            // トークン無効化
            cleanupAfterFailure($_POST['csrf_token'] ?? '');
                // セッション自体の再生成（強化策）
            if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            }
            error_log("高リスクCSRF攻撃 - IP: {$ip}, セッション再生成");
            break;
            
        case 'low':
        default:
            // ✅ セッションID再生成前に記録   
            // トークン無効化
            echo "Low severity の処理をします。<br><br>";
            cleanupAfterFailure($_POST['csrf_token'] ?? '');
            error_log("CSRF攻撃検知 - IP: {$ip}, セッション再生成");
            break;
    }
}


// 成功と失敗を分けたレート制限システム
/**
 * レート制限の更新
 * 同一IPからの連続アクセス監視、制限
 * 5分以内で10回以上失敗した場合は30分ブロック
 * copilot-rules.md に従って実装
 * @param string $ip クライアントのIPアドレス
 * @return array 現在の試行データ
 * 失敗回数、成功回数、ブロック期限を含む配列
 * $_SESSIONに保存される
 * @throws CSRFException ブロック中,
 * または10回以上失敗した場合にスロー
 * 
 */
function advancedRateLimit(string $ip) {
    echo "<h3>advancedRateLimit called</h3>
    レイト制限関数が呼び出されました。<br><br>";
    // $ip を受け取り 
    // 配列のキーをcsrf_attempts_{$ip}として定義
    $rate_key = "csrf_attempts_{$ip}";
    // 現在の時刻を取得
    $current_time = time();
    
    //  過去にアクセスがなければ初期化
    if (!isset($_SESSION[$rate_key])) {
        $_SESSION[$rate_key] = [
            'failures'        => [], // 失敗時刻の配列
            'successes'       => [], // 成功時刻の配列  
            'blocked_until'   => 0,  // ブロック期限
            'session_resets'  => 0,  // セッションリセット回数
            'last_reset_time' => 0   // 最後のリセット時刻
        ];
    }
    echo 'failures の値<br>';
    var_dump($_SESSION[$rate_key]['failures']);
    echo '<br>';
    echo 'successes の値<br>';
    var_dump($_SESSION[$rate_key]['successes']);
    echo '<br>';
    echo 'blocked_until の値<br>';
    var_dump($_SESSION[$rate_key]['blocked_until']);
    echo '<br>';
    echo 'session_resets の値<br>';
    var_dump($_SESSION[$rate_key]['session_resets']);
    echo '<br>';

    //  アクセス情報を$dataにセット
    $data = $_SESSION[$rate_key];
    

    // セッションリセットの異常検知
    if ($data['session_resets'] > 5 && ($current_time - $data['last_reset_time']) < 300) {
        echo "Abnormal session reset detected<br>
        異常なセッションリセットが検知されました。<br>
        5分間で5回以上のセッションリセットが発生しました。<br>";

        // 5分間で5回以上のセッションリセットは異常
        $data['blocked_until'] = $current_time + 7200; // 2時間ブロック
        echo "Blocking IP for 2 hours due to abnormal session resets<br>
        2時間ブロックします。<br>
        'blocked_until' の値をvar_dumpします。:<br>";
        var_dump($data['blocked_until']);
        echo '<br>';
        $_SESSION[$rate_key] = $data;
        recordFailure($ip, $data); // 異常記録として失敗を追加
        $attack_severity = 'critical';
        echo "Critical attack severity が criticalでセットされました。<br>";
        var_dump($attack_severity);
        echo '<br>';
        error_log("異常なセッションリセット検知 - IP: {$ip}, 2時間ブロック開始");
        throw CSRFException::fromCurrentRequest(
            "異常な操作検知: 2時間ブロック",
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_CRITICAL,
            null
            );
    }



    // ブロック期間中かチェック
    //  ブロック中なら残り時間を計算して例外をスロー
    if ($data['blocked_until'] > $current_time) {
        echo "IP is currently blocked<br>
        まだブロック中です。<br>";
        $remaining = $data['blocked_until'] - $current_time;
        recordFailure($ip, $data); // ブロック中のアクセスも失敗として記録
            $attack_severity = SecurityException::LEVEL_CRITICAL;
            echo "Critical attack severity が criticalでセットされました。<br>";
            var_dump($attack_severity);
            echo '<br>';
        throw CSRFException::fromCurrentRequest(
            "ブロック中: 残り{$remaining}秒",
            SecurityException::SEC_CSRF_ATTACK,
            [],
            $attack_severity,
            null
            );
    }
    
    // 古い記録を削除（スライディングウィンドウ）
    $window = 10; // 5分間
    //  array_filter()は条件に合う要素だけを残す
    //  配列の中から($current_time - $time) < $window
    //  を満たす要素だけを残す
    //  無名関数(function)は、
    //  配列の各要素を$timeとして引数で受け取る
    //  use()は外部変数を無名関数内で使うための宣言
    //  つまり 5分以内の失敗記録だけを残す
    echo "'failures' のクリーンアップをします。:<br>";
    var_dump($data['failures']);
    echo '<br>';
    echo 'failures の前のcount(): ' . count($data['failures']) . '<br>';
    $data['failures'] = array_filter($data['failures'], function($time) use ($current_time, $window) {
        //  $time は配列の各要素に記録した時刻
        return ($current_time - $time) < $window;
    });
    echo 'failures の後のcount(): ' . count($data['failures']) . '<br><br>';

        // ✅ 成功記録のクリーンアップも追加
    echo "'successes' のクリーンアップをします。:<br>";
    var_dump($data['successes']);
    echo '<br>';
    echo 'トリム前のsuccesses のcount(): ' . count($data['successes']) . '<br>';
    $data['successes'] = array_filter($data['successes'], function($time) use ($current_time, $window) {
        return ($current_time - $time) < $window;
    });
    echo 'トリム後のsuccesses のcount(): ' . count($data['successes']) . '<br><br>';

    // ✅ 2. 件数制限（最大50件）
    $max_records = 50;
    
    if (count($data['failures']) > $max_records) {
        // 古いものから削除（配列の先頭から削除）
        $data['failures'] = array_slice($data['failures'], -$max_records);
    }
    echo "'successes' の件数制限をします。12個:<br>";
    var_dump($data['successes']);
    echo '<br>';
    if (count($data['successes']) > 12) {
        $data['successes'] = array_slice($data['successes'], -12);
    }
    var_dump($data['successes']);
    echo '<br><br>';



    // 失敗回数のチェック

    if (count($data['failures']) >= 10) {
        echo " 5分以内で10回以上失敗したことが検知されました。<br>
        30分ブロック- blocking IP<br>";
        // 5分以内で10回以上失敗した場合は30分ブロック
        echo "'blocked_until' の値をvar_dumpします。:<br>";
        var_dump($data['blocked_until']);
        echo '<br>';
        $data['blocked_until'] = $current_time + 1800;
        echo "'blocked_until' の新しい値をvar_dumpします。:<br>";
        var_dump($data['blocked_until']);
        echo '<br>';
        $_SESSION[$rate_key] = $data;

                // ✅ ログ記録を追加
        error_log("レート制限ブロック開始 - IP: {$ip}, 失敗回数: " . count($data['failures']) . 
                  ", ブロック期限: " . date('Y-m-d H:i:s', $data['blocked_until']));
        $attack_severity = 'high';
        echo "High attack severity が highでセットされました。<br>";
        var_dump($attack_severity);
        echo '<br>';
        recordFailure($ip, $data); // ブロック記録として失敗を追加
        throw CSRFException::fromCurrentRequest(
            "試行回数上限: 30分間ブロック",
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_HIGH,
            null
        );
    }
    //  $rate_key の配列の情報を返す
    //  失敗情報とブロック状況を更新して
    //  return する
    //  関数外で$_SESSION[$rate_key]は
    //  変更しないのでここで更新
    $_SESSION[$rate_key] = $data;
    echo 'failures の値<br>';
    var_dump($_SESSION[$rate_key]['failures']);
    echo '<br>';
    echo 'successes の値<br>';
    var_dump($_SESSION[$rate_key]['successes']);
    echo '<br>';
    echo 'blocked_until の値<br>';
    var_dump($_SESSION[$rate_key]['blocked_until']);
    echo '<br>';
    echo 'session_resets の値<br>';
    var_dump($_SESSION[$rate_key]['session_resets']);
    echo '<br>';
    echo "Updated Rate Data:<br>";
    print_r($data);
    echo "<br>";
    return $data;
}

/**
 * CSRFトークンの検証とレート制限
 * function generate_csrf_token() {
 *   $token = bin2hex(random_bytes(32));
 *   $_SESSION['csrf_token'] = $token;
 *   $_SESSION['csrf_token_time'] = time();
 *   return $token;
 * }で生成されたトークンを使用して確認
 * .copilot-rules.md に従って実装
 * @throws CSRFException CSRFが疑われる場合にスロー
 * @return void
 * 
 */
function csrfValidation() : void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $rate_key = "csrf_attempts_{$ip}";
    $rate_data = $_SESSION[$rate_key] ?? [
        'failures'        => [],
        'successes'       => [],
        'blocked_until'   => 0,
        'session_resets'  => 0,
        'last_reset_time' => 0
    ];

    $attack_detected = false;
    $attack_severity = 'low';
    
    try {
    //  sessionにトークンがセットされていなければ
    //  throw CSRFException::fromCurrentRequest(
    //  'CSRFトークンが存在しません');
    if (!isset($_SESSION['csrf_token'])) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'セッションにCSRFトークンが存在しません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    $session_token = $_SESSION['csrf_token'];
    //  sessionにトークンタイムがセットされていなければ
    //  throw CSRFException::fromCurrentRequest(
    //  'CSRFトークンタイムが存在しません');
    if (!isset($_SESSION['csrf_token_time'])) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
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
    if (time() - $token_time > 1800) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'CSRFトークンの有効期限が切れています',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    //  POSTされたトークンがあるか確認
    if (!isset($_POST['csrf_token'])) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'POSTのCSRFトークンが存在しません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    $post_token = $_POST['csrf_token'];

    //  token が文字列か確認
    //  random_bytes()はバイナリデータで
    //  bin2hex()で16進数文字列に変換される
    if (!is_string($post_token)) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'POSTされたCSRFトークンが文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    if (!is_string($session_token)) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
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
    if (strlen($post_token) !== 64) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'ポストCSRFトークンの長さが不正です',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    if (strlen($session_token) !== 64) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'セッションのCSRFトークンの長さが不正です',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    //  ctype_xdigit()で16進数文字列か確認
    if (!ctype_xdigit($post_token) ) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'POSTされたCSRFトークンが16進数文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    if (!ctype_xdigit($session_token) ) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'セッションのCSRFトークンが16進数文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    //  referer を監視します
    //  ここでは処理を中断しません。
    //  補助的にチェックするにとどめます。
        // 5. リファラーチェック（追加のセキュリティ）
    $referer = $_SERVER['HTTP_REFERER'] ?? ''; //  アクセス元のURL
    $host = $_SERVER['HTTP_HOST'] ?? '';       //  アクセス先（さき）のドメイン
    
    //  strpos(文字列,探したい部分文字列) は、部分文字列の位置を検索します。
    //  文字列の一致を探す関数。
    if (empty($referer) || strpos($referer, $host) === false) {
        // 警告レベル（ブロックはしない）
        // 補助的な監視に留めるため、処理を止めない。

        error_log("警告: 不審なリファラー - リファラー: {$referer}, ホスト: {$host}, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }

            // 10. 使用済みトークンチェック（早期チェック）
    if (!isset($_SESSION['used_csrf_tokens'])) {
        $_SESSION['used_csrf_tokens'] = [];
    }
            //  in_array(確認したい要素, 配列, true) は、配列の中に要素が存在するか確認します。
        //  第3引数をtrueにすると型も厳密に比較します
    if (in_array($post_token, $_SESSION['used_csrf_tokens'], true)) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            '使用済みトークンの再利用',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }



    //  レート制限チェック
    //  同一ipからの連続アクセスを監視
    //  advancedRateLimit()を呼び出し
        //  advancedRateLimit()は
        //  失敗と成功を分けて記録する
        //  失敗が10回以上なら30分ブロック
        //  成功時はカウントするが$rate_dataはリセットしない
        //  呼び出し元で例外処理を行う
        //  $rate_data は現在の試行データの配列
        //  失敗回数、成功回数、ブロック期限を含む
        //  $_SESSIONに保存される
    $rate_data = advancedRateLimit($ip);

    // CSRF検証
    if (!hash_equals($post_token, $session_token)) {
        // ✅ 失敗を記録  一回だけの失敗は低'low'レベル
        $attack_severity = 'low';
        $attack_detected = true;
        //  失敗時間を配列に追加
        recordFailure($ip, $rate_data);      
        throw CSRFException::fromCurrentRequest(
            'トークン不一致',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
            );
    }
    
    // ✅ 成功を記録（失敗カウンターはリセットしない）
    error_log("CSRFトークン検証成功 - IP: {$ip}");
    cleanupAfterSuccess($post_token);
    recordSuccess($ip, $rate_data);

    } catch (CSRFException $e) {
    // ✅ 攻撃レベルに応じたクリーンアップ
    handleCSRFAttack($e, $attack_severity, $ip);
    // 例外を再スロー
    throw $e;
    }
}

// テストコード
function test() : void {


$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_key = "csrf_attempts_{$ip}";
if (!isset($_SESSION[$rate_key])) {
    $_SESSION[$rate_key] = [
        'failures'        => [],
        'successes'       => [],
        'blocked_until'   => 0,
        'session_resets'  => 0,
        'last_reset_time' => 0
    ];

}

if (!isset($_SESSION['used_csrf_tokens'])) {
    $_SESSION['used_csrf_tokens'] = [];
}

echo 'テスト１: CSRFトークン検証開始<br>';
    
echo "Initial Rate Data:<br>";
echo 'failures の値<br>';
    var_dump($_SESSION[$rate_key]['failures']);
    echo '<br>';
    echo 'successes の値<br>';
    var_dump($_SESSION[$rate_key]['successes']);
    echo '<br>';
    echo 'blocked_until の値<br>';
    var_dump($_SESSION[$rate_key]['blocked_until']);
    echo '<br>';
    echo 'session_resets の値<br>';
    var_dump($_SESSION[$rate_key]['session_resets']);
    echo '<br>';
    echo 'used_csrf_tokens の値<br>';
    var_dump($_SESSION['used_csrf_tokens']);

echo "<br>";
    $attack_severity = 'low';
    try {
    try {
    
    if (false) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        echo "失敗記録を表示します。<br>";
        print_r($rate_data['failures']);
        echo "<br>";
        echo "例外をスローします。<br>";
        throw CSRFException::fromCurrentRequest(
            'token 検証失敗',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

            // 10. 使用済みトークンチェック（早期チェック）
    if (!isset($_SESSION['used_csrf_tokens'])) {
        $_SESSION['used_csrf_tokens'] = [];
    }
    if (false) {   // "使用済みトークンで、true"
        $attack_severity = 'low';
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            '使用済みトークンの再利用',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    //  レート制限チェック
    
    $rate_data = advancedRateLimit($ip);

    // CSRF検証
    $post_token = 'token-value1';
    $session_token = 'token-value';
    $_POST['csrf_token'] = $post_token;
    if (!hash_equals($post_token, $session_token)) {
        // ✅ 失敗を記録  一回だけの失敗は低'low'レベル
        $attack_severity = 'low';
        $attack_detected = true;
        //  失敗時間を配列に追加
        recordFailure($ip, $rate_data);      
        throw CSRFException::fromCurrentRequest(
            'トークン不一致',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
            );
    }
    echo "CSRFトークンが一致しました。<br>";
    // ✅ 成功を記録（失敗カウンターはリセットしない）
    error_log("CSRFトークン検証成功 - IP: {$ip}");
    cleanupAfterSuccess($post_token);
    recordSuccess($ip, $rate_data);

    } catch (CSRFException $e) {
        echo "1回目のキャッチをしました。<br>";
        echo "CSRFException caught in inner try-catch<br>";
    // ✅ 攻撃レベルに応じたクリーンアップ
    handleCSRFAttack($e, $attack_severity, $ip);
    // 例外を再スロー
    echo "再スローします。<br>";
    throw $e;
    }
}catch (CSRFException $e) {
    echo "2回目のキャッチをしました。<br>";
    echo "CSRFException caught in outer try-catch<br>";
    echo 'CSRF検証エラー: ' . $e->getMessage() . '<br>';
    $message = $e->getLogMessage();
    error_log('CSRF検証エラー詳細: ' . $message);
    //echo 'CSRF検証エラー詳細: ' . $message . '<br>';
    $security_level = $e->getSecurityLevel();
    echo 'セキュリティレベル: ' . $security_level . '<br>';
    //var_dump($_SESSION);
}
    echo 'テスト１: CSRFトークン検証終了<br>';
echo "Final Rate Data:<br>";
    echo 'failures の値<br>';
    var_dump($_SESSION[$rate_key]['failures']);
    echo '<br>';
    echo 'successes の値<br>';
    var_dump($_SESSION[$rate_key]['successes']);
    echo '<br>';
    echo 'blocked_until の値<br>';
    var_dump($_SESSION[$rate_key]['blocked_until']);
    echo '<br>';
    echo 'session_resets の値<br>';
    var_dump($_SESSION[$rate_key]['session_resets']);
    echo '<br>';
echo "<br>";
echo "Used CSRF Tokens:<br>";
print_r($_SESSION['used_csrf_tokens'] ?? []);
echo 'failures の値<br>';
    var_dump($_SESSION[$rate_key]['failures']);
    echo '<br>';
    echo 'successes の値<br>';
    var_dump($_SESSION[$rate_key]['successes']);
    echo '<br>';
    echo 'blocked_until の値<br>';
    var_dump($_SESSION[$rate_key]['blocked_until']);
    echo '<br>';
    echo 'session_resets の値<br>';
    var_dump($_SESSION[$rate_key]['session_resets']);
    echo '<br>';
    echo 'used_csrf_tokens の値<br>';
    var_dump($_SESSION['used_csrf_tokens']);

echo "<br>";

}

// テスト実行
echo "<h1>CSRF防御テスト開始</h1>";
echo "session_start() を呼び出します。<br>";
session_start();
echo "session クリアします。<br>";
$_SESSION = []; // セッション初期化

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $rate_key = "csrf_attempts_{$ip}";
    $rate_data = $_SESSION[$rate_key] ?? [
        'failures'        => [],
        'successes'       => [],
        'blocked_until'   => 0,
        'session_resets'  => 0,
        'last_reset_time' => 0
    ];
echo "サクセス初期値<br>";
    var_dump($_SESSION[$rate_key]['successes'] ?? []);
    echo "<br>";
    for ($i = 0; $i < 20; $i++) {
        test($i);
    }
echo "<h1>CSRF防御テスト終了</h1>";
var_dump($_SESSION['failures'] ?? []);


