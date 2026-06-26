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
//: to do //session insert / ip update 
//: to do //session update / ip insert 

//:to do //session insert / ip update but ip same value
//:to do //session update / ip insert but session same value
//subject_key 128文字の境界値テスト
//subject_key 129文字の境界値テスト
//access count session59, 60, 61 の境界値テスト
//access count ip 599, 600, 601 の境界値テスト

//複合条件 is_ua_mismatch === 1 
// and 
// access_count is_over_threshold === 1 の場合のテスト
*/

/* risk score を
計算する仕様を説明します。
テスト用の expected score はこの仕様の
ハードコードによって計算します。
また、
*これにより、risk score を計算する
*ロジックをテストコード内で明示的に表現できるようにもなります。 

    //  疑わしいアクセスに対しては、
    //  risk score の減算を行わない。

    //  $is_no_ua === 1（uaなし）の場合は、risk score に1点加算します。
    
    //  $is_no_ua === 0（uaあり）の場合は、
    //  遷移前と遷移後で、ua を比較する。
    //  ua がない場合は、比較できないので、$is_ua_mismatch の値は無視します。
    //  遷移前と遷移後で、ua が異なる場合は、risk score に2点加算します。
    
    //  過去1分のアクセス数が閾値を超えている場合は、
    //  risk score に3点加算します。
    
    //  ここまでで、加算が完了。

    //  ここから、減算のロジックです。

    //  疑わしいアクセスの場合は、
    // 減算のロジックを適用せず、
    // 加算後のスコアを返します。
    // 疑わしいという判定は
    //  ua なし、
    //  ua 不一致、
    //  過去1分のアクセス数が閾値を超えている、
    //  のいずれかに該当する場合です。

    //  過去30分にスコアが減点されたアクセスがある場合は、
    //  減算のロジックを適用せず、
    //  加算後のスコアで  終了します。

    //  過去10分に異常がなかった場合は、
    //  減算のロジックを適用し、1点減点します。

    //  異常がなかった場合は、次の項目の
    //  recaptcha_solved の判定を行わず、
    // ここで終了します。
    // 異常がなく、かつ recaptcha を通過、
    // というケースは、想定されないためです。

    //  recaptcha を通過した場合は、
    //  減算のロジックを適用し、
    //  subject_type に応じた減算値を減点します。
    //    session の場合は、4点減点します。
    //    ip の場合は、1点減点します。

    //  減算の条件に該当しない場合は、
    //  加算後のスコアを計算結果とします。
*/

//  過去1分のアクセス数が閾値を超えているかどうかを判定する関数を定義します。
function checkOverThreshold(int $access_count_last1min, int $threshold): int {
    return ($access_count_last1min >= $threshold) ? 1 : 0;
}  // END function checkOverThreshold()

//  table ua_scores を TRUNCATE する関数を定義します。
function truncateUaScoresTable(PDO $pdo): void {
    try {
        $pdo->exec("TRUNCATE TABLE ua_scores");
        echo "Table ua_scores truncated successfully.<br><br>";
    } catch (Exception $e) {
        echo "Error truncating table ua_scores: " . $e->getMessage() . "<br><br>";
        exit(1);
    }  // END try-catch
}  // END function truncateUaScoresTable()

/**
 * runTestWriteUaScores() 関数を定義します。
 * writeUaScores()を呼び出し、
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
 * [
 *  'score_session' => 0,
 *  'score_ip' => 0,
 *  'access_count_session' => 0,
 *  'access_count_ip' => 0,
 *  'is_decreased_session' => 0,
 *  'is_decreased_ip' => 0,
 *  'is_no_anomaly_session' => 0,
 *  'is_no_anomaly_ip' => 0,
 * ]
 * @param int $scoreSessionExpected
 * session ベースの score の期待値を指定します。
 * @param int $scoreIpExpected
 * IP ベースの score の期待値を指定します。
 * @param PDO $pdo
 * PDO インスタンスを指定します。
 * 
 * @param bool $clearDb
 * true の場合、テスト開始前に table ua_scores を TRUNCATE します。
 */

