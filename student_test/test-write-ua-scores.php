<?php
/*
student/student_test/test-write-ua-scores.php
では、
student/write-ua.php
に定義されている
class WriteUaのwriteUaScores() メソッドをテストします。
*/

/* テストケース
session insert / ip insert
session update / ip update
session insert / ip update
session update / ip insert

session insert / ip update but ip same value
session update / ip insert but session same value

subject_key 128文字の境界値テスト
subject_key 129文字の境界値テスト
*/




//  タイムゾーンを明示的に設定します。
//  sql と php で時間がずれるのを防ぐためです。
date_default_timezone_set('Asia/Tokyo');
//  DBへのアクセス時刻と現在の時刻の差の上限を定数として定義します。
//  許容される時間の差を秒単位で定義します。
//  環境や状況によっては、
//  アクセス時間と現在の時間に
//  数秒の差が生じることがあるので、
//  10~30sec くらいの値を設定してください。
const ACCEPTABLE_TIME_DIFF_SEC = 10;
//  $accessCount の
//  閾値を定数として定義します。
const UNDER_THRESHOLD_SESSION = 59;
const ACCESS_COUNT_THRESHOLD_SESSION = 60;
const OVER_THRESHOLD_SESSION = 61;

const UNDER_THRESHOLD_IP = 599;
const ACCESS_COUNT_THRESHOLD_IP = 600;
const OVER_THRESHOLD_IP = 601;

const CHECK_THRESHOLD_SESSION = true; // 閾値を超えていることを明示的に示すフラグ
const CHECK_THRESHOLD_IP = true; // 閾値を超えていることを明示的に示すフラグ

const NOT_CHECK_THRESHOLD_SESSION = false; // 閾値を超えていないことを明示的に示すフラグ
const NOT_CHECK_THRESHOLD_IP = false; // 閾値を超えていないことを明示的に示すフラグ

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

//  RequestContent インターフェイスを、require_once します。
require_once __DIR__ . '/../interface_request_content.php';
//  RequestContentImplementation クラスの
//  静的メソッド を使用するために、require_once します。
//  その他、のテストに必要なファイルも require_once します。
require_once __DIR__ . '/../request_content_implementation.php';
require_once __DIR__ . '/../mock_request_content.php';
require_once __DIR__ . '/../interface_ua_repository.php';
require_once __DIR__ . '/../mock_ua_repository.php';
require_once __DIR__ . '/../ua_repository_implementation.php';
require_once __DIR__ . '/../risk_evaluation_result.php';
require_once __DIR__ . '/../user_agent_risk_evaluator.php';
require_once __DIR__ . '/../write-ua.php';
require_once __DIR__ . '/../exceptions.php';
require_once __DIR__ . '/function-check.php';

//  table ua_scores を TRUNCATE する関数を定義します。
function truncateUaScoresTable(PDO $pdo): void {
    try {
        $pdo->exec("TRUNCATE TABLE ua_scores");
        echo "Table ua_scores truncated successfully.<br><br>";
    } catch (Exception $e) {
        echo "Error truncating table ua_scores: " . $e->getMessage() . "<br><br>";
        exit(1);
    }
}
/**
 * runTestWriteUaScores() 関数を定義します。
 * テーブルをTRUNCATEし、writeUaScores()を呼び出し、
 * SELECTで取得した値と期待値を照合します。
 * @param string $caseLabel
 * テストケースのラベルを指定します。何をテストするか。
 * 
 * @param array $contents
 * モックで、サーバーからの情報を提供するための配列です。
 * [
 *   'session_id' => 'abc123',
 *   'ip_address' => '192.168.0.1',
 *   'simple_ua' => 'Chrome/91',
 *   'is_no_ua' => 1,
 *   'is_ua_mismatch' => 0,
 *   'recaptcha_solved' => 0,
 * ]
 * @param array $uaData
 * モックで、DBの情報を提供するための配列です。
 * @param PDO $pdo
 * PDO インスタンスを指定します。
 */
