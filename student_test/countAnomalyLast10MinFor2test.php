<?php


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

        $anomalyCountArray = [];
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

    //  プロパティーの定義
    //  データベースアクセス情報
    // private $access_info;
    // //  データベースのユーザー名
    // private $user;
    // //  データベースのパスワード
    // private $password;
    // //  PDO インスタンス
    // private $db = null;
    // public function get_db() {
    //     return $this->db;
    // }
    // //  コンストラクタ
    // public function __construct() {
    //     $this->access_info = 'mysql:host=localhost;dbname=school;charset=utf8mb4';
    //     $this->user = 'root';
    //     $this->password = 'root';
    // }


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


    // セッションIDを取得します。
    $sessionId = session_id();
    // クライアントのIPアドレスを取得します。
    $ipAddress = $_SERVER['REMOTE_ADDR'];


//  テーブルua_anomaly_events を一度クリアします。
$pdo->exec("TRUNCATE TABLE ua_anomaly_events");



//  log を書き込む関数を呼び出して、アクセスログを記録します。

$arguments_array = [
    // session_id, ip_address, is_no_ua, is_ua_mismatch, is_over_threshold_session, is_over_threshold_ip
    [$sessionId, $ipAddress, 1, 1, 1, 1],
    [$sessionId, ''        , 1, 1, 1, 1],
    [''        , ''        , 1, 1, 1, 1],
    [$sessionId, ''        , 1, 1, 1, 1],
    [$sessionId, $ipAddress, 1, 1, 1, 1],
];

foreach ($arguments_array as $args) {
    ua_anomaly_test($pdo, $args[0], $args[1], $args[2], $args[3], $args[4], $args[5]);
}



    $resultArray = 
    countAnomalyLast10MinFor2Test(
        $pdo,
        $sessionId,
        $ipAddress,
        );

    echo '<br><br>';
    echo 'var_dump<br>';
    var_dump($resultArray); // 結果を確認
    echo '<br><br>';
    echo 'print_r<br>';
    print_r($resultArray); // 結果を確認

    echo 'expected: $resultArray["session"] = 4, $resultArray["ip"] = 2 <br>';

    //  session_id を出力します。
    echo "\$resultArray[\"session\"]: " . $resultArray["session"] . "<br>";
    //  IPアドレスを出力します。
    echo "\$resultArray[\"ip\"]: " . $resultArray["ip"] . "<br>";