function runTestWriteUaScores(
            string $caseLabel,
            array  $contents,
            array  $uaData,
            int    $scoreSessionExpected,
            int    $scoreIpExpected,
            PDO    $pdo,
            bool   $clearDb = true
    ): void {
    echo "<b>{$caseLabel}</b><br>";

    if ($clearDb) {
        // table ua_scores をクリア
        truncateUaScoresTable($pdo);
    }  //END IF

    $mock_request_content = new MockRequestContent1($contents);
    $mock_ua_repository  = new MockUaRepository($uaData);
    $risk_evaluator = new UserAgentRiskEvaluator($mock_request_content, $mock_ua_repository);
    $write_ua       = new WriteUa($pdo, $mock_request_content, $mock_ua_repository, $risk_evaluator);
    try {
        $write_ua->writeUaScores();
    } catch (DbWriteException $e) {
        die("  Error writing anomaly events: " . $e->getMessage() . "<br><br>");
    } catch (Exception $e) {
        die("  Unexpected error: " . $e->getMessage() . "<br><br>");
    }  // END try-catch
    
// SELECTで session に紐づいた record を取得する。そして期待値と照合する
    $session_id = $mock_request_content->getSessionId();
    try {
        $stmt = $pdo->prepare("SELECT * FROM ua_scores WHERE subject_key = :session_id");
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        $stmt->execute();
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        die("  Error querying table: " . $e->getMessage() . "<br><br>");
    }
    if ($row === false) {
        $totalFail++;
        die("  FAIL: No log entry found in ua_scores.<br><br>");
    }

    $ok = check('subject_key_session_mock', $row['subject_key'], $session_id);
    $ok = check('subject_key_session_contents', $row['subject_key'], $contents['session_id']) && $ok;
    $ok = check('scoreSession', $row['score'], $scoreSessionExpected) && $ok;
    $ok = check('type', $row['subject_type'], 'session') && $ok;

        //  access_time が現在から5秒以内であることを確認します。
    //  まず、DBに記録された access_time を DateTime オブジェクトに変換します。
    $access_time = new DateTime($row['updated_at']);
    //  現在の時間を DateTime オブジェクトで取得します。
    $now  = new DateTime();
    //  Unix タイムスタンプの差を計算します。
    //  int なので、単純に引き算できます。
    $diff = $now->getTimestamp() - $access_time->getTimestamp();
    $ok = check('access_time_session', $diff >= 0 && $diff < ACCEPTABLE_TIME_DIFF_SEC, true) && $ok;

    //  session のチェックが失敗した場合は、ip のチェックをスキップします。
    //  実行しても test の信頼性が低いためです。
    if (!$ok) {
        die("  session checks failed. skipping ip checks.<br><br>");
    }

 // SELECTで ip address に紐づいた record を取得する。そして期待値と照合する
    $ip_address = $mock_request_content->getIpAddress();
    try {
        $stmt = $pdo->prepare("SELECT * FROM ua_scores WHERE subject_key = :ip_address");
        $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
        $stmt->execute();
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        die("  Error querying table: " . $e->getMessage() . "<br><br>");
    }
    if ($row === false) {
        die("  FAIL: No log entry found in ua_scores.<br><br>");
    }

    $ok = check('subject_key_ip_mock', $row['subject_key'], $ip_address);
    $ok = check('subject_key_ip_contents', $row['subject_key'], $contents['ip_address']) && $ok;
    $ok = check('scoreIp', $row['score'], $scoreIpExpected) && $ok;
    $ok = check('type', $row['subject_type'], 'ip') && $ok;

    //  access_time が現在から5秒以内であることを確認します。
    //  まず、DBに記録された access_time を DateTime オブジェクトに変換します。
    $access_time = new DateTime($row['updated_at']);
    //  現在の時間を DateTime オブジェクトで取得します。
    $now  = new DateTime();
    //  Unix タイムスタンプの差を計算します。
    //  int なので、単純に引き算できます。
    $diff = $now->getTimestamp() - $access_time->getTimestamp();
    $ok = check('access_time_ip', $diff >= 0 && $diff < ACCEPTABLE_TIME_DIFF_SEC, true) && $ok;
    
    if ($ok) {
        echo "  All checks passed.<br><br>";
    } else {
        //  test 失敗のため stop します。
        die("Ip test failed. Stopping further tests.<br><br>");
    }
    echo "<br>";
}  // END function runTestWriteUaScores()

