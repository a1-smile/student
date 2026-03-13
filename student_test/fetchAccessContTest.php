<?php


    //  data base からアクセス回数を配列で取得する
    //  実装で記述

    function fetchAccessCountArrayTest(PDO $pdo, string $sessionId, string $ipAddress): array {
        // ここでデータベースからアクセス回数を取得するロジックを実装
        // 例: SQLクエリを実行して、$sessionId と $ipAddress に基づいてアクセス回数を取得する
        // 取得したアクセス回数の配列を返す

//         CREATE TABLE user_agent_logs (
//   id             INT AUTO_INCREMENT PRIMARY KEY,
//   session_id     VARCHAR(128) NOT NULL,
//   ip_address     VARCHAR(64)  NOT NULL,
//   simple_ua      VARCHAR(128) NOT NULL,
//   is_ua_mismatch TINYINT(1)   NOT NULL,
//   access_time    DATETIME     NOT NULL,
//   INDEX idx_sess_ip_time (session_id, ip_address, access_time)
// ) 

        //  データベースのuser_agent_logsの session_id カラムが $sessionId
        //  であるレコードを取得する。
        //  ip_address カラムが $ipAddress に一致するレコードを取得する。

        //  以上の二つのレコードのレコード数をカウントして、
        //  配列で返す。
        /**
 * 直近1分間のアクセス数を取得
 *
 * 戻り値の例:
 * [
 *   'session_id_access_count' => 5,
 *   'ip_address_access_count' => 2,
 * ]
 */
// function ua_get_access_count_last_minute(PDO $pdo, string $session_id, string $ip_address): array {
//     $sql = "SELECT 
//                 COUNT(CASE WHEN session_id = :sid THEN 1 END) as session_id_access_count,
//                 COUNT(CASE WHEN ip_address = :ip THEN 1 END) as ip_address_access_count
//             FROM user_agent_logs 
//             WHERE access_time >= (NOW() - INTERVAL 1 MINUTE)";

//     $stmt = $pdo->prepare($sql);

//     // パラメータのバインドと実行
//     $stmt->execute([
//         ':sid' => $session_id,
//         ':ip'  => $ip_address,
//     ]);

//     // 結果を連想配列として取得
//     $result = $stmt->fetch(PDO::FETCH_ASSOC);
//     $access_count_last_minute = $result;
//     return $access_count_last_minute;
// }
                        // session_idがプレースホルダーである :sid と等しい場合に 1 をカウントし、そうでない場合は NULL を返す
                        // ip_addressがプレースホルダーである :ip と等しい場合に
                        // 1 をカウントし、そうでない場合は NULL を返す
                        // これにより、session_id と ip_address の両方のアクセス数
                        // count は、null出ない値の数をカウントすることになります。
        $sql = "SELECT 
                  COUNT(CASE WHEN session_id = :sid THEN 1 END) as session_id_access_count,
                 COUNT(CASE WHEN ip_address = :ip THEN 1 END) as ip_address_access_count            FROM user_agent_logs 
                 WHERE access_time >= (NOW() - INTERVAL 1 MINUTE)";

        $stmt = $pdo->prepare($sql);

        // パラメータのバインドと実行
        // プレイスホルダーに値をバインドしてクエリを実行する
        $stmt->execute([
            ':sid' => $sessionId,
            ':ip'  => $ipAddress,
        ]);

        // 結果を連想配列として取得
        $resultArray = $stmt->fetch(PDO::FETCH_ASSOC);

        $accessCuntLastMinuteArray = $resultArray;

        return $accessCuntLastMinuteArray;


        }


    //  テストケース
    //  //  session start
session_start();


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





    $resultArray = 
    fetchAccessCountArrayTest($pdo, $sessionId, $ipAddress);

    echo '<br><br>';
    var_dump($resultArray); // 結果を確認
    echo '<br><br>';
    print_r($resultArray); // 結果を確認

    //  session_id を出力します。
    echo "Session ID: " . $sessionId . "<br>";
    //  IPアドレスを出力します。
    echo "IP Address: " . $ipAddress . "<br>";