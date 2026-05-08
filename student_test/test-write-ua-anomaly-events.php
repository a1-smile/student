<?php
/*
student/student_test/test-write-ua-anomaly-events.php
では、
student/write-ua.php
に定義されている
class WriteUaのwriteUaAnomalyEvents() メソッドをテストします。
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
require_once __DIR__ . '/../mock_request_content.php';
require_once __DIR__ . '/../interface_ua_repository.php';
require_once __DIR__ . '/../mock_ua_repository.php';
require_once __DIR__ . '/../ua_repository_implementation.php';
require_once __DIR__ . '/../risk_evaluation_result.php';
require_once __DIR__ . '/../user_agent_risk_evaluator.php';
require_once __DIR__ . '/../write-ua.php';

// 全ケース合計のカウンタ
$totalPass = 0;
$totalFail = 0;

// check() 関数を定義します。
function check(string $label, mixed $actual, mixed $expected): void {
    global $totalPass, $totalFail;
    if ($actual === $expected) {
        echo "  PASS: {$label}<br>";
        $totalPass++;
    } else {
        echo "  FAIL: {$label}"
            . " (actual=" . var_export($actual, true)
            . ", expected=" . var_export($expected, true) . ")<br>";
        $totalFail++;
    }
}

/**
 * 1ケース分のテストを実行します。
 * テーブルをTRUNCATEし、writeUaAnomalyEvents()を呼び出し、
 * SELECTで取得した値と期待値を照合します。
 */

function runTestCase(
            string $caseLabel,
            array $contents,
            array $uaData,
            PDO $pdo
    ): void {
    echo "<b>{$caseLabel}</b><br>";

    // table ua_anomaly_events をクリア
    try {
        $pdo->exec("TRUNCATE TABLE ua_anomaly_events");
    } catch (Exception $e) {
        echo "  Error truncating table: " . $e->getMessage() . "<br><br>";
        return;
    }

    $mock = new MockRequestContent1($contents);

    $mock_ua_repository  = new MockUaRepository($uaData);
    $risk_evaluator = new UserAgentRiskEvaluator($mock, $mock_ua_repository);
    $write_ua       = new WriteUa($pdo, $mock, $mock_ua_repository, $risk_evaluator);

    $write_ua->writeUaAnomalyEvents();

    // SELECTで取得して期待値と照合する
    try {
        $stmt = $pdo->query("SELECT * FROM ua_anomaly_events ORDER BY id DESC LIMIT 1");
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo "  Error querying table: " . $e->getMessage() . "<br><br>";
        return;
    }
    if ($row === false) {
        echo "  FAIL: No log entry found in ua_anomaly_events.<br><br>";
        return;
    }

    check('session_id',     $row['session_id'],          $mock->getSessionId());
    check('ip_address',     $row['ip_address'],          $mock->getIpAddress());
    check('is_no_ua',       (int)$row['is_no_ua'],       $mock->getIsNoUa());
    check('is_ua_mismatch', (int)$row['is_ua_mismatch'], $mock->getIsUaMismatch());
    check('is_over_threshold_session', (int)$row['is_over_threshold_session'], $risk_evaluator->getIsOverThresholdSession());
    check('is_over_threshold_ip', (int)$row['is_over_threshold_ip'], $risk_evaluator->getIsOverThresholdIp());

    $access_time = new DateTime($row['access_time']);
    $now  = new DateTime();
    $diff = $now->getTimestamp() - $access_time->getTimestamp();
    check('access_time', $diff >= 0 && $diff < 5, true);

    echo "<br>";
}


