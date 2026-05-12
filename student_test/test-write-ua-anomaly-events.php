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

//  $accessCount の
//  閾値を定数として定義します。
const UNDER_THRESHOLD_SESSION = 59;
const ACCESS_COUNT_THRESHOLD_SESSION = 60;
const OVER_THRESHOLD_SESSION = 61;
const UNDER_THRESHOLD_IP = 599;
const ACCESS_COUNT_THRESHOLD_IP = 600;
const OVER_THRESHOLD_IP = 601;

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
    try {
        $write_ua->writeUaAnomalyEvents();
    } catch (RuntimeException $e) {
        echo "  Error writing anomaly events: " . $e->getMessage() . "<br><br>";
         http_response_code(500); // 500 Internal Server Error を返す場合
         return;
    } catch (Exception $e) {
        echo "  Unexpected error: " . $e->getMessage() . "<br><br>";
        return;
    }  // END try-catch
    
    // 追加されたidを取得。
    $id = (int)$pdo->lastInsertId();

    // SELECTで取得して期待値と照合する
    try {
        $stmt = $pdo->prepare("SELECT * FROM ua_anomaly_events WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
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
    } //

} // END function


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
// Case 2: 異常系（ua_mismatch = 1）
//   記録されるか確認します。
// -------------------------------------------------------
$contents2 = [
    'session_id'      => 'def456',
    'ip_address'      => '192.168.0.2',
    'simple_ua'       => 'Firefox/89', // previous simple_ua
    'is_no_ua'        => 0,
    'is_ua_mismatch'  => 1,
    'recaptcha_solved' => 0,
    'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
];
$uaData2 = [
    'score_session' => 0,
    'score_ip' => 0,
    'access_count_session' => 0,// 閾値 60
    'access_count_ip' => 0, // 閾値 600
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 1, // 異常なし
    'is_no_anomaly_ip' => 1       // 異常なし
];
runTestCase('Case 2: 異常系 (ua_mismatch = 1)',
    $contents2,
    $uaData2,
    $pdo);

