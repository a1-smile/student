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
$dbManager->connect();
//  PDO インスタンスを取得します。
$pdo = $dbManager->get_db();

//  RequestContent インターフェイスを使用するために、require_once します。
require_once __DIR__ . '/../interface_request_content.php';
//  RequestContentImplementation クラスを使用するために、require_once します。
require_once __DIR__ . '/../request_content_implementation.php';
// RequestContentImplementation クラスのインスタンスを作成します。
$request_content = new RequestContentImplementation();
// session_id と ip_address を取得します。
$session_id = $request_content->getSessionId();
$ip_address = $request_content->getIpAddress();

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
    $request_content,
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
$write_ua = new WriteUa(
    $pdo,
    $request_content,
    $ua_repository,
    $risk_evaluator
);


$write_ua->writeAgentLog(
      $write_ua->pdo,
      $write_ua->sessionId,
      $write_ua->ipAddress,
      $write_ua->simpleUa,
      $write_ua->isUaMismatch,
    );