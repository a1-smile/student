<?php
//  MAMP でサーバーを起動しておきます。
// session_start() します。
session_start();

echo 'テスト開始<br>';
echo 'セッションID: ' . session_id() . '<br>';
echo 'IPアドレス: ' . $_SERVER['REMOTE_ADDR'] . '<br>';

//  一階層上のディレクトリにある common ディレクトリにある
//  common\dbmanager.php を読み込みます。
echo 'DBManager を読み込みます。<br>';
require_once('../common/dbmanager.php');

//  データベースに接続します。

//  DBManager をインスタンス化します。
$dbm = new DBManager();
//  DBManager の connect() メソッドを呼び出して、データベースに接続します。
$dbm->connect();
//  pdo を取得します。
$pdo = $dbm->get_db();

echo 'データベースに接続try。<br>';
echo 'PDO オブジェクトをvar_dump: <br>';
var_dump($pdo);
echo '<br>';


//  session id を取得します。
$sessionId = session_id();
//  ipアドレスを取得します。
$ipAddress = $_SERVER['REMOTE_ADDR'];

//   CREATE TABLE ua_score_history (
//   id                INT AUTO_INCREMENT PRIMARY KEY,
//   subject_type      ENUM('session', 'ip') NOT NULL,
//   subject_key       VARCHAR(128) NOT NULL,
//   is_no_ua          TINYINT(1) NOT NULL DEFAULT 0,
//   is_ua_mismatch    TINYINT(1) NOT NULL DEFAULT 0,
//   is_over_threshold_session TINYINT(1) NOT NULL DEFAULT 0,
//   is_over_threshold_ip TINYINT(1) NOT NULL DEFAULT 0,
//   is_no_anomaly     TINYINT(1) NOT NULL DEFAULT 0,
//   recaptcha_solved  TINYINT(1) NOT NULL DEFAULT 0,
//   is_decreased      TINYINT(1) NOT NULL DEFAULT 0,
//   access_time       DATETIME NOT NULL,
//   INDEX idx_subject_time (subject_type, subject_key, access_time)
// ) ENGINE=InnoDB
//   DEFAULT CHARSET=utf8mb4
//   COLLATE=utf8mb4_unicode_ci;



//  ua_score_history テーブルのレコードを全て削除します。
$stmt = $pdo->prepare('DELETE FROM ua_score_history');
$stmt->execute();




//  ua_score_history テーブルに、
//  subject_type = 'session'
//  subject_key = $sessionId
//  is_no_ua = 0
//  is_ua_mismatch = 0
//  is_over_threshold_session = 0
//  is_over_threshold_ip = 0
//  is_no_anomaly = 0
//  recaptcha_solved = 0
//  is_decreased = 1
//  access_time = 現在の日時

//  for 文で 10 回繰り返します。
for ($i = 0; $i < 10; $i++) {

$stmt = $pdo->prepare('INSERT INTO ua_score_history (subject_type, subject_key, is_no_ua, is_ua_mismatch, is_over_threshold_session, is_over_threshold_ip, is_no_anomaly, recaptcha_solved, is_decreased, access_time) VALUES (:subject_type, :subject_key, :is_no_ua, :is_ua_mismatch, :is_over_threshold_session, :is_over_threshold_ip, :is_no_anomaly, :recaptcha_solved, :is_decreased, NOW())');
$stmt->execute([
    ':subject_type' => 'session',
    ':subject_key' => $sessionId,
    ':is_no_ua' => 0,
    ':is_ua_mismatch' => 0,
    ':is_over_threshold_session' => 0,
    ':is_over_threshold_ip' => 0,
    ':is_no_anomaly' => 0,
    ':recaptcha_solved' => 0,
    ':is_decreased' => 1
]);

}

    

         function plunkIsDecreasedIp_TEST(array $decreasedCountArray): int {
        $decreasedCountIp = $decreasedCountArray['ip_decreased_count'] ?? 0;
        return $decreasedCountIp > 0 ? 1 : 0;
    }

        function plunkIsDecreasedSession_TEST(array $decreasedCountArray): int {
        $decreasedCountSession = $decreasedCountArray['session_decreased_count'] ?? 0;
        return $decreasedCountSession > 0 ? 1 : 0;
    }

