<?php
/*
student/student_test/test-write-ua-anomaly-events.php
では、
student/write-ua.php
に定義されている
class WriteUaのwriteUaAnomalyEvents() メソッドをテストします。
*/
/* 1. 怪しい動きがあるときに、レコードが追加されて、
データが正しく保存されることを確認します。
使用する関数は、check() と runTestCase() です。
1-1. anomaly event があり、
データが通常の範囲内。
1-2. anomaly event があり、
データが境界値。
(session_id が 128 文字の長い文字列)

2. 異常がないときに、
レコードが追加されないことを確認します。
使用する関数は、testCaseNoAnomaly() です。

2-1. 異常がなく、過去のアクセスがゼロのとき。
（閾値以内）

また、過去1分間のアクセス数が
閾値の前後で適切に条件分岐されていることを確認します。
2-2. UNDER_THRESHOLD_SESSION (59)
2-3. ACCESS_COUNT_THRESHOLD_SESSION (60)
2-4. OVER_THRESHOLD_SESSION (61)
2-5. UNDER_THRESHOLD_IP (599) 
2-6. ACCESS_COUNT_THRESHOLD_IP (600)
2-7. OVER_THRESHOLD_IP (601)

3. 異常があり、記入を依頼したが、
データが適切ではないために、
例外がスローされることを確認します。
使用する関数は、runTestCaseError() です。
3-1. session_id が 129 文字の長い文字列

以上をテストします。
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
require_once __DIR__ . '/../exceptions.php';

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
    } catch (DbWriteException $e) {
        echo "  Error writing anomaly events: " . $e->getMessage() . "<br><br>";
         http_response_code(500); // 500 Internal Server Error を返す場合
         return;
    } catch (DbRowCountException $e) {
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
// Case 1-1.: anomaly event があるときに
// データが正しく記録されることを確認します。
// -------------------------------------------------------
$contents = [
    'session_id'      => 'abc123',
    'ip_address'      => '192.168.0.1',
    'simple_ua'       => 'Chrome/91',
    'is_no_ua'        => 1,
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
    'Case 1-1: anomaly event があるときにデータが正しく記録されることを確認します。',
    $contents,
    $uaData,
    $pdo);

// -------------------------------------------------------
// Case 1-1-2: anomaly event があるときに（ua_mismatch = 1）
//   データが正しく記録されるか確認します。
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
runTestCase('Case 1-1-2: anomaly event があるときに（ua_mismatch = 1）データが正しく記録されることを確認し正しく記録されることを確認します。',
    $contents2,
    $uaData2,
    $pdo);

// -------------------------------------------------------
// Case 1-1-3: anomaly event があるときに（over threshold session）
//   データが正しく記録されるか確認します。
// -------------------------------------------------------
$contents2a = [
    'session_id'      => 'def456',
    'ip_address'      => '192.168.0.2',
    'simple_ua'       => 'Firefox/89', // previous simple_ua
    'is_no_ua'        => 0,
    'is_ua_mismatch'  => 0,
    'recaptcha_solved' => 0,
    'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
];
$uaData2a = [
    'score_session' => 0,
    'score_ip' => 0,
    'access_count_session' => ACCESS_COUNT_THRESHOLD_SESSION,// 閾値 60
    'access_count_ip' => 0, // 閾値 600
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 1, // 異常なし
    'is_no_anomaly_ip' => 1       // 異常なし
];
runTestCase('Case 1-1-3: anomaly event があるときに（ACCESS_COUNT_THRESHOLD_SESSION）データが正しく記録されることを確認し正しく記録されることを確認します。<br>',
    $contents2a,
    $uaData2a,
    $pdo);


// -------------------------------------------------------
// Case 1-1-4: anomaly event があるときに（ua_mismatch = 1）
//   データが正しく記録されるか確認します。
// -------------------------------------------------------
$contents2b = [
    'session_id'      => 'def456',
    'ip_address'      => '192.168.0.2',
    'simple_ua'       => 'Firefox/89', // previous simple_ua
    'is_no_ua'        => 0,
    'is_ua_mismatch'  => 0,
    'recaptcha_solved' => 0,
    'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
];
$uaData2b = [
    'score_session' => 0,
    'score_ip' => 0,
    'access_count_session' => 0,// 閾値 60
    'access_count_ip' => ACCESS_COUNT_THRESHOLD_IP, // 閾値 600
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 1, // 異常なし
    'is_no_anomaly_ip' => 1       // 異常なし
];
runTestCase('Case 1-1-4: anomaly event があるときに（ACCESS_COUNT_THRESHOLD_IP）データが正しく記録されることを確認し正しく記録されることを確認します。<br>',
    $contents2b,
    $uaData2b,
    $pdo);

// -------------------------------------------------------
// Case 1-2: 異常系（session_id が 128 文字の長い文字列）
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
        'access_count_session' => ACCESS_COUNT_THRESHOLD_SESSION,// 閾値 60
        'access_count_ip' => 0, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
runTestCase('Case 1-2: 境界値 (session_id が 128 文字の長い文字列)', 
    $contents3,
    $uaData3,
    $pdo);

// -------------------------------------------------------
// Case 2-1:  異常がないときに、レコードが追加されないことを確認します
// 過去のアクセスがゼロのとき（閾値以内）
//  ($accessCount >= 60) ===  false であることを確認します。
// -------------------------------------------------------
    $contents4a = [
        'session_id'       => 'def456',
        'ip_address'       => '10.0.0.1',
        'simple_ua'        => 'Chrome/91',
        'is_no_ua'         => 0,
        'is_ua_mismatch'   => 0,
        'recaptcha_solved' => 0,
        'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ];
    $uaData4a = [
        'score_session' => 0,
        'score_ip' => 0,
        'access_count_session' => 0,// 閾値 60
        'access_count_ip' => 0, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
testCaseNoAnomaly('Case 2-1: 異常がないときに、レコードが追加されないことを確認します。<br>
過去のアクセスがゼロのとき（閾値以内）<br>
expecting PASS: Record count did not change.
<br>', 
    $contents4a,
    $uaData4a,
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
        'access_count_session' => UNDER_THRESHOLD_SESSION,// 閾値 60
        'access_count_ip' => 0, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
testCaseNoAnomaly('Case 4: SESSION UNDER THRESHOLD (59) expecting PASS: Record count did not change.
<br>', 
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
        'access_count_session' => ACCESS_COUNT_THRESHOLD_SESSION,// 閾値 60
        'access_count_ip' => 0, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
testCaseNoAnomaly('Case 5: SESSION THRESHOLD (60) expecting FAIL: Record count changed.
<br>', 
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
        'access_count_session' => OVER_THRESHOLD_SESSION,// 閾値 60
        'access_count_ip' => 0, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
testCaseNoAnomaly('Case 6: SESSION OVER THRESHOLD (61) expecting FAIL: Record count changed.
<br>', 
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
    'is_ua_mismatch'   => 1,
    'recaptcha_solved' => 0,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ];
    $uaData7 = [
        'score_session' => 0,
        'score_ip' => 0,
        'access_count_session' => 0,// 閾値 60
        'access_count_ip' => UNDER_THRESHOLD_IP, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
testCaseNoAnomaly('Case 7: IP UNDER THRESHOLD (599) expecting PASS: Record count did not change.
<br>', 
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
        'access_count_ip' => ACCESS_COUNT_THRESHOLD_IP, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
runTestCase('Case 8: IP THRESHOLD (600) expecting FAIL: Record count changed.<br>', 
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
        'access_count_ip' => OVER_THRESHOLD_IP, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // 異常なし
        'is_no_anomaly_ip' => 1       // 異常なし
    ];
runTestCase('Case 9: IP OVER THRESHOLD (601) expecting FAIL: Record count changed.<br>', 
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
    'is_no_ua'        => 1,
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

//  writeUaAnomalyEvents()のコードは以下のようになっています。
//  public function writeUaAnomalyEvents(): void {
//     $pdo = $this->pdo;
//     $sessionId = $this->sessionId;
//     $ipAddress = $this->ipAddress;
//     $isNoUa = $this->isNoUa;
//     $isUaMismatch = $this->isUaMismatch;
//     $isOverThresholdSession = $this->isOverThresholdSession;
//     $isOverThresholdIp = $this->isOverThresholdIp;
//     // UAに異常がない場合は何もせずに return
//     if (
//         $isNoUa === 0 
//             && 
//         $isUaMismatch === 0 
//             && 
//         $isOverThresholdSession === 0 
//             && 
//         $isOverThresholdIp === 0
//         ) {
//         return;
//     }

//     // ua_anomaly_events テーブルにデータを記録する処理
//     $sql = "INSERT INTO ua_anomaly_events (
//               session_id,
//               ip_address,
//               is_no_ua,
//               is_ua_mismatch,
//               is_over_threshold_session,
//               is_over_threshold_ip,
//               access_time
//             ) VALUES (
//               :session_id,
//               :ip_address,
//               :is_no_ua,
//               :is_ua_mismatch,
//               :is_over_threshold_session,
//               :is_over_threshold_ip,
//               NOW())";
//     try{
//       $stmt = $this->pdo->prepare($sql);
//       // パラメーターの型を指定してバインドする
//       $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_STR);
//       $stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
//       $stmt->bindParam(':is_no_ua', $isNoUa, PDO::PARAM_INT);
//       $stmt->bindParam(':is_ua_mismatch', $isUaMismatch, PDO::PARAM_INT);
//       $stmt->bindParam(':is_over_threshold_session', $isOverThresholdSession, PDO::PARAM_INT);
//       $stmt->bindParam(':is_over_threshold_ip', $isOverThresholdIp, PDO::PARAM_INT);
//       $stmt->execute();
//       //  INSERTが正しく行われたか確認するために、影響を受けた行数をチェックする
//       if ($stmt->rowCount() !== 1) {
//           throw new RuntimeException('writeUaAnomalyEvents: INSERT affected 0 rows.');
//       } // END IF
//     }catch(PDOException $e){
//         throw new RuntimeException('writeUaAnomalyEvents failed: ' . $e->getMessage(),
//                                   self::DEFAULT_ERROR_CODE, //  code は自分で定義する。通常定数かする。マジックナンバーは避ける。 
//                                   $e //  再スローする例外の前の例外のインスタンス。
//                                   ); 
//     } // END TRY CATCH
//   } // END FUNCTION writeUaAnomalyEvents()

/*  writeUaAnomalyEvents()
が 
UAに異常がない場合は何もせずに return
することをテストするには
１．テーブル ua_anomaly_events のレコード
数を数える。
２．writeUaAnomalyEvents() を呼び出す。
３．再度テーブル ua_anomaly_events レコード数を数える。
４．呼び出し前後でレコード数が変わらないことを確認する。
５．DbWriteException や DbRowCountException がスローされないことを確認する。
という処理で適切ですか？ */
function testCaseNoAnomaly(
            string $caseLabel,
            array $contents,
            array $uaData,
            PDO $pdo
    ): void {
    echo "<b>{$caseLabel}</b><br>";

    
    $mock = new MockRequestContent1($contents);

    $mock_ua_repository  = new MockUaRepository($uaData);
    $risk_evaluator = new UserAgentRiskEvaluator($mock, $mock_ua_repository);
    $write_ua       = new WriteUa($pdo, $mock, $mock_ua_repository, $risk_evaluator);

    //  ua_anomaly_events テーブルのレコード数を数える
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM ua_anomaly_events");
        $countBefore = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        echo "  Error counting records before: " . $e->getMessage() . "<br><br>";
        return;
    } //  END try-catch


    try {
        $write_ua->writeUaAnomalyEvents();
        echo "  PASS: writeUaAnomalyEvents() executed without exceptions.<br>";
    } catch (DbWriteException $e) {
        echo "  Error writing anomaly events: " . $e->getMessage() . "<br><br>";
         http_response_code(500); // 500 Internal Server Error を返す場合
         return;
    } catch (DbRowCountException $e) {
        echo "  Error writing anomaly events: " . $e->getMessage() . "<br><br>";
         http_response_code(500); // 500 Internal Server Error を返す場合
         return;
    } catch (Exception $e) {
        echo "  Unexpected error: " . $e->getMessage() . "<br><br>";
        return;
    }  // END try-catch

    //  ua_anomaly_events テーブルのレコード数を再度数える
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM ua_anomaly_events");
        $countAfter = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        echo "  Error counting records after: " . $e->getMessage() . "<br><br>";
        return;
    }

    // レコード数が変わらないことを確認
    if ($countBefore !== $countAfter) {
        echo "  FAIL: Record count changed. Before: {$countBefore}, After: {$countAfter}<br><br>";
        return;
    }
    echo
    "  PASS: Record count did not change. Before: {$countBefore}, After: {$countAfter}<br>";
    echo "<br>";
}
//  Case 10: UAに異常がない場合は何もせずに return することを確認します。
$contents10 = [
    'session_id'       => 'abc123',
    'ip_address'       => '192.168.0.1',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 0,
    'is_ua_mismatch'   => 0,
    'is_over_threshold_session' => 0,
    'is_over_threshold_ip' => 0,
];

