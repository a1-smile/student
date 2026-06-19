# リスク スコア を計算するロジック

## previous score session
記録された、session に紐づけられた
scoreを取得する。
$previousScoreSession
データベースのテーブル ua_scores から前回のスコアを取得する
recordがなければ、0を代入する
```php
    //  $pdo を使用して、データベースからスコアを取得する
        $sql = "SELECT score FROM ua_scores WHERE subject_type = :subjectType AND subject_key = :subjectKey";   
        $stmt = $pdo->prepare($sql);
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
```
## score if is no ua session
uaが存在しない場合は、スコアを1加算する。
```php
    if ($isNoUa === 1) {
        $isNoUaScoreSession = $previousScoreSession + 1;
    } else {
        $isNoUaScoreSession = $previousScoreSession;
    } END IF-ELSE
```
## score if is ua mismatch session
ua が不一致の場合は、スコアを2加算する。
```php
    //  ua が存在しない場合は比較対象にしない。
    //  例えば、遷移前に uaなし、遷移後に uaなし
    //  の場合 「'' と '' で ua 一致」とすることは
    //  適切ではない。  
    /*たとえば、
    商品番号で商品を認識している場合、
    商品番号がない商品同士を
    どちらも商品番号がないから、
    同じ商品であるとみなすのは適切ではない。
    また、違う商品であるとも言えない。
    というのと同じです。*/
    /*という理由で、$isUaMismatch のチェックに
    $isNoUa !== 1 の条件を追加しています。*/
    if ($isUaMismatch === 1 and $isNoUa !== 1) {
        $isUaMismatchScoreSession = $isNoUaScoreSession + 2;
    } else {
        $isUaMismatchScoreSession = $isNoUaScoreSession;
    } END IF-ELSE
```
## score if over threshold session
アクセス回数が閾値を超えた場合は、スコアを3加算する。
```php
    if ($isOverThresholdSession === 1) {
        $isOverThresholdScoreSession = $isUaMismatchScoreSession + 3;
    } else {
        $isOverThresholdScoreSession = $isUaMismatchScoreSession;
    } END IF-ELSE
```
## scoreSession を減算しない条件
user_agent_risk_evaluator.php file の
メソッド private function decreaseScore で
早期リターンする場合に相当します。

- 異常があるアクセス
```    if ($isNoUa                 === 1 or
           $isUaMismatch           === 1 or 
           $isOverThresholdSession === 1 or
           $isOverThresholdIp      === 1) 
``` 
この条件に当てはまるアクセスは、
異常があるアクセスとみなされるため、
スコアを減算しないこととする。

この条件は、
private function decreaseScore の
以下の記述に相当します。
```php
 private function getIsSuspiciousAccess(): int {
        if ($this->isNoUa === 1) {
            return 1;
        } 
        if ($this->isUaMismatch === 1) {
            return 1;
        }
        if ($this->isOverThresholdSession === 1) {
            return 1;
        }
        if ($this->isOverThresholdIp === 1) {
            return 1;
        }
        return 0;
    }

    $this->isSuspiciousAccess = 
        $this->getIsSuspiciousAccess();
//  疑わしいアクセスの場合は、スコアを減算しない
        if ($isSuspiciousAccess === 1) {
            return $score;
        }
```

- 30分以内に減算された場合は、さらに減算はしない。
DBのテーブル ua_score_history から、

カラム subject_key が 追跡対象のセッションIDである、
かつ、
カラム subject_type が 'session' である、
かつ、
カラム is_decreased が 1 である、
かつ、
カラム created_at が 現在時刻から30分以内である、
以上の条件を満たすレコードが存在する場合は、
スコアを減算しないこととする。

この条件は、
private function decreaseScore の
以下の記述に相当します。
```
// 30分以内に減算された場合は
        // さらに減算はしない
        if ($decreased === 1) {
            return $score;
        }
        
        $decreased の値は、
        ua_repository_implementation.php
        に記述されているメソッド
            private function countIsDecreasedLast30MinutesForTwoSubjects(PDO $pdo, string $sessionId, string $session, string $ipAddress, string $ip): array {
        $sql = "SELECT 
                    COUNT(CASE WHEN subject_key = :sessionId AND subject_type = :session_id AND is_decreased = 1 THEN 1 END) as session_decreased_count,
                    COUNT(CASE WHEN subject_key = :ipAddress AND subject_type = :ip_address AND is_decreased = 1 THEN 1 END) as ip_decreased_count
                    
                FROM ua_score_history WHERE access_time >= (NOW() - INTERVAL 30 MINUTE)";

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
```
    private function countIsDecreasedLast30MinutesForTwoSubjects
    の戻り値が配列なのですが、その配列の要素
     'session_decreased_count'キーに対する値が 1 以上であれば、
     $decreased の値は 1 となる。
     というロジックになっています。