// -------------------------------------------------------
// Case 3: 異常系（session_id が 128 文字の長い文字列）
//   VARCHAR(128) の境界値で正しくINSERTされるか確認します。
// -------------------------------------------------------
    $contents3 = [
    'session_id'      => str_repeat('s', 128),
    'ip_address'      => '10.0.0.1',
    'simple_ua'       => 'Chrome/91',
    'is_no_ua'        => 0,
    'is_ua_mismatch'  => 0,
    'recaptcha_solved' => 0,
    'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ];
    $uaData3 = [
        'score_session' => 0,
        'score_ip' => 0,
        'access_count_session' => 0,// 閾値 60
        'access_count_ip' => 0, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
runTestCase('Case 3: 異常系 (session_id が 128 文字の長い文字列)', 
    $contents3,
    $uaData3,
    $pdo);

// -------------------------------------------------------
// Case 4: SESSION UNDER THRESHOLD
//  (59) 
//  ($accessCount >= 60) ===  false であることを確認します。
// -------------------------------------------------------
    $contents4 = [
        'session_id'       => 'def456',
        'ip_address'       => '10.0.0.1',
        'simple_ua'        => 'Chrome/91',
        'is_no_ua'         => 0,
        'is_ua_mismatch'   => 0,
        'recaptcha_solved' => 0,
        'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ];
    $uaData4 = [
        'score_session' => 0,
        'score_ip' => 0,
        'access_count_session' => SESSION_UNDER_THRESHOLD,// 閾値 60
        'access_count_ip' => 0, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
runTestCase('Case 4: SESSION UNDER THRESHOLD (59)', 
    $contents4,
    $uaData4,
    $pdo);

// -------------------------------------------------------
// Case 5: SESSION THRESHOLD
//  (60) 
//  ($accessCount >= 60) ===  true であることを確認します。
// -------------------------------------------------------
    $contents5 = [
        'session_id'       => 'def456',
        'ip_address'       => '10.0.0.1',
        'simple_ua'        => 'Chrome/91',
        'is_no_ua'         => 0,
        'is_ua_mismatch'   => 0,
        'recaptcha_solved' => 0,
        'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ];
    $uaData5 = [
        'score_session' => 0,
        'score_ip' => 0,
        'access_count_session' => SESSION_THRESHOLD,// 閾値 60
        'access_count_ip' => 0, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
runTestCase('Case 5: SESSION THRESHOLD (60)', 
    $contents5,
    $uaData5,
    $pdo);


// -------------------------------------------------------
// Case 6: SESSION OVER THRESHOLD
//  (61) 
//  ($accessCount >= 60) ===  true であることを確認します。
// -------------------------------------------------------
    $contents6 = [
    'session_id'       => 'def456',
    'ip_address'       => '10.0.0.1',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 0,
    'is_ua_mismatch'   => 0,
    'recaptcha_solved' => 0,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ];
    $uaData6 = [
        'score_session' => 0,
        'score_ip' => 0,
        'access_count_session' => SESSION_OVER_THRESHOLD,// 閾値 60
        'access_count_ip' => 0, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
runTestCase('Case 6: SESSION OVER THRESHOLD (61)', 
    $contents6,
    $uaData6,
    $pdo);


// -------------------------------------------------------
// Case 7: IP UNDER THRESHOLD
//  (599)
//  ($accessCount >= 600) ===  false であることを確認します。
//   
// -------------------------------------------------------
    $contents7 = [
    'session_id'       => 'def456',
    'ip_address'       => '10.0.0.1',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 0,
    'is_ua_mismatch'   => 0,
    'recaptcha_solved' => 0,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ];
    $uaData7 = [
        'score_session' => 0,
        'score_ip' => 0,
        'access_count_session' => 0,// 閾値 60
        'access_count_ip' => IP_UNDER_THRESHOLD, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
runTestCase('Case 7: IP UNDER THRESHOLD (599)', 
    $contents7,
    $uaData7,
    $pdo);
// -------------------------------------------------------
// Case 8: IP THRESHOLD
//  (600)
//  ($accessCount >= 600) ===  true であることを確認します。
//   
// -------------------------------------------------------
    $contents8 = [
    'session_id'       => 'def456',
    'ip_address'       => '10.0.0.1',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 0,
    'is_ua_mismatch'   => 0,
    'recaptcha_solved' => 0,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ];
    $uaData8 = [
        'score_session' => 0,
        'score_ip' => 0,
        'access_count_session' => 0,// 閾値 60
        'access_count_ip' => IP_THRESHOLD, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
runTestCase('Case 8: IP THRESHOLD (600)', 
    $contents8,
    $uaData8,
    $pdo);


// -------------------------------------------------------
// Case 9: IP OVER THRESHOLD
//  (601)
//  ($accessCount >= 600) ===  true であることを確認します。
//   
// -------------------------------------------------------
    $contents9 = [
    'session_id'       => 'def456',
    'ip_address'       => '10.0.0.1',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 0,
    'is_ua_mismatch'   => 0,
    'recaptcha_solved' => 0,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ];
    $uaData9 = [
        'score_session' => 0,
        'score_ip' => 0,
        'access_count_session' => 0,// 閾値 60
        'access_count_ip' => IP_OVER_THRESHOLD, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
runTestCase('Case 9: IP OVER THRESHOLD (601)', 
    $contents9,
    $uaData9,
    $pdo);

echo "Result: {$totalPass} passed, {$totalFail} failed.<br><br>";
echo "Now running error test case...<br><br>";

// -------------------------------------------------------
// Case error: 異常系（session_id が 129 文字の長い文字列）
//   VARCHAR(128) の上限を超えた場合にエラーになるか確認します。
//  
//  mysql の設定が 【STRICT_TRANS_TABLES】 です。
//  VARCHAR(128)は128文字まで です。
//  129 文字以上の session_id を挿入しようとすると、
//  PDOException がスローされるはずです。
//  これがラップされて、再スローされ、
//  その結果、
//  RuntimeException が スローされるはずです。
// -------------------------------------------------------
    $contents_error = [
    'session_id'      => str_repeat('s', 129),
    'ip_address'      => '10.0.0.1',
    'simple_ua'       => 'Chrome/91',
    'is_no_ua'        => 0,
    'is_ua_mismatch'  => 0,
    'recaptcha_solved' => 0,
    'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ];
    $uaDataError = [
        'score_session' => 0,
        'score_ip' => 0,
        'access_count_session' => 0,// 閾値 60
        'access_count_ip' => 0, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
runTestCaseError('Case error: 異常系 (session_id が 129 文字の長い文字列)',
    $contents_error,
    $uaDataError,
    $pdo);

//  データベース接続を閉じます。
$dbManager->disconnect();