//      * database のtable ua_score_historyから、
//  * subject_key カラム が $sessionIdかつ
//  * subject_type カラム が $session 
//  * であるレコードの数をカウントする。
//  * 連想配列の 'session_decreased_count' キーにカウントされた数を格納する。
//  * そして
//  * subject_key カラム が $ipAddressかつ
//  * subject_type カラム が $ip であるレコードの数をカウントする。
//  * 連想配列の 'ip_decreased_count' キーにカウントされた数を格納する。
//  * そして、連想配列を返す。
//  * fetch される値は、
//  * デフォルトでは文字列であるため、
//  * (int) キャストして整数に変換する。
//  */

    function countIsDecreasedLast30MinutesForTwoSubjects_TEST(PDO $pdo, string $sessionId, string $session, string $ipAddress, string $ip): array {
        $sql = "SELECT 
                    COUNT(CASE WHEN subject_key = :sessionId AND subject_type = :session_id AND is_decreased = 1 AND access_time >= (NOW() - INTERVAL 30 MINUTE) THEN 1 END) as session_decreased_count,
                    COUNT(CASE WHEN subject_key = :ipAddress AND subject_type = :ip_address AND is_decreased = 1 AND access_time >= (NOW() - INTERVAL 30 MINUTE) THEN 1 END) as ip_decreased_count
                FROM ua_score_history";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sessionId' => $sessionId,
            ':session_id' => $session,
            ':ipAddress' => $ipAddress,
            ':ip_address' => $ip,
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            // COUNT の値は文字列で返されるため、(int) キャストして整数に変換する
            'session_decreased_count' => (int)$result['session_decreased_count'],
            'ip_decreased_count' => (int)$result['ip_decreased_count'],
        ];
    }


    $decreasedCountArray = 
    countIsDecreasedLast30MinutesForTwoSubjects_TEST(
        $pdo, 
        $sessionId, 
        'session', 
        $ipAddress,
        'ip');
echo 'decreasedCountArray: <br>';
echo 'var_dump<br>';
var_dump($decreasedCountArray);
echo '<br><br>';
echo 'print_r<br>';
print_r($decreasedCountArray);
echo '<br><br>';






    $isDecreasedSession_TEST = plunkIsDecreasedSession_TEST($decreasedCountArray);
    $isDecreasedIp_TEST = plunkIsDecreasedIp_TEST($decreasedCountArray);

echo 'isDecreasedSession_TEST: ' . $isDecreasedSession_TEST . '<br>';
echo 'expected : 1<br>';
echo 'isDecreasedIp_TEST: ' . $isDecreasedIp_TEST . '<br>';
echo 'expected : 0<br>';

// countIsDecreasedLast30MinutesForTwoSubjects_TEST()と
// plunkIsDecreasedIp_TEST()と
// plunkIsDecreasedSession_TEST()の
// テストを行いました。ブラウザ表示は以下になります。
// テスト開始
// セッションID: daf06113ff51a42504ad73c1d3a90ed8
// IPアドレス: ::1
// DBManager を読み込みます。
// データベースに接続try。
// PDO オブジェクトをvar_dump:
// object(PDO)#2 (0) { }
// decreasedCountArray:
// var_dump
// array(2) { ["session_decreased_count"]=> int(10) ["ip_decreased_count"]=> int(0) }

// print_r
// Array ( [session_decreased_count] => 10 [ip_decreased_count] => 0 )

// isDecreasedSession_TEST: 1
// expected : 1
// isDecreasedIp_TEST: 0
// expected : 0
// レビューをお願いします。