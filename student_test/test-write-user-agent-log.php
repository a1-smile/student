<?php
/*
student/student_test/test-write-user-agent-log.php
では、
student/write-ua.php
に定義されている
class WriteUaのwriteUserAgentLog() メソッドをテストします。
*/
//  タイムゾーンを明示的に設定します。
date_default_timezone_set('Asia/Tokyo');
//  DBManager クラスを使用するために、require_once します。
require_once __DIR__ . '/../common/dbmanager.php';
//  DBManager を new します。
$dbManager = new DBManager();
//  データベースに接続します。
try {
    $dbManager->connect();
    echo "Database connection successful.<br><br>";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "<br><br>";
    exit(1);
}
//  PDO インスタンスを取得します。
$pdo = $dbManager->get_db();

//  RequestContent インターフェイスを使用するために、require_once します。
require_once __DIR__ . '/../interface_request_content.php';
//  RequestContentImplementation クラスの
//  静的メソッド を使用するために、require_once します。
require_once __DIR__ . '/../request_content_implementation.php';
// RequestContentImplementation クラスのインスタンスは使用
// しません。

//  後述のMockRequestContent1 クラスのコンストラクタに渡すための配列を用意します。
$contents = [
        'session_id' => 'abc123',
        'ip_address' => '192.168.0.1',

        // こちらのsimple_ua は画面遷移前のUAを想定しています。
        //  現在のsimple_ua は、静的メソッド
        //  RequestContentImplementation::makeSimpleUa() を
        // 呼び出して,
        // $currentSimpleUa を取得するようになっています。
        // この値は、コンストラクタの引数として渡す必要があります。
        // DBに保存される値ではありません。
        'simple_ua' => 'Mozilla/5.0', // previous



        'is_no_ua' => 0,
        'is_ua_mismatch' => 0,
        'recaptcha_solved' => 0,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    ];  

//  mock_request_content.php
  require_once __DIR__ . '/../mock_request_content.php';

$mock_request_content = new MockRequestContent1(
    $contents
);

$session_id = $mock_request_content->getSessionId();
$ip_address = $mock_request_content->getIpAddress();

//  interface_ua_repository.php
require_once __DIR__ . '/../interface_ua_repository.php';
//  ua_repository_implementation.php
require_once __DIR__ . '/../ua_repository_implementation.php';
//  class UaRepositoryImplementation implements UaRepository を new します。
$ua_repository = new UaRepositoryImplementation(
    $pdo,
    $session_id,
    $ip_address
    );

//  risk_evaluation_result.php
require_once __DIR__ . '/../risk_evaluation_result.php';
//  user_agent_risk_evaluator.php
//  public function __construct(RequestContent $requestContent, UaRepository $uaRepository)
require_once __DIR__ . '/../user_agent_risk_evaluator.php';
//  UserAgentRiskEvaluator クラスのインスタンスを作成します。
$risk_evaluator = new UserAgentRiskEvaluator(
    $mock_request_content,
    $ua_repository
);

//  WriteUaの writeUserAgentLog() が書き込むと期待される値を取得します。
        // $session_id    = $mock_request_content->getSessionId();
        // $ip_address    = $mock_request_content->getIpAddress();
        $simpleUa     = $mock_request_content->getCurrentSimpleUa();
        $isUaMismatch = $mock_request_content->getIsUaMismatch();

//  WriteUa クラスを使用するために、require_once します。
require_once __DIR__ . '/../write-ua.php';

//  table user_agent_logs をクリア
try {
    $pdo->exec("TRUNCATE TABLE user_agent_logs");
    echo "Table user_agent_logs truncated successfully.<br><br>";
} catch (Exception $e) {
    echo "Error truncating table: " . $e->getMessage() . "<br><br>";
}

$write_ua = new WriteUa(
    $pdo,
    $mock_request_content,
    $ua_repository,
    $risk_evaluator
);

$current_simple_ua = RequestContentImplementation::makeSimpleUa($contents['user_agent']);
$write_ua->writeUserAgentLog();    

// SELECTで取得して期待値と照合する
try {
$stmt = $pdo->query("SELECT * FROM user_agent_logs ORDER BY id DESC LIMIT 1");

$row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "Error querying table: " . $e->getMessage() . "<br><br>";
    $dbManager->disconnect();
    exit(1);
}
if (!$row) {
    echo "No log entry found in user_agent_logs.<br><br>";
    $dbManager->disconnect();
    exit(1);
}

// 改善例: check() 関数でカウントする
$passCount = 0;
$failCount = 0;

function check(string $label, mixed $actual, mixed $expected): void {
    global $passCount, $failCount;
    if ($actual === $expected) {
        echo "PASS: {$label}<br>";
        $passCount++;
    } else {
        echo "FAIL: {$label} ...<br>";
        $failCount++;
    }
}

check('session_id',    $row['session_id'],           'abc123');
check('ip_address',    $row['ip_address'],           '192.168.0.1');
check('simple_ua',     $row['simple_ua'],            $current_simple_ua);
check('is_ua_mismatch',(int)$row['is_ua_mismatch'],  0);

$access_time = new DateTime($row['access_time']);
$now = new DateTime();
$diff = $now->getTimestamp() - $access_time->getTimestamp();
assert($diff >= 0 && $diff < 5, 'access_time is not recent');

check('access_time', $diff >= 0 && $diff < 5, true  );

echo "Check the user_agent_logs table for the inserted log.<br><br>";
//  データベース接続を閉じます。
$dbManager->disconnect();
echo "Result: {$passCount} passed, {$failCount} failed.<br>";
