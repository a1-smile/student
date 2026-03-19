<?php


class UaRepositoryImplementation implements UaRepository {
    
    //  プロパティ

    // serverからの基本情報
    private int $SessionId;
    private int $IpAddress;

    // PDO のインスタンスを保持するプロパティ
    private PDO $pdo;

    //  databaseからの情報
    private int $scoreSession;
    private int $scoreIp;

    private int $isDecreasedSession;
    private int $isDecreasedIp;

    // メソッド間でデータを共有するためのプロパティ
    private array $accessCountArray;
    private int $accessCountSession;
    private int $accessCountIp;


    //  コンストラクタで 
    // $SessionId と $IpAddress を初期化する
    //  PDO のインスタンスを受け取る
    public function __construct(PDO $pdo, int $SessionId, int $IpAddress) {
        $this->pdo = $pdo;

        $this->SessionId = $SessionId;
        $this->IpAddress = $IpAddress;

        $this->scoreSession = $this->fetchScore($this->pdo, (string)$this->SessionId, 'session');
        $this->scoreIp      = $this->fetchScore($this->pdo, (string)$this->IpAddress, 'ip');

        $this->accessCountArray   = $this->fetchAccessCountArray($this->pdo, (string)$this->SessionId, (string)$this->IpAddress);
        $this->accessCountSession = $this->plunkAccessCountSession($this->accessCountArray);
        $this->accessCountIp      = $this->plunkAccessCountIp($this->accessCountArray);

        $this->isDecreasedSession = $this-> ;
        $this->isDecreasedIp      = $this-> ;



    }


    //  追跡対象に対するスコアを返す getter
    public function getScoreSession(): int{
        return $this->scoreSession;
    }
    public function getScoreIp(): int{
        return $this->scoreIp;
    }

    //  data base から score を取得する
    //  テストのために、$pdo を引数に取る private メソッドを定義する
    private function fetchScore(PDO $pdo, string $subjectKey, string $subjectType): int{
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
        // データがない場合は false を返します。
    if ($result !== false) {
            return (int)$result['score'];
            } else {
                return 0; // スコアが見つからない場合は 0 を返す
            }

    }
    

    // アクセス回数を返す getter
    public function getAccessCountSession(): int{
        return $this->accessCountSession;
    }
    public function getAccessCountIp(): int{
        return $this->accessCountIp;
    }

    //  data base からアクセス回数を配列で取得する
    //  実装で記述
    private function fetchAccessCountArray(PDO $pdo, string $sessionId, string $ipAddress): array {
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
                COUNT(CASE WHEN ip_address = :ip THEN 1 END) as ip_address_access_count
                FROM user_agent_logs 
                WHERE access_time >= (NOW() - INTERVAL 1 MINUTE)";

        $stmt = $pdo->prepare($sql);

        // パラメータのバインドと実行
        // プレイスホルダーに値をバインドしてクエリを実行する
        $stmt->execute([
            ':sid' => $sessionId,
            ':ip'  => $ipAddress,
        ]);

        // 結果を連想配列として取得
        // 値は文字列で返されるため、(int) キャストして整数に変換する
        $resultArray = $stmt->fetch(PDO::FETCH_ASSOC);

        $accessCountLastMinuteArray['session_id_access_count'] = (int)$resultArray['session_id_access_count'];
        $accessCountLastMinuteArray['ip_address_access_count'] = (int)$resultArray['ip_address_access_count'];
    

        return $accessCountLastMinuteArray;


        }




    //  配列からアクセス回数を取得する
    //  戻り値の例:
    //  [
    //   'session_id_access_count' => 5,
    //     'ip_address_access_count' => 2,
    //   ]

    //  実装で記述
    // public function plunkAccessCountSession(array $accessCountArray): int;
    // public function plunkAccessCountIp(array $accessCountArray): int;
    private function plunkAccessCountSession(array $accessCountArray): int {
        $accessCountSession = $accessCountArray['session_id_access_count'] ?? 0;
        return $accessCountSession;
    }

    private function plunkAccessCountIp(array $accessCountArray): int {
        $accessCountIp = $accessCountArray['ip_address_access_count'] ?? 0;
        return $accessCountIp;        
    }

    // スコアが減少したかどうかを返す getter（1/0想定）
    // public function getIsDecreasedSession(): int;
    // public function getIsDecreasedIp(): int;


//     CREATE TABLE ua_score_history (
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


    /**
     * データベースの
     * ua_score_history テーブルで
     * subject_key カラム が $subject_keyで、
     * subject_type カラム が $subject_type であるレコードのうち、
     * is_decreased カラムの値が 1 で
     * access_time が直近30分以内のレコードの数をカウントする。
     * 
     */

