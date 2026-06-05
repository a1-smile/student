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
is_no_ua = 1 のとき

1-1-2. anomaly event があり、

データが通常の範囲内。
is_ua_mismatch = 1 のとき

1-1-3. anomaly event があり、
データが通常の範囲内。
ACCESS_COUNT_THRESHOLD_SESSION (access_count_session が 60)

1-1-4. anomaly event があり、
データが通常の範囲内。
ACCESS_COUNT_THRESHOLD_IP (access_count_ip が 600)

1-2. anomaly event があり、
データが境界値。
(session_id が 128 文字の長い文字列)

2. 異常がないときに、
レコードが追加されないことを確認します。
使用する関数は、testCaseNoAnomaly() です。

2-1. 異常がなく、過去のアクセスがゼロのとき。
（一分間にアクセスがゼロは閾値以内です。）
DB にレコードが追加されないことを確認します。

また、過去1分間のアクセス数が
閾値の前後で適切に条件分岐されていることを確認します。
2-2. UNDER_THRESHOLD_SESSION (59)
閾値-1のときは、レコードが追加されないことを確認します。

ACCESS_COUNT_THRESHOLD_SESSION (60)は
Case 1-1-3で、
runTestCase() を使用して確認済です。

2-3. OVER_THRESHOLD_SESSION (61)
閾値+1のときは、レコードが正確に追加されることを確認します。


2-4. UNDER_THRESHOLD_IP (599) 
閾値-1のときは、レコードが追加されないことを確認します。

ACCESS_COUNT_THRESHOLD_IP (600)は
Case 1-1-4で、
runTestCase() を使用して確認済です。

2-5. OVER_THRESHOLD_IP (601)
閾値+1のときは、レコードが正確に追加されることを確認します。

error. 異常があり、記入を依頼したが、
データが適切ではないために、
例外がスローされることを確認します。
使用する関数は、runTestCaseError() です。
 session_id が 129 文字の長い文字列

以上をテストします。
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

//  RequestContent インターフェイスを使用するために、require_once します。
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
    }  // END if-else
}  // END function check

/**
 * runTestCase() 関数を定義します。
 * テーブルをTRUNCATEし、writeUaAnomalyEvents()を呼び出し、
 * SELECTで取得した値と期待値を照合します。
 * @param string $caseLabel
 * テストケースのラベルを指定します。何をテストするか。
 * @param array $contents
 * モックで、サーバーからの情報を提供するための配列です。
 * @param array $uaData
 * モックで、DBの情報を提供するための配列です。
 * @param PDO $pdo
 * PDO インスタンスを指定します。
 * @param bool $assertSessionThreshold
 * 閾値をこえたら、あるいは閾値に達したら
 * is_over_threshold_session が 1 になることを確認します。
 * 初期値は false で、必要なときに true を渡します。
 * @param bool $assertIpThreshold
 * 閾値をこえたら、あるいは閾値に達したら
 * is_over_threshold_ip が 1 になることを確認します。
 * 初期値は false で、必要なときに true を渡します。
 */

