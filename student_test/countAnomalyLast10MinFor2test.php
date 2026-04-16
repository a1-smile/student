<?php

// 変数定義
$testSessionId = hash('sha256', session_id());
$testIpAddress = $_SERVER['REMOTE_ADDR'];
$unmatchedSession = 'unmatched_session';
$unmatchedIp = '10.99.99.99';


     function countAnomalyLast10MinFor2Test(PDO $pdo, string $sessionId, string $ipAddress): array {
        // ここでデータベースから異常イベントの数を取得するロジックを実装
        // 例: SQLクエリを実行して、$sessionId と $ipAddress に基づいて異常イベントの数を取得する
        // 取得した異常イベントの数を配列で返す


//         CREATE TABLE ua_anomaly_events (
// id INT AUTO_INCREMENT PRIMARY KEY,
// session_id VARCHAR(128) NOT NULL,
// ip_address VARCHAR(45) NOT NULL,
// is_no_ua TINYINT(1) NOT NULL DEFAULT 0,
// is_ua_mismatch TINYINT(1) NOT NULL DEFAULT 0,
// is_over_threshold_session TINYINT(1) NOT NULL DEFAULT 0,
// is_over_threshold_ip TINYINT(1) NOT NULL DEFAULT 0,
// access_time DATETIME NOT NULL,
// INDEX idx_session_time (session_id, access_time),
// INDEX idx_ip_time (ip_address, access_time),
// INDEX idx_time (access_time)
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    $sql = "SELECT 'session' as type, COUNT(*) as anomaly_count
            FROM ua_anomaly_events 
            WHERE session_id = :sessionId AND access_time >= (NOW() - INTERVAL 10 MINUTE)
            UNION ALL
            SELECT 'ip' as type, COUNT(*) as anomaly_count
            FROM ua_anomaly_events 
            WHERE ip_address = :ipAddress AND access_time >= (NOW() - INTERVAL 10 MINUTE);
            ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sessionId' => $sessionId,
            ':ipAddress' => $ipAddress,
        ]);

        $resultArray = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // このクエリを実行して、
        // fetchAll すると、以下のような結果が得られます。
        // $anomalyCountArrayString = [
        //     ['type' => 'session', 'anomaly_count' => '5'],
        //     ['type' => 'ip', 'anomaly_count' => '3']
        // ]; 

        $anomalyCountArray = [
            'session' => 0,
            'ip' => 0
        ];
        foreach ($resultArray as $row) {
            if ($row['type'] === 'session') {
                $anomalyCountArray['session'] = (int)$row['anomaly_count'];
            } elseif ($row['type'] === 'ip') {
                $anomalyCountArray['ip'] = (int)$row['anomaly_count'];
            }
        }
        //  最終的に、以下のような配列が得られます。
        // $anomalyCountArray = [
        //     'session' => 5,
        //     'ip' => 3
        // ];

        return $anomalyCountArray;

    }


    //  テストケース
    //  //  session start
session_start();

//  log を書き込む関数定義
/**
 * UAアクセスログを記録
 */
function ua_anomaly_test(PDO $pdo, string $session_id, string $ip_address, int $is_no_ua, int $is_ua_mismatch, int $is_over_threshold_session, int $is_over_threshold_ip): void {
    $sql = 'INSERT INTO ua_anomaly_events (session_id, ip_address, is_no_ua, is_ua_mismatch, is_over_threshold_session, is_over_threshold_ip, access_time)
            VALUES (:session_id, :ip_address, :is_no_ua, :is_ua_mismatch, :is_over_threshold_session, :is_over_threshold_ip, NOW())';
    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(':session_id',     
    $session_id, PDO::PARAM_STR);

    $stmt->bindValue(':ip_address',
    $ip_address, PDO::PARAM_STR);

    $stmt->bindValue(':is_no_ua',
    $is_no_ua, PDO::PARAM_INT); // 仮の値

    $stmt->bindValue(':is_ua_mismatch',
    $is_ua_mismatch, PDO::PARAM_INT);

    $stmt->bindValue(':is_over_threshold_session',
    $is_over_threshold_session, PDO::PARAM_INT);

    $stmt->bindValue(':is_over_threshold_ip',
    $is_over_threshold_ip, PDO::PARAM_INT);
    
    $stmt->execute();
}



    //  一階層上にある common フォルダ内の'dbmanager.php' を require_once します。
    // require_once 'common/dbmanager.php';
    require_once __DIR__ . '/../common/dbmanager.php';


    //  DBManager クラスのインスタンスを作成し、
    // PDO インスタンスを取得します。
    $dbManager = new DBManager();
    //  データベースに接続
    $dbManager->connect();
    $is_connected = $dbManager->is_connected();
    echo '<br><br>';
    echo '接続状態: ';
    echo '<br><br>';
    echo 'expected: true <br>';
    var_dump($is_connected); // 接続状態を確認
    echo '<br><br>';

    $pdo = $dbManager->get_db();