//  table ua_scores のレコード件数を取得する関数を定義します。
function getRecordCount(PDO $pdo): int {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM ua_scores");
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        echo "  Error counting records: " . $e->getMessage() . "<br><br>";
        return -1; // エラーの場合は -1 を返す
    }  // END try-catch
}  // END function getRecordCount()

//  record count が期待値と一致するかどうかをチェックする関数を定義します。
function checkRecordCount(
    string $caseLabel,
    PDO $pdo,
    int $expectedCount
): void {
    echo "<b>{$caseLabel}</b><br>";

    global $totalPass, $totalFail;

    $actualCount = getRecordCount($pdo);
    if ($actualCount === -1) {
        $totalFail++;
        die("  FAIL: Could not retrieve record count.<br><br>");
    }

    if ($actualCount === $expectedCount) {
        $totalPass++;
        echo "  PASS: Record count is as expected. Count: {$actualCount}<br><br>";
    } else {
        $totalFail++;
        die("  FAIL: Record count is not as expected. Expected: {$expectedCount}, Actual: {$actualCount}<br><br>");
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
        die("  Error truncating table: " . $e->getMessage() . "<br><br>");
}

    $mock = new MockRequestContent1($contents);

    $mock_ua_repository  = new MockUaRepository($uaData);
    $risk_evaluator = new UserAgentRiskEvaluator($mock, $mock_ua_repository);
    $write_ua       = new WriteUa($pdo, $mock, $mock_ua_repository, $risk_evaluator);

    try {
        $write_ua->writeUaScores();
        $totalFail++;
        die("  FAIL: 例外が発生しませんでした。デバッグが必要です。<br><br>");
    } catch (RuntimeException $e) {
        $totalPass++;
        echo "  PASS: 例外が発生しました: " . $e->getMessage() . "<br><br>";
        return;
    } // END try-catch
} // END function runTestCaseError()

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

//  subject_type が session の場合、recaptcha 通過時に減算する値
//  hardcode で、計算するためコメントアウトします。
//const DECREASE_SCORE_FOR_SESSION = 4; // subject_type が session の場合、recaptcha 通過時に減算する値

//  subject_type が ip の場合、recaptcha 通過時に減算する値
//  hardcode で、計算するためコメントアウトします。
//const DECREASE_SCORE_FOR_IP = 1; // subject_type が ip の場合、recaptcha 通過時に減算する値    

const CLEAR_DB = true; // テスト開始前にDBをクリアするかどうか。true にすると、テスト開始前に table ua_scores を TRUNCATE します。
const NOT_CLEAR_DB = false; // テスト開始前にDBをクリアしない場合。テストケースによっては、前のテストケースのデータが残っていることを前提とするものもあるため、こちらの定数も定義しておきます。

//to do:const  MAX_SUBJECT_KEY_LENGTH = 128; // subject_key の最大長を定義します。128文字を超える場合は、例外が発生することを想定しています。
const JUST_THRESHOLD_SESSION = 60; // access_count_session の閾値を定義します。カウントする時間は1分間です。
const JUST_THRESHOLD_IP = 600; // access_count_ip の閾値を定義します。カウントする時間は1分間です。

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

//  RequestContentImplementation クラスを、require_once します。
//  mock_request_content.php 内で、simple ua を求めるときに 
//  RequestContentImplementation の静的メソッドを使用するためです。
require_once __DIR__ . '/../request_content_implementation.php';

//  その他のテストに必要なファイルも require_once します。
require_once __DIR__ . '/../mock_request_content.php';
require_once __DIR__ . '/../interface_ua_repository.php';
require_once __DIR__ . '/../mock_ua_repository.php';
require_once __DIR__ . '/../risk_evaluation_result.php';
require_once __DIR__ . '/../user_agent_risk_evaluator.php';
require_once __DIR__ . '/../write-ua.php';
require_once __DIR__ . '/../exceptions.php';
require_once __DIR__ . '/function-check.php';