function runTestCaseError(
            string $caseLabel,
            array $contents,
            array $uaData,
            PDO $pdo
    ): void {
    echo "<b>{$caseLabel}</b><br>";

    // table ua_anomaly_events をクリア
    try {
        $pdo->exec("TRUNCATE TABLE ua_anomaly_events");
    } catch (Exception $e) {
        echo "  Error truncating table: " . $e->getMessage() . "<br><br>";
        return;
    }

    $mock = new MockRequestContent1($contents);

    $mock_ua_repository  = new MockUaRepository($uaData);
    $risk_evaluator = new UserAgentRiskEvaluator($mock, $mock_ua_repository);
    $write_ua       = new WriteUa($pdo, $mock, $mock_ua_repository, $risk_evaluator);

    try {
        $write_ua->writeUaAnomalyEvents();
        echo " FAIL: 例外が発生しませんでした。<br><br>";
    } catch (RuntimeException $e) {
        echo "  PASS: 例外が発生しました: " . $e->getMessage() . "<br><br>";
        return;
    }

    // SELECTで取得して期待値と照合する
    try {
        $stmt = $pdo->query("SELECT * FROM ua_anomaly_events ORDER BY id DESC LIMIT 1");
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo "  Error querying table: " . $e->getMessage() . "<br><br>";
        return;
    }
    if ($row === false) {
        echo "  FAIL: No log entry found in ua_anomaly_events.<br><br>";
        return;
    }

    check('session_id',     $row['session_id'],          $mock->getSessionId());
    check('ip_address',     $row['ip_address'],          $mock->getIpAddress());
    check('is_no_ua',       (int)$row['is_no_ua'],       $mock->getIsNoUa());
    check('simple_ua',      $row['simple_ua'],           $mock->getCurrentSimpleUa());
    check('is_ua_mismatch', (int)$row['is_ua_mismatch'], $mock->getIsUaMismatch());
    check('is_over_threshold_session', (int)$row['is_over_threshold_session'], $risk_evaluator->getIsOverThresholdSession());
    check('is_over_threshold_ip', (int)$row['is_over_threshold_ip'], $risk_evaluator->getIsOverThresholdIp());
    $access_time = new DateTime($row['access_time']);
    $now  = new DateTime();
    $diff = $now->getTimestamp() - $access_time->getTimestamp();
    check('access_time', $diff >= 0 && $diff < 5, true);

    echo "<br>";
}


// -------------------------------------------------------
// Case 1: 正常系（閾値以下）
// -------------------------------------------------------
$contents = [
    'session_id'      => 'abc123',
    'ip_address'      => '192.168.0.1',
    'simple_ua'       => 'Chrome/91',
    'is_no_ua'        => 0,
    'is_ua_mismatch'  => 0,
    'recaptcha_solved' => 0,
    'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
];
$uaData = [
    'score_session' => 0,
    'score_ip' => 0,
    'access_count_session' => 0,// 閾値 60
    'access_count_ip' => 0, // 閾値 600
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 1, // 異常なし
    'is_no_anomaly_ip' => 1       // 異常なし
];
runTestCase(
    'Case 1: 正常系（閾値以下）',
    $contents,
    $uaData,
    $pdo);

// -------------------------------------------------------
// Case 2: 異常系（is_ua_mismatch = 1）
//   UA不一致フラグが 1 のとき、DBに 1 で記録されるか確認します。
// -------------------------------------------------------
runTestCase('Case 2: 異常系 (is_ua_mismatch = 1)', [
    'session_id'      => 'abc123',
    'ip_address'      => '192.168.0.1',
    'simple_ua'       => 'Chrome/91',   // 前回UA
    'is_no_ua'        => 0,
    'is_ua_mismatch'  => 1,
    'recaptcha_solved' => 0,
    'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
], $pdo);

// -------------------------------------------------------
// Case 3: 異常系（session_id が 128 文字の長い文字列）
//   VARCHAR(128) の境界値で正しくINSERTされるか確認します。
// -------------------------------------------------------
runTestCase('Case 3: 異常系 (session_id が 128 文字の長い文字列)', [
    'session_id'      => str_repeat('s', 128),
    'ip_address'      => '10.0.0.1',
    'simple_ua'       => 'Chrome/91',
    'is_no_ua'        => 0,
    'is_ua_mismatch'  => 0,
    'recaptcha_solved' => 0,
    'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
], $pdo);

//  データベース接続を閉じます。
//$dbManager->disconnect();
//echo "Result: {$totalPass} passed, {$totalFail} failed.<br>";


// -------------------------------------------------------
// Case 4: 異常系（session_id が 129 文字の長い文字列）
//   VARCHAR(128) の上限を超えた場合にエラーになるか確認します。
//  
//  mysql の設定が STRICT_TRANS_TABLES です。
//  VARCHAR(128)は128文字まで です。
//  129 文字以上の session_id を挿入しようとすると、エラーになるはずです。
// -------------------------------------------------------
runTestCaseError('Case 4: 異常系 (session_id が 129 文字の長い文字列)', [
    'session_id'      => str_repeat('s', 129),
    'ip_address'      => '10.0.0.1',
    'simple_ua'       => 'Chrome/91',
    'is_no_ua'        => 0,
    'is_ua_mismatch'  => 0,
    'recaptcha_solved' => 0,
    'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
], $pdo);

//  データベース接続を閉じます。
$dbManager->disconnect();
echo "Result: {$totalPass} passed, {$totalFail} failed.<br>";