//$contents = [
//         'session_id' => 'abc123',
//         'ip_address' => '192.168.0.1',
//         'simple_ua' => 'Mozilla/5.0',
//         'is_no_ua' => 0,
//         'is_ua_mismatch' => 0,
//         'recaptcha_solved' => 0
//     ];  


// $uaData = [
//     'score_session' => 0,
//     'score_ip' => 0,
//     'access_count_session' => 0,
//     'access_count_ip' => 0,
//     'is_decreased_session' => 0,
//     'is_decreased_ip' => 0,
//     'is_no_anomaly_session' => 0,
//     'is_no_anomaly_ip' => 0
// ];


function runTestWriteUaScores(
            string $caseLabel,
            array  $contents,
            array  $uaData,
            int    $scoreSessionExpected,
            int    $scoreIpExpected,
            PDO    $pdo,
    ): void {
    echo "<b>{$caseLabel}</b><br>";

    $mock_request_content = new MockRequestContent1($contents);
    $mock_ua_repository  = new MockUaRepository($uaData);
    $risk_evaluator = new UserAgentRiskEvaluator($mock_request_content, $mock_ua_repository);
    $write_ua       = new WriteUa($pdo, $mock_request_content, $mock_ua_repository, $risk_evaluator);
    try {
        $write_ua->writeUaScores();
    } catch (DbWriteException $e) {
        echo "  Error writing anomaly events: " . $e->getMessage() . "<br><br>";
         http_response_code(500); // 500 Internal Server Error を返す場合
         return;
    } catch (Exception $e) {
        echo "  Unexpected error: " . $e->getMessage() . "<br><br>";
        return;
    }  // END try-catch
    
// SELECTで session に紐づいた record を取得する。そして期待値と照合する
    $session_id = $mock_request_content->getSessionId();
    try {
        $stmt = $pdo->prepare("SELECT * FROM ua_scores WHERE subject_key = :session_id");
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        $stmt->execute();
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo "  Error querying table: " . $e->getMessage() . "<br><br>";
        return;
    }
    if ($row === false) {
        echo "  FAIL: No log entry found in ua_scores.<br><br>";
        return;
    }

    check('subject_key_session_mock', $row['subject_key'], $session_id);
    check('subject_key_session_contents', $row['subject_key'], $contents['session_id']);
    check('scoreSession', $row['score'], $scoreSessionExpected);
    check('type', $row['subject_type'], 'session');

        //  access_time が現在から5秒以内であることを確認します。
    //  まず、DBに記録された access_time を DateTime オブジェクトに変換します。
    $access_time = new DateTime($row['updated_at']);
    //  現在の時間を DateTime オブジェクトで取得します。
    $now  = new DateTime();
    //  Unix タイムスタンプの差を計算します。
    //  int なので、単純に引き算できます。
    $diff = $now->getTimestamp() - $access_time->getTimestamp();
    check('access_time_session', $diff >= 0 && $diff < ACCEPTABLE_TIME_DIFF_SEC, true);

    

 // SELECTで ip address に紐づいた record を取得する。そして期待値と照合する
    $ip_address = $mock_request_content->getIpAddress();
    try {
        $stmt = $pdo->prepare("SELECT * FROM ua_scores WHERE subject_key = :ip_address");
        $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
        $stmt->execute();
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo "  Error querying table: " . $e->getMessage() . "<br><br>";
        return;
    }
    if ($row === false) {
        echo "  FAIL: No log entry found in ua_scores.<br><br>";
        return;
    }

    check('subject_key_ip_mock', $row['subject_key'], $ip_address);
    check('subject_key_ip_contents', $row['subject_key'], $contents['ip_address']);
    check('scoreIp', $row['score'], $scoreIpExpected);
    check('type', $row['subject_type'], 'ip');

    //  access_time が現在から5秒以内であることを確認します。
    //  まず、DBに記録された access_time を DateTime オブジェクトに変換します。
    $access_time = new DateTime($row['updated_at']);
    //  現在の時間を DateTime オブジェクトで取得します。
    $now  = new DateTime();
    //  Unix タイムスタンプの差を計算します。
    //  int なので、単純に引き算できます。
    $diff = $now->getTimestamp() - $access_time->getTimestamp();
    check('access_time_ip', $diff >= 0 && $diff < ACCEPTABLE_TIME_DIFF_SEC, true);
    
    echo "<br>";
}  // END function runTestWriteUaScores()