// -------------------------------------------------------
// session insert / ip insert
// $is_no_ua === 1
//  
// -------------------------------------------------------

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

$is_over_threshold_session =
    checkOverThreshold(
        $uaData['access_count_session'],
        JUST_THRESHOLD_SESSION
        );
$is_over_threshold_ip=
    checkOverThreshold(
        $uaData['access_count_ip'], 
        JUST_THRESHOLD_IP
        );

$scoreSessionExpected = 1;
/* 
table をTRUNCATE したので、
previous score 0

is no ua 1 => +1

ua がないので比較できない。
ゆえに、
不一致時の加算はなし。

過去1分のアクセス数が閾値を超えていないので、加算なし。

疑わしいアクセスなので、
減算のロジックは適用されず、
加算後のスコアが計算結果となります。
結果は、
score は 1 です。
    */
    
//  同様に、ip の score も計算しますと、
$scoreIpExpected = 1;

runTestWriteUaScores(
    $caseLabel,
    $contents,
    $uaData,
    $scoreSessionExpected,
    $scoreIpExpected,
    $pdo,
    CLEAR_DB);

//  checkRecordCount() の引数を定義します。
$caseLabel = 'Check record count after session insert / ip insert';
$expectedCount = 2; // session と ip の2件が挿入されることを期待します。
checkRecordCount($caseLabel, $pdo, $expectedCount);

// -------------------------------------------------------
// session update / ip update
// $is_no_ua === 1
//
// step 1: CLEAR_DB で insert を行い、score = 1 を書き込む。
// step 2: 同じ session_id / ip_address で NOT_CLEAR_DB にし update を行う。
//         mock の score_session/score_ip に step 1 で書き込んだ値 1 をセットする。
//         is_no_ua=1 → +1 加算 → score = 2 になることを確認する。
// step 3: record count が 2（INSERT でなく UPDATE）であることを確認する。
// -------------------------------------------------------

// step 1: insert (CLEAR_DB)
$caseLabel = 'Case<br>session update / ip update, step 1: insert';

$contents = [
    'session_id'      => 'update_session_01',
    'ip_address'      => '10.0.0.1',
    'simple_ua'       => 'Chrome/91',
    'is_no_ua'        => 1,  // score +1
    'is_ua_mismatch'  => 0,
    'recaptcha_solved' => 0,
    'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
];

$uaData = [
    'score_session'        => 0,  // DB に記録なし → 0
    'score_ip'             => 0,  // DB に記録なし → 0
    'access_count_session' => 0,  // 閾値 60
    'access_count_ip'      => 0,  // 閾値 600
    'is_decreased_session' => 0,
    'is_decreased_ip'      => 0,
    'is_no_anomaly_session' => 0,  // is_no_ua=1 → isSuspiciousAccess=1 → decreaseScore() 早期リターン → 減算スキップ
    'is_no_anomaly_ip'      => 0,  // 同上
];

// 計算: previous(0) + is_no_ua(+1) = 1
// 減算: is_no_ua===1 → isSuspiciousAccess===1 → decreaseScore() 早期リターン
$scoreSessionExpected = 1;
$scoreIpExpected      = 1;

runTestWriteUaScores(
    $caseLabel,
    $contents,
    $uaData,
    $scoreSessionExpected,
    $scoreIpExpected,
    $pdo,
    CLEAR_DB);

// step 2: update (NOT_CLEAR_DB, 同じ session_id / ip_address)
// $contents は step 1 と同じ変数をそのまま使用します（同一 session_id / ip_address）。
$caseLabel = 'Case<br>session update / ip update, step 2: update';

