<?php

//  interface RequestContent を
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
    'is_recaptcha_solved' => 0,
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
$mockUaRepository1 = new MockUaRepository1($records_array);