echo 'テスト開始<br><br>';
    // データベースに接続できているか確認します。
    if ($pdo) {
        echo "データベースに接続できました。";
    } else {
        echo "データベースに接続できませんでした。";
    }


    // // セッションIDを取得します。
    // $sessionId = session_id();
    // // 取得したセッションIDをハッシュ化
    // $sessionId = hash('sha256', $sessionId);
    // // クライアントのIPアドレスを取得します。
    // $ipAddress = $_SERVER['REMOTE_ADDR'];

    // // dummyIp を定義します。
    // $unmatchedIp = '127.0.0.1';
    // // dummySessionId を定義します。
    // $unmatchedSession = 'dummy_session_id';

function test1(PDO $pdo, string $testSessionId, string $testIpAddress, string $unmatchedSession, string $unmatchedIp) {
//  テーブルua_anomaly_events を一度クリアします。
$pdo->exec("TRUNCATE TABLE ua_anomaly_events");



//  log を書き込む関数を呼び出して、アクセスログを記録します。

$arguments_array = [
    // session_id, ip_address, is_no_ua, is_ua_mismatch, is_over_threshold_session, is_over_threshold_ip
    [$testSessionId,        $testIpAddress,     1, 1, 1, 1],
    [$testSessionId,        $unmatchedIp,   1, 1, 1, 1],
    [$unmatchedSession,     $unmatchedIp,   1, 1, 1, 1],
    [$testSessionId,        $unmatchedIp,   1, 1, 1, 1],
    [$testSessionId,        $testIpAddress,   1, 1, 1, 1]
];

foreach ($arguments_array as $args) {
    ua_anomaly_test($pdo, $args[0], $args[1], $args[2], $args[3], $args[4], $args[5]);
}



    $resultArray = 
    countAnomalyLast10MinFor2Test(
        $pdo,
        $testSessionId,
        $testIpAddress,
        );

    echo '<br><br>';
    echo '<br><br>';
    echo 'expected: $resultArray["session"] = 4, $resultArray["ip"] = 2 <br>';

    //  session_id を出力します。
    echo "\$resultArray[\"session\"]: " . $resultArray["session"] . "<br>";
    //  IPアドレスを出力します。
    echo "\$resultArray[\"ip\"]: " . $resultArray["ip"] . "<br>";

    $expected = [
        'session' => 4,
        'ip' => 2
    ];

    echo '<br><br>';
    if ($resultArray == $expected) {
        echo "テスト成功: 結果が期待通りです。<br>";
    } else {
        echo "テスト失敗: 結果が期待と異なります。<br>";
    }
}

// ============================================
// エッジケーステスト
// ============================================

echo '<br><hr><br>';
echo '<b>エッジケーステスト開始</b><br><br>';

// --------------------------------------------
// テスト2: TRUNCATE 直後、INSERT なしで呼び出す場合
//   テーブルが空 → session=0, ip=0 を期待
// --------------------------------------------
$pdo->exec("TRUNCATE TABLE ua_anomaly_events");

$resultArray2 = countAnomalyLast10MinFor2Test(
    $pdo,
    $testSessionId,
    $testIpAddress
);

$expected2 = ['session' => 0, 'ip' => 0];