// mock の score_session / score_ip に
// step 1 で DB に書き込んだ値 1 をセットします。
// これにより「DB に score 1 が残っている状態から再アクセスした」状況を模倣します。
$uaData = [
    'score_session'        => 1,  // step 1 で DB に書かれたスコア
    'score_ip'             => 1,  // step 1 で DB に書かれたスコア
    'access_count_session' => 0,  // 閾値 60
    'access_count_ip'      => 0,  // 閾値 600
    'is_decreased_session' => 0,
    'is_decreased_ip'      => 0,
    'is_no_anomaly_session' => 0,  // is_no_ua=1 → isSuspiciousAccess=1 → decreaseScore() 早期リターン → 減算スキップ
    'is_no_anomaly_ip'      => 0,  // 同上
];

// 計算: previous(1) + is_no_ua(+1) = 2
// 減算: is_no_ua===1 → isSuspiciousAccess===1 → decreaseScore() 早期リターン
$scoreSessionExpected = 2;
$scoreIpExpected      = 2;

runTestWriteUaScores(
    $caseLabel,
    $contents,        // step 1 と同じ session_id / ip_address
    $uaData,
    $scoreSessionExpected,
    $scoreIpExpected,
    $pdo,
    NOT_CLEAR_DB);    // クリアしない → UPDATE になることを確認

// step 3: record count が 2（INSERT でなく UPDATE）であることを確認する。
$caseLabel = 'Record count = 2: UPDATE であり INSERT でないことを確認';
$expectedCount = 2;
checkRecordCount($caseLabel, $pdo, $expectedCount);

/* session ベースの過去1分間のアクセス数が閾値-1の場合のテスト */
$caseLabel = 'Case<br>session ベースの過去1分間のアクセス数が閾値-1の場合のテスト';
$contents = [
    'session_id'      => 'session_threshold_minus_1',
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
    'access_count_session' => JUST_THRESHOLD_SESSION - 1, // 閾値-1
    'access_count_ip' => 0, // 閾値 600
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 0 , 
    'is_no_anomaly_ip' => 0 
];
$is_over_threshold_session = 0; // 閾値-1なので、閾値を超えていない
$is_over_threshold_ip = 0; // 閾値 600 なので、閾値を超えていない

$scoreSessionExpected = 0; // previous score 0、加算なし、減算なし → score = 0
$scoreIpExpected = 0; // previous score 0、加算なし、減算なし → score = 0

runTestWriteUaScores(
    $caseLabel,
    $contents,
    $uaData,
    $scoreSessionExpected,
    $scoreIpExpected,
    $pdo,
    CLEAR_DB);

//  checkRecordCount() の引数を定義します。
$caseLabel = 'Check record count after session threshold-1 test';
$expectedCount = 2; // session と ip の2件が挿入されることを期待します。
checkRecordCount($caseLabel, $pdo, $expectedCount);

/* session ベースの過去1分間のアクセス数が閾値の場合のテスト */
$caseLabel = 'Case<br>session ベースの過去1分間のアクセス数が閾値の場合のテスト';
$contents = [
    'session_id'       => 'session_threshold',
    'ip_address'       => '192.168.0.1',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 0,
    'is_ua_mismatch'   => 0,
    'recaptcha_solved' => 0,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
];

$uaData = [
    'score_session' => 0,
    'score_ip' => 0,
    'access_count_session' => JUST_THRESHOLD_SESSION, // 閾値
    'access_count_ip' => 0, // 閾値 600
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 0 , 
    'is_no_anomaly_ip' => 0 
];

$is_over_threshold_session = 1; // 閾値なので、閾値を超えている
$is_over_threshold_ip = 0; // 閾値 600 なので、閾値を超えていない

$scoreSessionExpected = 3; // previous score 0、加算 +3、減算なし → score = 3
$scoreIpExpected = 0; // previous score 0、加算なし、減算なし → score = 0

runTestWriteUaScores(
    $caseLabel,
    $contents,
    $uaData,
    $scoreSessionExpected,
    $scoreIpExpected,
    $pdo,
    CLEAR_DB);

//  checkRecordCount() の引数を定義します。
$caseLabel = 'Check record count after session threshold test';
$expectedCount = 2; // session と ip の2件が挿入されることを期待します。
checkRecordCount($caseLabel, $pdo, $expectedCount);