## scoreSession を減算する条件
- 10 分間異常がないアクセス元 -1
request_content_implementation.php
に記述されているメソッド
```
     private function countAnomalyLast10MinFor2(PDO $pdo, string $sessionId, string $ipAddress): array {
        // ここでデータベースから異常イベントの数を取得するロジックを実装
        // 例: SQLクエリを実行して、$sessionId と $ipAddress に基づいて異常イベントの数を取得する
        // 取得した異常イベントの数を配列で返す

        //  データベースのua_anomaly_eventsの session_id カラムが $sessionId
        //  であるレコードの数をカウントする。

        //  ip_address カラムが $ipAddress
        //  であるレコードの数をカウントする。


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

        $anomalyCountArray = ['session' => 0, 'ip' => 0]; // デフォルト値を設定
        foreach ($resultArray as $row) {
            if ($row['type'] === 'session') {
                $anomalyCountArray['session'] = (int)$row['anomaly_count'];
            } elseif ($row['type'] === 'ip') {
                $anomalyCountArray['ip'] = (int)$row['anomaly_count'];
            }
        }

        return $anomalyCountArray;

    }
```
このメソッドの戻り値が配列なのですが、その配列の要素
 'session'キーに対する値が 0 であれば、
 10 分間異常がないアクセス元とみなし、スコアを1減算する。
 ただし、スコアが0未満にならないようにする。

- リキャプチャを通過 -4

リキャプチャを通過した場合は、スコアを4減算する。
ただし、スコアが0未満にならないようにする。

user_agent_risk_evaluator.php file の
class UserAgentRiskEvaluator に定義
されている定数
 const DECREASE_SCORE_SESSION= 4;
 const DECREASE_SCORE_IP = 1;
 を用いて、

 以下に示す、
 private function decreaseScore
 の
 $decreaseScore 引数に
 DECREASE_SCORE_SESSION を代入して、
 減算する。
     private function decreaseScore(
        int $score, 
        int $isSuspiciousAccess,
        int $decreased, 
        int $isNoAnomaly,
        int $recaptchaSolved,
        int $decreaseScore  //  subject_type に応じて減算するスコアを指定する
        ): int {

        //  疑わしいアクセスの場合は、スコアを減算しない
        if ($isSuspiciousAccess === 1) {
            return $score;
        }

        // 30分以内に減算された場合は
        // さらに減算はしない
        if ($decreased === 1) {
            return $score;
        }

        if ($isNoAnomaly === 1) {
            // 10分以内に異常がない場合はスコアを1減算
            $score -= 1;
            //  ゼロ以下にならないようにする
            $score = max($score, 0);

            return $score;

            //  10分以内に異常がない場合で
            //  reCAPTCHAを解いてアクセスして来る場合は
            //  は、想定されないが、
            //  -1減算し、return するロジックにする

            }
            
        if ($recaptchaSolved === 1) {
                // 異常があった場合でreCAPTCHAを
                // 解いた場合はスコアを4減算

                /*$isNoAnomaly === 1 と判定されれば、
                このifブロックに入ることはない。*/

                // 異常があって、10分以上経過して
                // reCAPTCHAを解いてアクセス
                // して来る場合は通常ありえない。
                // つまり、異常なし 
                // かつ 
                // reCAPTCHAを解いてアクセス
                // して来る場合は想定されないアクセスであると考えられる。
                // したがって、
                // この 
                //  if ブロックで
                // 異常があって、
                // reCAPTCHAを解いてアクセスして来る場合は
                // スコアを$decreaseScore減算するというロジックにする
                //  ただし、$decreaseScoreは
                //  subject_type に応じて減算するスコアを指定する
                //  このクラスの冒頭で定義してある
                //  const DECREASE_SCORE_SESSION= 4;
                //  const DECREASE_SCORE_IP = 1;
                //  を使用する。
            $score -= $decreaseScore;
                //  ゼロ以下にならないようにする
            $score = max($score, 0);

            return $score;

            }
            // 異常があって、
            // 直近で reCAPTCHAを解いていない場合は
            // スコアを減算しない
            return $score;
    }
# score 計算 logic まとめ
- 前回のスコアを取得する
記録がなければ、0を代入する
- uaが存在しない場合は、スコアを1加算する。
- ua が不一致の場合は、スコアを2加算する。
- アクセス回数が閾値を超えた場合は、スコアを3加算する。
- 疑わしいアクセスの場合は、スコアを減算しない。
- 30分以内に減算された場合は、さらに減算はしない。
- 10 分間異常がないアクセス元は、スコアを1減算する
ゼロ以下にならないようにする。
- リキャプチャを通過した場合は、スコアを減算する。
いかの条件に応じて減算するスコアを指定する。
1. session ベースで追跡する場合は、スコアを4減算する。
 const DECREASE_SCORE_SESSION= 4;

2. ip ベースで追跡する場合は、スコアを1減算する。
 const DECREASE_SCORE_IP = 1;

ただし、ゼロ以下にならないようにする。