echo 'テスト2: テーブルが空の場合<br>';
echo 'expected: session=0, ip=0<br>';
echo 'actual: session=' . $resultArray2['session'] . ', ip=' . $resultArray2['ip'] . '<br>';
if ($resultArray2 === $expected2) {
    echo "テスト2 成功<br>";
} else {
    echo "テスト2 失敗<br>";
}

// --------------------------------------------
// テスト3: session_id は一致するが ip_address は一致しない場合
//   $sessionId のレコードのみ INSERT し、$ipAddress は別の値にする
// --------------------------------------------
$pdo->exec("TRUNCATE TABLE ua_anomaly_events");

$arguments_array3 = [
    [$testSessionId, $unmatchedIp, 1, 1, 1, 1],
    [$testSessionId, $unmatchedIp, 1, 1, 1, 1],
    [$testSessionId, $unmatchedIp, 1, 1, 1, 1],
];
foreach ($arguments_array3 as $args) {
    ua_anomaly_test($pdo, $args[0], $args[1], $args[2], $args[3], $args[4], $args[5]);
}

$resultArray3 = countAnomalyLast10MinFor2Test(
    $pdo,
    $testSessionId,
    $testIpAddress
);

$expected3 = ['session' => 3, 'ip' => 0];

echo '<br>テスト3: session_id のみ一致、ip_address は不一致<br>';
echo 'expected: session=3, ip=0<br>';
echo 'actual: session=' . $resultArray3['session'] . ', ip=' . $resultArray3['ip'] . '<br>';
if ($resultArray3 === $expected3) {
    echo "テスト3 成功<br>";
} else {
    echo "テスト3 失敗<br>";
}

// --------------------------------------------
// テスト4: ip_address は一致するが session_id は一致しない場合
// --------------------------------------------
$pdo->exec("TRUNCATE TABLE ua_anomaly_events");

$arguments_array4 = [
    [$unmatchedSession, $testIpAddress, 1, 1, 1, 1],
    [$unmatchedSession, $testIpAddress, 1, 1, 1, 1],
];
foreach ($arguments_array4 as $args) {
    ua_anomaly_test($pdo, $args[0], $args[1], $args[2], $args[3], $args[4], $args[5]);
}

$resultArray4 = countAnomalyLast10MinFor2Test(
    $pdo,
    $testSessionId,
    $testIpAddress
);

$expected4 = ['session' => 0, 'ip' => 2];

echo '<br>テスト4: ip_address のみ一致、session_id は不一致<br>';
echo 'expected: session=0, ip=2<br>';
echo 'actual: session=' . $resultArray4['session'] . ', ip=' . $resultArray4['ip'] . '<br>';
if ($resultArray4 === $expected4) {
    echo "テスト4 成功<br>";
} else {
    echo "テスト4 失敗<br>";
}

// --------------------------------------------
// テスト5: session_id も ip_address も一致しない場合
// --------------------------------------------
$pdo->exec("TRUNCATE TABLE ua_anomaly_events");

$arguments_array5 = [
    [$unmatchedSession, $unmatchedIp, 1, 1, 1, 1],
    [$unmatchedSession, $unmatchedIp, 1, 1, 1, 1],
];
foreach ($arguments_array5 as $args) {
    ua_anomaly_test($pdo, $args[0], $args[1], $args[2], $args[3], $args[4], $args[5]);
}

$resultArray5 = countAnomalyLast10MinFor2Test(
    $pdo,
    $testSessionId,
    $testIpAddress
);

$expected5 = ['session' => 0, 'ip' => 0];

echo '<br>テスト5: session_id も ip_address も不一致（レコードはあるが該当なし）<br>';
echo 'expected: session=0, ip=0<br>';
echo 'actual: session=' . $resultArray5['session'] . ', ip=' . $resultArray5['ip'] . '<br>';
if ($resultArray5 === $expected5) {
    echo "テスト5 成功<br>";
} else {
    echo "テスト5 失敗<br>";
}

echo '<br><hr><br>';
echo '<b>全エッジケーステスト完了</b><br>';

test1($pdo, $testSessionId, $testIpAddress, $unmatchedSession, $unmatchedIp);