/* session ベースの過去1分間のアクセス数が閾値+1の場合のテスト */
$caseLabel = 'Case<br>session ベースの過去1分間のアクセス数が閾値+1の場合のテスト';
$contents = [
    'session_id'       => 'session_threshold_plus_1',
    'ip_address'       => '192.168.0.1',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 0,
    'is_ua_mismatch'   => 0,
    'recaptcha_solved' => 0,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
];

$uaData = [
    'score_session' => 0,
    'score_ip' => 0,
    'access_count_session' => JUST_THRESHOLD_SESSION + 1, // 閾値+1
    'access_count_ip' => 0, // 閾値 600
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 0 , 
    'is_no_anomaly_ip' => 0 
];

$is_over_threshold_session = 1; // 閾値+1なので、閾値を超えている
$is_over_threshold_ip = 0; // 閾値 600 なので、閾値を超えていない

$scoreSessionExpected = 3; // previous score 0、加算 +3、減算なし → score = 3
$scoreIpExpected = 0; // previous score 0、加算なし、減算なし → score = 0

runTestWriteUaScores(
    $caseLabel,
    $contents,
    $uaData,
    $scoreSessionExpected,
    $scoreIpExpected,
    $pdo,
    CLEAR_DB);

//  checkRecordCount() の引数を定義します。
$caseLabel = 'Check record count after session threshold+1 test';
$expectedCount = 2; // session と ip の2件が挿入されることを期待します。
checkRecordCount($caseLabel, $pdo, $expectedCount);

/* IP ベースの過去1分間のアクセス数が閾値-1の場合のテスト */
$caseLabel = 'Case<br>IP ベースの過去1分間のアクセス数が閾値-1の場合のテスト (599)';
$contents = [
    'session_id'       => 'ip_threshold_minus_1',
    'ip_address'       => '10.0.1.1',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 0,
    'is_ua_mismatch'   => 0,
    'recaptcha_solved' => 0,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
];

$uaData = [
    'score_session'        => 0,
    'score_ip'             => 0,
    'access_count_session' => 0,                      // 閾値 60
    'access_count_ip'      => JUST_THRESHOLD_IP - 1,  // 閾値-1 (599)
    'is_decreased_session' => 0,
    'is_decreased_ip'      => 0,
    'is_no_anomaly_session' => 0,
    'is_no_anomaly_ip'      => 0,
];

$is_over_threshold_session = 0; // 閾値を超えていない
$is_over_threshold_ip      = 0; // 閾値-1なので、閾値を超えていない

/* score calculation
previous score 0

is_no_ua 0 => 加算なし

ua があるので比較できるが、
is_ua_mismatch 0 => 加算なし

過去1分の ip アクセス数が閾値を超えていないので、
加算なし

疑わしいアクセスではない

過去30分にスコアが減点されたアクセスがない、

過去10分に異常がなかったという
記録はないので、
減点しません。

recaptcha_solved 0 なので、
減算しません。
結果は、
session score は 0、ip score は 0 です。
*/
$scoreSessionExpected = 0;
$scoreIpExpected      = 0;

runTestWriteUaScores(
    $caseLabel,
    $contents,
    $uaData,
    $scoreSessionExpected,
    $scoreIpExpected,
    $pdo,
    CLEAR_DB);

$caseLabel = 'Check record count after IP threshold-1 test';
$expectedCount = 2;
checkRecordCount($caseLabel, $pdo, $expectedCount);

/* IP ベースの過去1分間のアクセス数が閾値の場合のテスト */
$caseLabel = 'Case<br>IP ベースの過去1分間のアクセス数が閾値の場合のテスト (600)';
$contents = [
    'session_id'       => 'ip_threshold',
    'ip_address'       => '10.0.1.2',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 0,
    'is_ua_mismatch'   => 0,
    'recaptcha_solved' => 0,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
];

