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



//  ベース例外クラス
//  子クラスがインスタンス化されれば、
//  抽象クラスはインスタンス化されたようにつかえる。
//  直接インスタンス化しようとするとエラーになる。
//  抽象メソッドは、必ずしも必要ではない。
abstract class ApplicationException extends Exception {
    protected $context = [];
    
    public function __construct($message = "", $code = 0, $context = [], Throwable $previous = null) {
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
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            date('Y-m-d H:i:s')
        );
    }
}

// セキュリティ関連例外
//  SecurityException のコンストラクターの引数は、
//  メッセージ、コード、コンテキスト、セキュリティレベル、前の例外です。
class SecurityException extends ApplicationException {
    const LEVEL_LOW = 1;
    const LEVEL_MEDIUM = 2;
    const LEVEL_HIGH = 3;
    const LEVEL_CRITICAL = 4;
    
    private $securityLevel;
    //  const をクラス内で参照する場合は、self:: を使う。
    public function __construct($message = "", $code = 0, $context = [], $securityLevel = self::LEVEL_MEDIUM, Throwable $previous = null) {
        parent::__construct($message, $code, $context, $previous);
        $this->securityLevel = $securityLevel;
    }
    
    public function getSecurityLevel() {
        return $this->securityLevel;
    }
    
    public function isCritical() {
        return $this->securityLevel >= self::LEVEL_HIGH;
//         $this->securityLevel: インスタンスのセキュリティレベル（数値）
//         >=: 以上（大なりイコール）
//         self::LEVEL_HIGH: 定数の値（3）
//         結果: セキュリティレベルが「高」以上かを判定するboolean値を返す
    }
}

// CSRF攻撃専用例外
class CSRFException extends SecurityException {
    public function __construct($message = "", $code = 0, $context = [], Throwable $previous = null) {
        parent::__construct($message, $code, $context, self::LEVEL_HIGH, $previous);
    }
}

// データベース例外
//  DatabaseException のコンストラクターの引数は、
//  メッセージ、コード、コンテキスト、SQLSTATE、ドライバーコード、前の例外です。
class DatabaseException extends ApplicationException {
    private $sqlState;
    private $driverCode;
    
    public function __construct($message = "", $code = 0, $context = [], $sqlState = null, $driverCode = null, Throwable $previous = null) {
        parent::__construct($message, $code, $context, $previous);
        $this->sqlState = $sqlState;
        $this->driverCode = $driverCode;
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
class BusinessLogicException extends ApplicationException {
    const ERR_STUDENT_NOT_FOUND = 1001;
    const ERR_INVALID_DATA = 1002;
    const ERR_DATABASE_ERROR = 1003;
    const ERR_PERMISSION_DENIED = 1004;
    
    public static function getErrorMessage($code) {
        switch ($code) {
            case self::ERR_STUDENT_NOT_FOUND:
                return '学生情報が見つかりません';
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
class ValidationException extends ApplicationException {
    private $field;
    private $value;
    
    public function __construct($message = "", $field = null, $value = null, $code = 0, $context = [], Throwable $previous = null) {
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
?>