function runTestCase(
            string $caseLabel,
            array  $contents,
            array  $uaData,
            PDO    $pdo,
            bool   $assertSessionThreshold = false,
            bool   $assertIpThreshold = false
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

    // SELECTで取得する。そして期待値と照合する
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

    check('session_id_mock',         $row['session_id'],     $mock->getSessionId());
    check('session_id_contents',     $row['session_id'],     $contents['session_id']);
    check('ip_address_mock',         $row['ip_address'],     $mock->getIpAddress());
    check('ip_address_contents',     $row['ip_address'],     $contents['ip_address']);
    check('is_no_ua_mock',           (int)$row['is_no_ua'],       $mock->getIsNoUa());
    check('is_no_ua_contents',       (int)$row['is_no_ua'],       $contents['is_no_ua']);
    check('is_ua_mismatch_mock',     (int)$row['is_ua_mismatch'], $mock->getIsUaMismatch());
    check('is_ua_mismatch_contents', (int)$row['is_ua_mismatch'], $contents['is_ua_mismatch']);
    check('is_over_threshold_session', (int)$row['is_over_threshold_session'], $risk_evaluator->getIsOverThresholdSession());
    check('is_over_threshold_ip', (int)$row['is_over_threshold_ip'], $risk_evaluator->getIsOverThresholdIp());

    //  access_time が現在から5秒以内であることを確認します。
    //  まづ、DBに記録された access_time を DateTime オブジェクトに変換します。
    $access_time = new DateTime($row['access_time']);
    //  現在の時間を DateTime オブジェクトで取得します。
    $now  = new DateTime();
    //  Unix タイムスタンプの差を計算します。
    //  int なので、単純に引き算できます。
    $diff = $now->getTimestamp() - $access_time->getTimestamp();
    check('access_time', $diff >= 0 && $diff < ACCEPTABLE_TIME_DIFF_SEC, true);



    if ($assertSessionThreshold) {
        check('is_over_threshold_session(evaluator) === 1', $risk_evaluator->getIsOverThresholdSession(), 1);
        check('is_over_threshold_session (DB) === 1', (int)$row['is_over_threshold_session'], 1);
    }else {
        check('is_over_threshold_session(evaluator) === 0', $risk_evaluator->getIsOverThresholdSession(), 0);
        check('is_over_threshold_session (DB) === 0', (int)$row['is_over_threshold_session'], 0);
    }  //  END if-else

    if ($assertIpThreshold) {
        check('is_over_threshold_ip(evaluator) === 1', $risk_evaluator->getIsOverThresholdIp(), 1);
        check('is_over_threshold_ip (DB) === 1', (int)$row['is_over_threshold_ip'], 1);
    }else {
        check('is_over_threshold_ip(evaluator) === 0', $risk_evaluator->getIsOverThresholdIp(), 0);
        check('is_over_threshold_ip (DB) === 0', (int)$row['is_over_threshold_ip'], 0);
    }  //  END if-else

    echo "<br>";
}  // END function runTestCase()

function runTestCaseError(
            string $caseLabel,
            array $contents,
            array $uaData,
            PDO $pdo
    ): void {
    echo "<b>{$caseLabel}</b><br>";

    global $totalPass, $totalFail;

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
        $totalFail++;
        echo " FAIL: 例外が発生しませんでした。<br><br>";
    } catch (RuntimeException $e) {
        $totalPass++;
        echo "  PASS: 例外が発生しました: " . $e->getMessage() . "<br><br>";
        return;
    } //

} // END function runTestCaseError()


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
という処理で確認します。 */

function testCaseNoAnomaly(
            string $caseLabel,
            array $contents,
            array $uaData,
            PDO $pdo
    ): void {
    echo "<b>{$caseLabel}</b><br>";

    global $totalPass, $totalFail;

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
        $totalPass++;
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
        $totalFail++;
        echo "  FAIL: Record count changed. Before: {$countBefore}, After: {$countAfter}<br><br>";
        return;
    }
    $totalPass++;
    echo
    "  PASS: Record count did not change. Before: {$countBefore}, After: {$countAfter}<br>";
    echo "<br>";
}  // END function testCaseNoAnomaly()

// -------------------------------------------------------
// Case 1-1.: anomaly event があるときに
// データが正しく記録されることを確認します。
//  is_no_ua = 1, // 異常あり
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
    'is_no_anomaly_session' => 1, // means that no anomaly events last 10min in session
    'is_no_anomaly_ip' => 1       // means that no anomaly events last 10min in IP
];
runTestCase(
    'Case 1-1: anomaly event があるときにデータが正しく記録されることを確認します。<br>is_no_ua = 1, // 異常あり',
    $contents,
    $uaData,
    $pdo);

// -------------------------------------------------------
// Case 1-1-2: anomaly event があるときに（is_ua_mismatch = 1）
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
    'is_no_anomaly_session' => 1, // means that no anomaly events last 10min in session
    'is_no_anomaly_ip' => 1       // means that no anomaly events last 10min in IP
];
runTestCase('Case 1-1-2: anomaly event があるときに（is_ua_mismatch = 1）データが正しく記録されることを確認し正しく記録されることを確認します。',
    $contents2,
    $uaData2,
    $pdo);

// -------------------------------------------------------
// Case 1-1-3: anomaly event があるときに（ACCESS_COUNT_THRESHOLD_SESSION）
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
    'is_no_anomaly_session' => 1, // means that no anomaly events last 10min in session
    'is_no_anomaly_ip' => 1       // means that no anomaly events last 10min in IP
];