$uaData = [
    'score_session'        => 0,
    'score_ip'             => 0,
    'access_count_session' => 0,                  // 閾値 60
    'access_count_ip'      => JUST_THRESHOLD_IP,  // 閾値 (600)
    'is_decreased_session' => 0,
    'is_decreased_ip'      => 0,
    'is_no_anomaly_session' => 0,
    'is_no_anomaly_ip'      => 0,
];

$is_over_threshold_session = 0; // 閾値を超えていない
$is_over_threshold_ip      = 1; // 閾値なので、閾値を超えている

/* score calculation
previous score 0

is_no_ua 0 => 加算なし

ua があるので比較できるが、
is_ua_mismatch 0 => 加算なし

過去1分の ip アクセス数が閾値を超えているので、
ip score に +3

ip が疑わしいアクセス (is_over_threshold_ip === 1) なので、
ip の減算ロジックは適用されず、加算後のスコアが計算結果となります。

session は疑わしいアクセスではない (is_no_ua=0, is_ua_mismatch=0, is_over_threshold_session=0)
過去30分にスコアが減点されたアクセスがない、
過去10分に異常がなかったという記録はないので、減点しません。
recaptcha_solved 0 なので、減算しません。

結果は、
session score は 0、ip score は 0 + 3 = 3 です。
*/
$scoreSessionExpected = 0;
$scoreIpExpected      = 3;

runTestWriteUaScores(
    $caseLabel,
    $contents,
    $uaData,
    $scoreSessionExpected,
    $scoreIpExpected,
    $pdo,
    CLEAR_DB);

$caseLabel = 'Check record count after IP threshold test';
$expectedCount = 2;
checkRecordCount($caseLabel, $pdo, $expectedCount);

/* IP ベースの過去1分間のアクセス数が閾値+1の場合のテスト */
$caseLabel = 'Case<br>IP ベースの過去1分間のアクセス数が閾値+1の場合のテスト (601)';
$contents = [
    'session_id'       => 'ip_threshold_plus_1',
    'ip_address'       => '10.0.1.3',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 0,
    'is_ua_mismatch'   => 0,
    'recaptcha_solved' => 0,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
];

$uaData = [
    'score_session'        => 0,
    'score_ip'             => 0,
    'access_count_session' => 0,                      // 閾値 60
    'access_count_ip'      => JUST_THRESHOLD_IP + 1,  // 閾値+1 (601)
    'is_decreased_session' => 0,
    'is_decreased_ip'      => 0,
    'is_no_anomaly_session' => 0,
    'is_no_anomaly_ip'      => 0,
];

$is_over_threshold_session = 0; // 閾値を超えていない
$is_over_threshold_ip      = 1; // 閾値+1なので、閾値を超えている

/* score calculation
previous score 0

is_no_ua 0 => 加算なし

ua があるので比較できるが、
is_ua_mismatch 0 => 加算なし

過去1分の ip アクセス数が閾値を超えているので、
ip score に +3

ip が疑わしいアクセス (is_over_threshold_ip === 1) なので、
ip の減算ロジックは適用されず、加算後のスコアが計算結果となります。

session は疑わしいアクセスではない
過去30分にスコアが減点されたアクセスがない、
過去10分に異常がなかったという記録はないので、減点しません。
recaptcha_solved 0 なので、減算しません。

結果は、
session score は 0、ip score は 0 + 3 = 3 です。
*/
$scoreSessionExpected = 0;
$scoreIpExpected      = 3;

runTestWriteUaScores(
    $caseLabel,
    $contents,
    $uaData,
    $scoreSessionExpected,
    $scoreIpExpected,
    $pdo,
    CLEAR_DB);

$caseLabel = 'Check record count after IP threshold+1 test';
$expectedCount = 2;
checkRecordCount($caseLabel, $pdo, $expectedCount);

/* `is_ua_mismatch === 1 && is_over_threshold === 1` の複合条件のテスト */
$caseLabel = 'Case<br>is_ua_mismatch === 1 && is_over_threshold === 1 の複合条件のテスト';
$contents = [
    'session_id'       => 'mismatch_and_threshold',
    'ip_address'       => '192.168.0.1',
    'simple_ua'        => 'Chrome/91',
    'is_no_ua'         => 0,
    'is_ua_mismatch'   => 1,
    'recaptcha_solved' => 0,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
];

