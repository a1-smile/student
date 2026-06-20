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
 private function decreaseScore(
        int $score, 
        int $isSuspiciousAccess,
        int $decreased, 
        int $isNoAnomaly,
        int $recaptchaSolved,
        int $decreaseScore  //  subject_type に応じて減算するスコアを指定する
        )
 の引数
 $decreaseScore に
 定数 DECREASE_SCORE_SESSION を代入して、
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

# test 実行 1
risk score を計算するロジックは、
student_test\ua-scores-memo.md
に記述されています。
これを、踏まえて、
student_test\test-write-ua-scores.php
にテストコードを記述して、実行します。
第一段階として、
- session insert / ip insert, $is_no_ua === 1
のケースをテストします。
ブラウザーでの表示は、以下に示すようになります。
テストコードの改善点を指摘してください。
Database connection successful.

Truncating table ua_scores...
Table ua_scores truncated successfully.

Running test case
Case
session insert / ip insert, $is_no_ua === 1
PASS: subject_key_session_mock
PASS: subject_key_session_contents
PASS: scoreSession
PASS: type
PASS: access_time_session
PASS: subject_key_ip_mock
PASS: subject_key_ip_contents
PASS: scoreIp
PASS: type
PASS: access_time_ip

Checking record count...
Case
session insert / ip insert, $is_no_ua === 1
PASS: Record count is as expected. Count: 2

Result: 11 passed, 0 failed.

# フィードバック
1. 期待値のスコア計算根拠コメントが不足
is_no_ua === 1 のとき 
isSuspiciousAccess === 1 になるため 
decreaseScore() が早期リターンし、
is_no_anomaly_session = 1 
であってもスコアは減算されません。
この「減算がスキップされる理由」がコメントに書かれておらず、
後からコードを読んだ人が
「なぜ is_no_anomaly_session = 1 
なのにスコアが 0 にならないのか」
と混乱する可能性があります。
<?php
// 改善例
// 計算: previousScore(0) + isNoUa(1) = 1
// 減算: is_no_ua===1 → isSuspiciousAccess===1 → decreaseScore() 早期リターン
//       is_no_anomaly_session=1 であっても減算は行われない
$scoreSessionExpected = 1;

2. is_no_anomaly_session => 1 の設定が意図をミスリード
$uaData で 
is_no_anomaly_session => 1, 
is_no_anomaly_ip => 1 
を設定していますが、
is_no_ua === 1 の場合は 
isSuspiciousAccess === 1 
になるため、これらの値はスコアに影響しません。

「is_no_anomaly = 1 でも減算されないこと
（isSuspiciousAccess による早期リターン）
を確認するテスト」として意図的ならばコメントで明示すべきです。
そうでなければ is_no_anomaly_session => 0 
にした方が「このフラグはこのケースで関係ない」
という意図が明確になります。

3. runTestWriteUaScores() 内で 
session SELECT 失敗後も ip SELECT が継続される
session の SELECT で FAIL が発生した後、
return するのではなく ip 側の SELECT まで実行が続きます。
失敗後の継続実行は、
後続のチェック結果が連鎖的に意味を持たなくなるため、
session 取得失敗の時点で return する方が明確です。

現状のコード（test-write-ua-scores.php:157）では 
if ($row === false) { echo "FAIL..."; return; } 
となっており session 側は return していますが、
session の check() 群が失敗しても 
return せず ip 側に進む点が懸念です。

4. echo "<b>Running test case</b><br>" 
と関数内 echo "<b>{$caseLabel}</b><br>" が二重出力
呼び出し側で「Running test case」と出力し、
関数内でもケースラベルを出力しているため、
出力が重複してやや見づらくなっています。
「Running test case」の echo は削除するか、
関数内の出力に統合することを検討してください。

5. checkRecordCount() のラベルが 
runTestWriteUaScores() と同一
$caseLabel を再代入して同じ文字列をセットしているため、
2つのテスト出力が同じラベルになっています。
record count の確認であることが分かるよう、
ラベルを区別すべきです。
例えば、以下のように変更すると、出力がより明確になります。
```php
<?php
// 改善例
$caseLabel = 'Record count after: session insert / ip insert';
```

6. truncateUaScoresTable() の呼び出し責務が 
runTestWriteUaScores() の外にある
現在は呼び出し側が truncate のタイミングを管理する設計で、
"insert" か "update" かを外部から制御しています。
今後 "session update / ip update" 
などのテストケースを追加するとき、
truncate し忘れるリスクがあります。

runTestWriteUaScores() に
 bool $truncateBefore = false パラメータを追加するか、
 または insert テスト用・update テスト用の関数を分けることで、
 意図を明示できます。

 7. 定義済みの未使用定数
UNDER_THRESHOLD_SESSION, 
CHECK_THRESHOLD_SESSION, 
NOT_CHECK_THRESHOLD_SESSION 
など、今後のテストケース用に定義された定数がありますが、
現時点では未使用です。
将来の実装予定であれば // TODO:
 コメントを付けると意図が明確になります。


8. 境界値テストのコメントが TODOになっていない
ファイル冒頭のテストケースコメントに
「subject_key 128文字の境界値テスト」
と書かれていますが、実装がありません。
// TODO: として明示しておくと、
未実装であることが一目で分かります。

