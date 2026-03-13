<?php
//  session start
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
    var_dump($is_connected); // 接続状態を確認
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

    $subjectTypeSession = 'session';
    $subjectTypeIp = 'ip';






    //  data base から score を取得する
    //  テストのために、$pdo を引数に取る関数を定義する
     function fetchScore_test(PDO $pdo, string $subjectKey, string $subjectType): int{
        // ここでデータベースからスコアを取得するロジックを実装
        // 例: SQLクエリを実行して、$subjectKey と $subjectType に基づいてスコアを取得する
        // 取得したスコアを返す

//         CREATE TABLE ua_scores (
//   subject_type ENUM('session', 'ip') NOT NULL,
//   subject_key  VARCHAR(128) NOT NULL,
//   score        INT UNSIGNED NOT NULL DEFAULT 0,
//   updated_at   DATETIME NOT NULL
//     DEFAULT CURRENT_TIMESTAMP
//     ON UPDATE CURRENT_TIMESTAMP,
//   PRIMARY KEY (subject_type, subject_key)
// ) ENGINE=InnoDB
//   DEFAULT CHARSET=utf8mb4
//   COLLATE=utf8mb4_unicode_ci;

    //  $pdo を使用して、データベースからスコアを取得する
        $stmt = $pdo->prepare("SELECT score FROM ua_scores WHERE subject_type = :subjectType AND subject_key = :subjectKey");
        $stmt->execute(['subjectType' => $subjectType, 'subjectKey' => $subjectKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // fetch(PDO::FETCH_ASSOC) は、
        // 実行済みのSQLクエリの結果セットから 
        // 1行だけ 取得し、
        // カラム名をキーとする連想配列 として返します。

        if ($result) {
            return (int)$result['score'];
        } else {
            return 0; // スコアが見つからない場合は 0 を返す
        }


    }

    $scoreSession = fetchScore_test($pdo, $sessionId, $subjectTypeSession);
    $scoreIp = fetchScore_test($pdo, $ipAddress, $subjectTypeIp);

    echo "Session ID: $sessionId, Score: $scoreSession\n";
    echo '<br><br>';
    echo "IP Address: $ipAddress, Score: $scoreIp\n";