$uaData = [
    'score_session' => 0,
    'score_ip' => 0,
    'access_count_session' => JUST_THRESHOLD_SESSION , // 閾値
    'access_count_ip' => JUST_THRESHOLD_IP, // 閾値 
    'is_decreased_session' => 0,
    'is_decreased_ip' => 0,
    'is_no_anomaly_session' => 0 , 
    'is_no_anomaly_ip' => 0 
];

$is_over_threshold_session = 1; // 閾値
$is_over_threshold_ip = 1; // 閾値

/* score calculation
previous score 0、
加算 
    ua mismatch +2
    over threshold +3
減算
    疑わしいアクセス、減算なし
result score = 0 + 2 + 3 = 5
*/
$scoreSessionExpected = 5; 
$scoreIpExpected = 5; 

runTestWriteUaScores(
    $caseLabel,
    $contents,
    $uaData,
    $scoreSessionExpected,
    $scoreIpExpected,
    $pdo,
    CLEAR_DB);

//  checkRecordCount() の引数を定義します。
$caseLabel = 'Check record count after is_ua_mismatch && is_over_threshold test';
$expectedCount = 2; // session と ip の2件が挿入されることを期待します。
checkRecordCount($caseLabel, $pdo, $expectedCount);

/* subject_key の文字列の長さが上限である場合のテスト */
$session_id = str_repeat('a', 128); // 128文字の文字列を生成
$ip_address = str_repeat('b', 128); // 128文字の文字列を生成

$caseLabel = 'Case<br>subject_key 128文字の境界値テスト';
$contents = [
    'session_id'      => $session_id,
    'ip_address'      => $ip_address,
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
    'is_no_anomaly_session' => 0 , // 異常がないアクセスなので、こちらの値はスコアに影響します。
    'is_no_anomaly_ip' => 0       // 異常がないアクセスなので、こちらの値はスコアに影響します。
];

$is_over_threshold_session =
    checkOverThreshold(
        $uaData['access_count_session'],
        JUST_THRESHOLD_SESSION
        );
$is_over_threshold_ip=
    checkOverThreshold(
        $uaData['access_count_ip'], 
        JUST_THRESHOLD_IP
        );

/* risk score calculation
table をTRUNCATE したので、

previous score 0

is no ua 0 => 加算なし

ua があるので比較できるが、
is_ua_mismatch 0 => 加算なし

過去1分のアクセス数が閾値を超えていないので、
加算なし

疑わしいアクセスではない

過去30分
にスコアが減点されたアクセスがない、

過去10分に異常がなかったという
記録はないので、
点減しません。

recaptcha_solved 0 なので、
減算しません。
結果は、
score は 0 です。
*/
$scoreSessionExpected = 0;
$scoreIpExpected = 0;

runTestWriteUaScores(
    $caseLabel,
    $contents,
    $uaData,
    $scoreSessionExpected,
    $scoreIpExpected,
    $pdo,
    CLEAR_DB);

//  checkRecordCount() の引数を定義します。
$caseLabel = 'Check record count after subject_key 128文字の境界値テスト';
$expectedCount = 2; // session と ip の2件が挿入されることを期待します。
checkRecordCount($caseLabel, $pdo, $expectedCount);

/* session id の文字列の長さが129文字の境界値テスト */
$session_id = str_repeat('a', 129); // 129文字の文字列を生成
$ip_address = str_repeat('b', 129); // 129文字の文字列を生成

$caseLabel = 'Case<br>subject_key 129文字の境界値テスト<br>例外が発生することを期待します。';
$contents = [
    'session_id'      => $session_id,
    'ip_address'      => $ip_address,
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
    'is_no_anomaly_session' => 0 , 
    'is_no_anomaly_ip' => 0 
];
runTestCaseError(
    $caseLabel,
    $contents,
    $uaData,
    $pdo
);
//  データベース接続を閉じます。    
$dbManager->disconnect();
echo "Result: {$totalPass} passed, {$totalFail} failed.<br><br>";


