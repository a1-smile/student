<?php

//  interface RequestContent などを
//  require_once
require_once __DIR__ . '/../interface_request_content.php';
require_once __DIR__ . '/../interface_ua_repository.php';

require_once __DIR__ . '/../mock_request_content.php';
require_once __DIR__ . '/../mock_ua_repository.php';

require_once __DIR__ . '/../risk_evaluation_result.php';

require_once __DIR__ . '/../user_agent_risk_evaluator.php';


//  class MockRequestContent1 implements RequestContent を
//  new する。
$request_context_array = [
    'is_no_ua' => 0,
    'is_ua_mismatch' => 0,
    'recaptcha_solved' => 0,
];
$mockRequestContent1 = new MockRequestContent1($request_context_array);

//  class MockUaRepository1 implements UaRepository を
//  new する。
$records_array = [
    'score_session' => 0,
    'score_ip' => 0,
    'access_count_session' => 0,
    'access_count_ip' => 0,
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 0,
    'is_no_anomaly_ip' => 0
];
$mockUaRepository1 = new MockUaRepository($records_array);

//  class UserAgentRiskEvaluator を new する。
$userAgentRiskEvaluator = 
new UserAgentRiskEvaluator(
    $mockRequestContent1,
    $mockUaRepository1
);
//  UserAgentRiskEvaluator->evaluate() を呼び出す。
$result = $userAgentRiskEvaluator->evaluate();

//  RiskEvaluationResult->getSecurityLevel() を呼び出す。
$securityLevel = $result->getSecurityLevel();

//  $result->getAccessDecision() を呼び出す。
$accessDecision = $result->getAccessDecision();

//  $securityLevel と $accessDecision を出力する。
echo '-----------------------------<br>';
echo '-----------------------------<br>';

echo "Security Level: " . $securityLevel . "<br>";
echo 'expected: ' . RiskEvaluationResult::LEVEL_LOW . "<br>";
echo '-----------------------------<br>';
echo "Access Decision: " . $accessDecision . "<br>";
echo 'expected: ' . RiskEvaluationResult::ALLOW . "<br>";
echo '-----------------------------<br>';
echo '-----------------------------<br>';


//  class MockRequestContent1 implements RequestContent を
//  new する。
$request_context_array = [
    'is_no_ua' => 1,
    'is_ua_mismatch' => 0,
    'recaptcha_solved' => 0,
];
$mockRequestContent1 = new MockRequestContent1($request_context_array);

//  class MockUaRepository1 implements UaRepository を
//  new する。
$records_array = [
    'score_session' => 2,
    'score_ip' => 0,
    'access_count_session' => 0,
    'access_count_ip' => 0,
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 0,
    'is_no_anomaly_ip' => 0
];
$mockUaRepository1 = new MockUaRepository($records_array);

//  class UserAgentRiskEvaluator を new する。
$userAgentRiskEvaluator = 
new UserAgentRiskEvaluator(
    $mockRequestContent1,
    $mockUaRepository1
);
//  UserAgentRiskEvaluator->evaluate() を呼び出す。
$result = $userAgentRiskEvaluator->evaluate();

//  RiskEvaluationResult->getSecurityLevel() を呼び出す。
$securityLevel = $result->getSecurityLevel();

//  $result->getAccessDecision() を呼び出す。
$accessDecision = $result->getAccessDecision();

//  $securityLevel と $accessDecision を出力する。
echo '-----------------------------<br>';
echo '-----------------------------<br>';

echo "Security Level: " . $securityLevel . "<br>";
echo 'expected: ' . RiskEvaluationResult::LEVEL_MEDIUM . "<br>";
echo '-----------------------------<br>';
echo "Access Decision: " . $accessDecision . "<br>";
echo 'expected: ' . RiskEvaluationResult::REQUIRE_CAPTCHA . "<br>";
echo '-----------------------------<br>';
echo '-----------------------------<br>';


// 3. 最大スコアが 5 の場合: STOP_MOMENTARY / LEVEL_HIGH
$request_context_array = [
    'is_no_ua' => 0,
    'is_ua_mismatch' => 0,
    'recaptcha_solved' => 0,
];
$mockRequestContent1 = new MockRequestContent1($request_context_array);

$records_array = [
    'score_session' => 5,
    'score_ip' => 0,
    'access_count_session' => 0,
    'access_count_ip' => 0,
    'is_decreased_session' => 1,
    'is_decreased_ip' => 1,
    'is_no_anomaly_session' => 0,
    'is_no_anomaly_ip' => 0
];
$mockUaRepository1 = new MockUaRepository($records_array);

$userAgentRiskEvaluator = new UserAgentRiskEvaluator(
    $mockRequestContent1,
    $mockUaRepository1
);

$result = $userAgentRiskEvaluator->evaluate();
$securityLevel = $result->getSecurityLevel();
$accessDecision = $result->getAccessDecision();

echo '-----------------------------<br>';
echo '-----------------------------<br>';

echo "Security Level: " . $securityLevel . "<br>";
echo 'expected: ' . RiskEvaluationResult::LEVEL_HIGH . "<br>";
echo '-----------------------------<br>';
echo "Access Decision: " . $accessDecision . "<br>";
echo 'expected: ' . RiskEvaluationResult::STOP_MOMENTARY . "<br>";
echo '-----------------------------<br>';
echo '-----------------------------<br>';