runTestCase('Case 1-1-3: anomaly event があるときに（ACCESS_COUNT_THRESHOLD_SESSION）データが正しく記録されることを確認し正しく記録されることを確認します。<br>',
    $contents2a,
    $uaData2a,
    $pdo,
    CHECK_THRESHOLD_SESSION, // 閾値を超えていることを明示的に示すフラグ
    NOT_CHECK_THRESHOLD_IP   // 閾値を超えていないことを明示的に示すフラグ
    );


// -------------------------------------------------------
// Case 1-1-4: anomaly event があるときに（ACCESS_COUNT_THRESHOLD_IP）
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
    'is_no_anomaly_session' => 1, // means that no anomaly events last 10min in session
    'is_no_anomaly_ip' => 1       // means that no anomaly events last 10min in IP
];
runTestCase('Case 1-1-4: anomaly event があるときに（ACCESS_COUNT_THRESHOLD_IP）データが正しく記録されることを確認し正しく記録されることを確認します。<br>',
    $contents2b,
    $uaData2b,
    $pdo,
    NOT_CHECK_THRESHOLD_SESSION, // 閾値を超えていないことを明示的に示すフラグ
    CHECK_THRESHOLD_IP   // 閾値を超えていることを明示的に示すフラグ
    );

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
        'is_no_anomaly_session' => 1, // means that no anomaly events last 10min in session
        'is_no_anomaly_ip' => 1       // means that no anomaly events last 10min in IP
    ];
runTestCase('Case 1-2: 境界値 (session_id が 128 文字の長い文字列)', 
    $contents3,
    $uaData3,
    $pdo,
    CHECK_THRESHOLD_SESSION, // 閾値を超えていることを明示的に示すフラグ
    NOT_CHECK_THRESHOLD_IP   // 閾値を超えていないことを明示的に示すフラグ
    );


echo '異常がないときに、レコードが追加されないことを確認します。<br>
また、異常があるときはレコードが追加されることを確認します。<br>
そして、境界値で適切に条件分岐されていることを確認します。<br><br>';
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
        'is_no_anomaly_session' => 1, // means that no anomaly events last 10min in session
        'is_no_anomaly_ip' => 1       // means that no anomaly events last 10min in IP
    ];
testCaseNoAnomaly('Case 2-1: 異常がないときに、レコードが追加されないことを確認します。<br>
過去のアクセスがゼロのとき（ゼロは閾値以内です。）<br>
DB にレコードが追加されないことを確認します。<br>
expecting PASS: Record count did not change.
<br>', 
    $contents4a,
    $uaData4a,
    $pdo);

// -------------------------------------------------------
// Case 2-2: SESSION UNDER THRESHOLD
//  (59) 
//  閾値-1のときは、レコードが追加されないことを確認します。
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
        'is_no_anomaly_session' => 1, // means that no anomaly events last 10min in session
        'is_no_anomaly_ip' => 1       // means that no anomaly events last 10min in IP
    ];
testCaseNoAnomaly('Case 2-2: SESSION UNDER THRESHOLD (59)<br>
閾値-1のときは、レコードが追加されないことを確認します。<br>
expecting PASS: Record count did not change.
<br>', 
    $contents4,
    $uaData4,
    $pdo);


echo 'ACCESS_COUNT_THRESHOLD_SESSION (60)の場合は<br>
Case 1-1-3で、<br>
runTestCase() を使用して確認済です。<br><br>';

// -------------------------------------------------------
// Case 2-3: SESSION OVER THRESHOLD
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
        'is_no_anomaly_session' => 1, // means that no anomaly events last 10min in session
        'is_no_anomaly_ip' => 1       // means that no anomaly events last 10min in IP
    ];
runTestCase('Case 2-3: SESSION OVER THRESHOLD (61)<br>
閾値を超えたときは、レコードが正確に追加されることを確認します。<br>
expecting PASS
<br>', 
    $contents6,
    $uaData6,
    $pdo,
    CHECK_THRESHOLD_SESSION, // 閾値を超えていることを明示的に示すフラグ
    NOT_CHECK_THRESHOLD_IP   // 閾値を超えていないことを明示的に示すフラグ
);


// -------------------------------------------------------
// Case 2-4: IP UNDER THRESHOLD
//  (599)
//  閾値-1のときは、レコードが追加されないことを確認します。
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
        'access_count_ip' => UNDER_THRESHOLD_IP, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // means that no anomaly events last 10min in session
        'is_no_anomaly_ip' => 1       // means that no anomaly events last 10min in IP
    ];