$uaData10 = [
    'score_session' => 0,
    'score_ip' => 0,
    'access_count_session' => 0,// 閾値 60
    'access_count_ip' => 0, // 閾値 600
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 1, // 異常なし
    'is_no_anomaly_ip' => 1       // 異常なし
];
testCaseNoAnomaly('Case 10: UAに異常がない場合は何もせずに return することを確認します。',
    $contents10,
    $uaData10,
    $pdo);

    // is_no_ua = 1 の場合も同様にテストします。
    // レコードか書き込まれてレコード数が変化することを確認します。
$contents11 = [
    'session_id'       => 'abc123',
    'ip_address'       => '192.168.0.1',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 1,
    'is_ua_mismatch'   => 0,
    'is_over_threshold_session' => 0,
    'is_over_threshold_ip' => 0,
];

$uaData11 = [
    'score_session' => 0,
    'score_ip' => 0,
    'access_count_session' => 0,// 閾値 60
    'access_count_ip' => 0, // 閾値 600
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 1, // 異常なし
    'is_no_anomaly_ip' => 1       // 異常なし
];
testCaseNoAnomaly('Case 11: is_no_ua = 1 の場合FALSEであることを確認します。',
    $contents11,
    $uaData11,
    $pdo);

    // is_ua_mismatch = 1 の場合も同様にテストします。
    // レコードか書き込まれてレコード数が変化することを確認します。
$contents12 = [
    'session_id'       => 'abc123',
    'ip_address'       => '192.168.0.1',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 0,
    'is_ua_mismatch'   => 1,
    'is_over_threshold_session' => 0,
    'is_over_threshold_ip' => 0,
];

$uaData12 = [
    'score_session' => 0,
    'score_ip' => 0,
    'access_count_session' => 0,// 閾値 60
    'access_count_ip' => 0, // 閾値 600
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 1, // 異常なし
    'is_no_anomaly_ip' => 1       // 異常なし
];
testCaseNoAnomaly('Case 12: is_ua_mismatch = 1 の場合FALSEであることを確認します。',
    $contents12,
    $uaData12,
    $pdo);


//  データベース接続を閉じます。
$dbManager->disconnect();