// 4. 最大スコアが 10 の場合: LOGOUT / LEVEL_CRITICAL
$request_context_array = [
    'is_no_ua' => 0,
    'is_ua_mismatch' => 0,
    'recaptcha_solved' => 0,
];
$mockRequestContent1 = new MockRequestContent1($request_context_array);

$records_array = [
    'score_session' => 10,
    'score_ip' => 0,
    'access_count_session' => 0,
    'access_count_ip' => 0,
    'is_decreased_session' => 1,
    'is_decreased_ip' => 1,
    'is_no_anomaly_session' => 0,
    'is_no_anomaly_ip' => 0
];
$mockUaRepository1 = new MockUaRepository($records_array);

$userAgentRiskEvaluator = new UserAgentRiskEvaluator(
    $mockRequestContent1,
    $mockUaRepository1
);

$result = $userAgentRiskEvaluator->evaluate();
$securityLevel = $result->getSecurityLevel();
$accessDecision = $result->getAccessDecision();

echo '-----------------------------<br>';
echo '-----------------------------<br>';

echo "Security Level: " . $securityLevel . "<br>";
echo 'expected: ' . RiskEvaluationResult::LEVEL_CRITICAL . "<br>";
echo '-----------------------------<br>';
echo "Access Decision: " . $accessDecision . "<br>";
echo 'expected: ' . RiskEvaluationResult::LOGOUT . "<br>";
echo '-----------------------------<br>';
echo '-----------------------------<br>';


// 5. is_no_anomaly によるスコア減算 (-1) の確認
$request_context_array = [
    'is_no_ua' => 0,
    'is_ua_mismatch' => 0,
    'recaptcha_solved' => 0,
];
$mockRequestContent1 = new MockRequestContent1($request_context_array);

$records_array = [
    'score_session' => 5,
    'score_ip' => 0,
    'access_count_session' => 0,
    'access_count_ip' => 0,
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 1,
    'is_no_anomaly_ip' => 0
];
$mockUaRepository1 = new MockUaRepository($records_array);

$userAgentRiskEvaluator = new UserAgentRiskEvaluator(
    $mockRequestContent1,
    $mockUaRepository1
);

$result = $userAgentRiskEvaluator->evaluate();
$securityLevel = $result->getSecurityLevel();
$accessDecision = $result->getAccessDecision();

echo '-----------------------------<br>';
echo '-----------------------------<br>';

echo "Security Level: " . $securityLevel . "<br>";
echo 'expected: ' . RiskEvaluationResult::LEVEL_MEDIUM . "<br>";
echo '-----------------------------<br>';
echo "Access Decision: " . $accessDecision . "<br>";
echo 'expected: ' . RiskEvaluationResult::REQUIRE_CAPTCHA . "<br>";
echo '-----------------------------<br>';
echo '-----------------------------<br>';


//  STOP_MOMENTARY：3
$request_context_array = [
    'is_no_ua' => 0,
    'is_ua_mismatch' => 1,
    'recaptcha_solved' => 1,
];
$mockRequestContent1 = new MockRequestContent1($request_context_array);

$records_array = [
    'score_session' => 4,
    'score_ip' => 0,
    'access_count_session' => 60, //over threshold
    'access_count_ip' => 0,
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 1,
    'is_no_anomaly_ip' => 0
];
$mockUaRepository1 = new MockUaRepository($records_array);

$userAgentRiskEvaluator = new UserAgentRiskEvaluator(
    $mockRequestContent1,
    $mockUaRepository1
);

$result = $userAgentRiskEvaluator->evaluate();
$securityLevel = $result->getSecurityLevel();
$accessDecision = $result->getAccessDecision();

echo '-----------------------------<br>';
echo '-----------------------------<br>';

echo "Security Level: " . $securityLevel . "<br>";
echo 'expected: ' . RiskEvaluationResult::LEVEL_HIGH . "<br>";
echo '-----------------------------<br>';
echo "Access Decision: " . $accessDecision . "<br>";
echo 'expected: ' . RiskEvaluationResult::STOP_MOMENTARY . "<br>";
echo '-----------------------------<br>';
echo '-----------------------------<br>';


// recaptcha_solved によるスコア減算 (-4) の確認
$request_context_array = [
    'is_no_ua' => 0,
    'is_ua_mismatch' => 0,
    'recaptcha_solved' => 1,
];
$mockRequestContent1 = new MockRequestContent1($request_context_array);

$records_array = [
    'score_session' => 5,
    'score_ip' => 0,
    'access_count_session' => 0,
    'access_count_ip' => 0,
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 0,
    'is_no_anomaly_ip' => 0
];
$mockUaRepository1 = new MockUaRepository($records_array);

$userAgentRiskEvaluator = new UserAgentRiskEvaluator(
    $mockRequestContent1,
    $mockUaRepository1
);

$result = $userAgentRiskEvaluator->evaluate();
$securityLevel = $result->getSecurityLevel();
$accessDecision = $result->getAccessDecision();

echo '-----------------------------<br>';
echo '-----------------------------<br>';

echo "Security Level: " . $securityLevel . "<br>";
echo 'expected: ' . RiskEvaluationResult::LEVEL_LOW . "<br>";
echo '-----------------------------<br>';
echo "Access Decision: " . $accessDecision . "<br>";
echo 'expected: ' . RiskEvaluationResult::ALLOW . "<br>";
echo '-----------------------------<br>';
echo '-----------------------------<br>';