function getRecordCount(PDO $pdo): int {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM ua_scores");
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        echo "  Error counting records: " . $e->getMessage() . "<br><br>";
        return -1; // エラーの場合は -1 を返す
    }
}

function checkRecordCount(
    string $caseLabel,
    PDO $pdo,
    int $expectedCount
): void {
    echo "<b>{$caseLabel}</b><br>";

    global $totalPass, $totalFail;

    $actualCount = getRecordCount($pdo);
    if ($actualCount === -1) {
        echo "  FAIL: Could not retrieve record count.<br><br>";
        return;
    }

    if ($actualCount === $expectedCount) {
        $totalPass++;
        echo "  PASS: Record count is as expected. Count: {$actualCount}<br><br>";
    } else {
        $totalFail++;
        echo "  FAIL: Record count is not as expected. Expected: {$expectedCount}, Actual: {$actualCount}<br><br>";
    }
} // END function checkRecordCount()

function runTestCaseError(
            string $caseLabel,
            array $contents,
            array $uaData,
            PDO $pdo
    ): void {
    echo "<b>{$caseLabel}</b><br>";

    global $totalPass, $totalFail;

    // table ua_scores をクリア
    try {
        $pdo->exec("TRUNCATE TABLE ua_scores");
    } catch (Exception $e) {
        echo "  Error truncating table: " . $e->getMessage() . "<br><br>";
        return;
}

    $mock = new MockRequestContent1($contents);

    $mock_ua_repository  = new MockUaRepository($uaData);
    $risk_evaluator = new UserAgentRiskEvaluator($mock, $mock_ua_repository);
    $write_ua       = new WriteUa($pdo, $mock, $mock_ua_repository, $risk_evaluator);

    try {
        $write_ua->writeUaScores();
        $totalFail++;
        echo " FAIL: 例外が発生しませんでした。<br><br>";
    } catch (RuntimeException $e) {
        $totalPass++;
        echo "  PASS: 例外が発生しました: " . $e->getMessage() . "<br><br>";
        return;
    } // END try-catch

} // END function runTestCaseError()



// -------------------------------------------------------
// session insert / ip insert
// $is_no_ua === 1
//  
// -------------------------------------------------------
//  table ua_scores を TRUNCATE します。
echo "<b>Truncating table ua_scores...</b><br>";
truncateUaScoresTable($pdo);

//  runTestWriteUaScores() の引数を定義します。
$caseLabel = 'Case<br>session insert / ip insert, $is_no_ua === 1';
$contents = [
    'session_id'      => 'abc123',
    'ip_address'      => '192.168.0.1',
    'simple_ua'       => 'Chrome/91',
    'is_no_ua'        => 1,  // score 1 加算
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
    'is_no_anomaly_session' => 0 , // 'is_no_ua' => 1 と設定してあり、異常があるアクセスなので、こちらの値はスコアに影響しません。
    'is_no_anomaly_ip' => 0       // 異常があるアクセスなので、こちらの値はスコアに影響しません。
];
$scoreSessionExpected = 1; // $is_no_ua === 1 なので、scoreSession は 1 になることを期待します。
$scoreIpExpected = 1; // $is_no_ua === 1 なので、scoreIp は 1 になることを期待します。
echo "<b>Running test case</b><br>";
runTestWriteUaScores(
    $caseLabel,
    $contents,
    $uaData,
    $scoreSessionExpected,
    $scoreIpExpected,
    $pdo);

//  checkRecordCount() の引数を定義します。
echo "<b>Checking record count...</b><br>";
$caseLabel = 'Case<br>session insert / ip insert, $is_no_ua === 1';
$expectedCount = 2; // session と ip の2件が挿入されることを期待します。
checkRecordCount($caseLabel, $pdo, $expectedCount);




//  データベース接続を閉じます。
$dbManager->disconnect();
echo "Result: {$totalPass} passed, {$totalFail} failed.<br><br>";


