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
require_once __DIR__ . '/../mock_request_content.php';
require_once __DIR__ . '/../interface_ua_repository.php';
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
 * テーブルをTRUNCATEし、writeUserAgentLog()を呼び出し、
 * SELECTで取得した値と期待値を照合します。
 */
function runTestCase(string $caseLabel, array $contents, PDO $pdo): void {
    echo "<b>{$caseLabel}</b><br>";

    // table user_agent_logs をクリア
    try {
        $pdo->exec("TRUNCATE TABLE user_agent_logs");
    } catch (Exception $e) {
        echo "  Error truncating table: " . $e->getMessage() . "<br><br>";
        return;
    }

    $mock = new MockRequestContent1($contents);
    $session_id_to_db = $mock->getSessionId();
    $ip_address_to_db = $mock->getIpAddress();

    $ua_repository  = new UaRepositoryImplementation($pdo, $session_id_to_db, $ip_address_to_db);
    $risk_evaluator = new UserAgentRiskEvaluator($mock, $ua_repository);
    $write_ua       = new WriteUa($pdo, $mock, $ua_repository, $risk_evaluator);

    $write_ua->writeUserAgentLog();

    // SELECTで取得して期待値と照合する
    try {
        $stmt = $pdo->query("SELECT * FROM user_agent_logs ORDER BY id DESC LIMIT 1");
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo "  Error querying table: " . $e->getMessage() . "<br><br>";
        return;
    }
    if (!$row) {
        echo "  FAIL: No log entry found in user_agent_logs.<br><br>";
        return;
    }

    check('session_id',     $row['session_id'],          $mock->getSessionId());
    check('ip_address',     $row['ip_address'],          $mock->getIpAddress());
    check('simple_ua',      $row['simple_ua'],           $mock->getCurrentSimpleUa());
    check('is_ua_mismatch', (int)$row['is_ua_mismatch'], $mock->getIsUaMismatch());

    $access_time = new DateTime($row['access_time']);
    $now  = new DateTime();
    $diff = $now->getTimestamp() - $access_time->getTimestamp();
    check('access_time', $diff >= 0 && $diff < 5, true);

    echo "<br>";
}

// -------------------------------------------------------
// Case 1: 正常系（is_ua_mismatch = 0）
// -------------------------------------------------------
runTestCase('Case 1: 正常系 (is_ua_mismatch = 0)', [
    'session_id'      => 'abc123',
    'ip_address'      => '192.168.0.1',
    'simple_ua'       => 'Chrome/91',
    'is_no_ua'        => 0,
    'is_ua_mismatch'  => 0,
    'recaptcha_solved' => 0,
    'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
], $pdo);

// -------------------------------------------------------
// Case 2: 異常系（is_ua_mismatch = 1）
//   UA不一致フラグが 1 のとき、DBに 1 で記録されるか確認します。
// -------------------------------------------------------
runTestCase('Case 2: 異常系 (is_ua_mismatch = 1)', [
    'session_id'      => 'abc123',
    'ip_address'      => '192.168.0.1',
    'simple_ua'       => 'Chrome/91',   // 前回UA（不一致を想定）
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
$dbManager->disconnect();
echo "Result: {$totalPass} passed, {$totalFail} failed.<br>";