testCaseNoAnomaly('Case 2-4: IP UNDER THRESHOLD (599)<br>
閾値-1のときは、レコードが追加されないことを確認します。<br>
expecting PASS: Record count did not change.
<br>', 
    $contents7,
    $uaData7,
    $pdo);

echo 'ACCESS_COUNT_THRESHOLD_IP (600)の場合は<br>
Case 1-1-4で、<br>
runTestCase() を使用して確認済です。<br><br>';
// -------------------------------------------------------
// Case 2-5: IP OVER THRESHOLD
//  (601)
//  閾値+1のときは、レコードが正確に追加されることを確認します。
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
        'is_no_anomaly_session' => 1, // means that no anomaly events last 10min in session
        'is_no_anomaly_ip' => 1       // means that no anomaly events last 10min in IP
    ];
runTestCase('Case 2-5: IP OVER THRESHOLD (601)<br>
閾値+1のときは、レコードが正確に追加されることを確認します。<br>
expecting PASS
<br>', 
    $contents9,
    $uaData9,
    $pdo,
    NOT_CHECK_THRESHOLD_SESSION, // 閾値を超えていないことを明示的に示すフラグ
    CHECK_THRESHOLD_IP   // 閾値を超えていることを明示的に示すフラグ
);

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

echo "Now running error test case...<br><br>";

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
        'is_no_anomaly_session' => 1, // means that no anomaly events last 10min in session
        'is_no_anomaly_ip' => 1       // means that no anomaly events last 10min in IP
    ];
runTestCaseError('Case error: 異常系 (session_id が 129 文字の長い文字列)<br>
expecting PASS:例外が発生しました:<br>',
    $contents_error,
    $uaDataError,
    $pdo);

    
//  複合条件のテストケース(B)：
//  is_no_ua === 1 and
//  access_count_session === OVER_THRESHOLD_SESSION のときに、
//  レコードが正確に追加されることを確認します。

    $contents9b = [
    'session_id'       => 'def456',
    'ip_address'       => '10.0.0.1',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 1,
    'is_ua_mismatch'   => 0,
    'recaptcha_solved' => 0,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ];
    $uaData9b = [
        'score_session' => 0,
        'score_ip' => 0,
        'access_count_session' => OVER_THRESHOLD_SESSION,// 閾値 60
        'access_count_ip' => 0, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // means that no anomaly events last 10min in session
        'is_no_anomaly_ip' => 1       // means that no anomaly events last 10min in IP
    ];
runTestCase('複合条件テストケース(B): is_no_ua === 1 and access_count_session === OVER_THRESHOLD_SESSION<br>
<br>
expecting PASS
<br>', 
    $contents9b,
    $uaData9b   ,
    $pdo,
    CHECK_THRESHOLD_SESSION, // 閾値を超えていることを明示的に示すフラグ
    NOT_CHECK_THRESHOLD_IP   // 閾値を超えていないことを明示的に示すフラグ
);


//  複合条件のテストケース(A)：
//  is_no_ua === 1 and is_ua_mismatch === 1 のときに、
//  レコードが正確に追加されることを確認します。

    $contents9a = [
    'session_id'       => 'def456',
    'ip_address'       => '10.0.0.1',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 1,
    'is_ua_mismatch'   => 1,
    'recaptcha_solved' => 0,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ];
    $uaData9a = [
        'score_session' => 0,
        'score_ip' => 0,
        'access_count_session' => 0,// 閾値 60
        'access_count_ip' => 0, // 閾値 600
        'is_decreased_session' => 0,
        'is_decreased_ip' => 0,
        'is_no_anomaly_session' => 1, // means that no anomaly events last 10min in session
        'is_no_anomaly_ip' => 1       // means that no anomaly events last 10min in IP
    ];
runTestCase('複合条件テストケース(A): is_no_ua === 1 and is_ua_mismatch === 1<br>
<br>
expecting PASS
<br>', 
    $contents9a,
    $uaData9a   ,
    $pdo,
    NOT_CHECK_THRESHOLD_SESSION, // 閾値を超えていないことを明示的に示すフラグ
    NOT_CHECK_THRESHOLD_IP   // 閾値を超えていないことを明示的に示すフラグ
);






//  データベース接続を閉じます。
$dbManager->disconnect();
echo "Result: {$totalPass} passed, {$totalFail} failed.<br><br>";

