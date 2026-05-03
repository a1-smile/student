<?php
/*
student/student_test/test-write-ua.php
では、
student/write-ua.php
に定義されている
class WriteUaのテストを行います。

PDO を取得するために、DBManager クラスを使用します。
student/common/dbmanager.php
DBManager

student/interface_request_content.php
をrequire_once して、RequestContent インターフェイスも使用します。
interface RequestContent

student/request_content_implementation.php
class RequestContentImplementation implements RequestContent
__construct()

interface_ua_repository.php
をrequire_once して、UaRepository インターフェイスも使用します。
interface UaRepository


*/

//  DBManager クラスを使用するために、require_once します。
require_once __DIR__ . '/../common/dbmanager.php';
//  DBManager を new します。
$dbManager = new DBManager();
//  データベースに接続します。
try {
    $dbManager->connect();
    echo "Database connection successful.\n";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}
//  PDO インスタンスを取得します。
$pdo = $dbManager->get_db();

//  RequestContent インターフェイスを使用するために、require_once します。
require_once __DIR__ . '/../interface_request_content.php';
//  RequestContentImplementation クラスを使用するために、require_once します。

//  静的メソッド を使用するために、require_once します。
require_once __DIR__ . '/../request_content_implementation.php';
// RequestContentImplementation クラスのインスタンスは使用
// しませんので、以下はコメントアウトします。
// $request_content = new RequestContentImplementation();

// session_id と ip_address を取得します。

$contents = [
        'session_id' => 'abc123',
        'ip_address' => '192.168.0.1',
        'simple_ua' => 'Mozilla/5.0',
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


$mock_session_id = $mock_request_content->getSessionId();
$mock_ip_address = $mock_request_content->getIpAddress();

//  interface_ua_repository.php
require_once __DIR__ . '/../interface_ua_repository.php';
//  ua_repository_implementation.php
require_once __DIR__ . '/../ua_repository_implementation.php';
//  class UaRepositoryImplementation implements UaRepository を new します。
$ua_repository = new UaRepositoryImplementation(
    $pdo,
    $mock_session_id,
    $mock_ip_address
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

//  WriteUa クラスを使用するために、require_once します。
require_once __DIR__ . '/../write-ua.php';
//  WriteUa クラスのインスタンスを作成します。
//   public function __construct(
//     PDO $pdo,
//     RequestContent $request_content,
//     UaRepository $ua_repository,
//     UserAgentRiskEvaluator $user_agent_risk_evaluator
//     )
// $write_ua = new WriteUa(
//     $pdo,
//     $request_content,
//     $ua_repository,
//     $risk_evaluator
// );


// $write_ua->writeAgentLog(
//       $write_ua->pdo,
//       $write_ua->sessionId,
//       $write_ua->ipAddress,
//       $write_ua->simpleUa,
//       $write_ua->isUaMismatch,
//     );


//  table user_agent_logs をクリア
try {
    $pdo->exec("TRUNCATE TABLE user_agent_logs");
    echo "Table user_agent_logs truncated successfully.\n";
} catch (Exception $e) {
    echo "Error truncating table: " . $e->getMessage() . "\n";
}



$mock_write_ua = new WriteUa(
    $pdo,
    $mock_request_content,
    $ua_repository,
    $risk_evaluator
);


$mock_write_ua->writeUserAgentLog();    


echo "writeUserAgentLog executed successfully.\n";
echo "Check the user_agent_logs table for the inserted log.\n";