    //  SQL 文の説明
    //  SELECT COUNT(*) as decreased_count
    //  SELECT COUNT(*) は条件に一致するレコードの数をカウントします。
    //  as decreased_count は、
    //  カウントされた数に decreased_count 
    //  というエイリアスを付けることを意味します。
    //  PHP 側で $result['decreased_count'] としてアクセスできるようになる。
    //  呼び出し元では、この値が 0 より大きければ直近30分間にスコア減少があったと判断する用途で使われます。
    private function countIsDecreasedLast30Minutes(PDO $pdo, string $subjectKey, string $subjectType): int {
        $sql = "SELECT COUNT(*) as decreased_count
                FROM ua_score_history
                WHERE subject_key = :subjectKey
                  AND subject_type = :subjectType
                  AND is_decreased = 1
                  AND access_time >= (NOW() - INTERVAL 30 MINUTE)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':subjectKey' => $subjectKey,
            ':subjectType' => $subjectType,
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['decreased_count'];
    }

    private function isDecreasedLast30Minutes(PDO $pdo, string $subjectKey, string $subjectType): int {
        $decreasedCount = $this->countIsDecreasedLast30Minutes($pdo, $subjectKey, $subjectType);
        return $decreasedCount > 0 ? 1 : 0;
    }

    
    public function getIsDecreasedSession(): int{
        return $this->isDecreasedLast30Minutes($this->pdo, (string)$this->SessionId, 'session');
    }
    public function getIsDecreasedIp(): int{
        return $this->isDecreasedLast30Minutes($this->pdo, (string)$this->IpAddress, 'ip');
    }
    
    // data base でスコアが減少したかどうかを確認する
    //  実装で記述
    // public function checkIsDecreasedLast30Minutes(string $subjectType, string $subjectKey): int;


    // 追跡対象に異常がない場合は 1 を返す getter
    public function getIsNoAnomalySession(): int;
    public function getIsNoAnomalyIp(): int;

    // data base で異常フラグがあるレコードの数を配列で取得する
    //  実装で記述
    // public function checkIsNoAnomalyLast10Minutes(string $sessionId, string $ipAddress): int;
    // 配列の要素の数から、
    // セッションID/IPアドレスに異常がないかどうかを取得する
    //  実装で記述
    // public function plunkIsNoAnomalySession(array $isNoAnomalyArray): int;
    // public function plunkIsNoAnomalyIp(array $isNoAnomalyArray): int;


    // UA に関するアクセスログを保存する
    //  実装で記述
    // public function recordUserAgentLogs(
    //     string $sessionId,
    //     string $ipAddress,
    //     string $simpleUa,
    //     int    $isUaMismatch
    // ): void;

    // 異常イベントを保存する
    //  実装で記述
    // public function recordAnomalyEvents(
    //     string $sessionId,
    //     string $ipAddress,
    //     int    $isNoUa,
    //     int    $isUaMismatch,
    //     int    $isOverThresholdSession,
    //     int    $isOverThresholdIp
    // ): void;

    // UA スコアを保存する（session / ip 共通）
    // subjectKey と subjectType が
    // 同じレコードがあれば更新、なければ新規作成する
    //  実装で記述
    // public function recordUaScores(
    //     string $subjectKey,
    //     string $subjectType,
    //     int    $score
    // ): void;

    // UA スコア履歴を保存する（session / ip 共通）
    //  実装で記述
    // public function recordUaScoreHistory(
    //     string $subjectKey,
    //     string $subjectType,
    //     int    $isNoUa,
    //     int    $isUaMismatch,
    //     int    $isOverThresholdSession,
    //     int    $isOverThresholdIp,
    //     int    $isNoAnomaly,  //last10minutes
    //     int    $recaptchaSolved,
    //     int    $isDecreased  //last30minutes
    // ): void;



}


?>

<?php
/**
 * database のtable ua_score_historyから、
 * subject_key カラム が $subject_key1かつ
 * subject_type カラム が $subject_type1 
 * であるレコードの数をカウントする。
 * 連想配列の 'decreased_count1' キーにカウントされた数を格納する。
 * そして
 * subject_key カラム が $subject_key2かつ
 * subject_type カラム が $subject_type2 であるレコードの数をカウントする。
 * 連想配列の 'decreased_count2' キーにカウントされた数を格納する。
    * そして、連想配列を返す。
 */

    function countIsDecreasedLast30MinutesForTwoSubjects(PDO $pdo, string $sessionId, string $session, string $ipAddress, string $ip): array {
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



?>