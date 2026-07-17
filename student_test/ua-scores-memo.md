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
    $isNoUa !== 1 の条件を追加されるロジックになります。*/
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


# session 失敗時に ip 側の SELECT が継続される問題

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/student_test/test-write-ua-scores.php#130-130), lines 130 to 220

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/student_test/test-write-ua-scores.php#220-220), lines 220 to 380

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/student_test/function-check.php)

コードの構造を確認しました。初心者向けに順を追って説明します。

---

## 問題の核心：`check()` は失敗しても「黙って続ける」

### check() の動き（function-check.php）

```
check('scoreSession', $row['score'], $scoreSessionExpected);
↓
FAIL: scoreSession (actual=5, expected=3) と表示する
↓
そのまま何もなかったように次の行へ進む
```

`check()` は `void`（戻り値なし）です。失敗を**表示するだけ**で、**実行を止める力を持っていません**。

---

### コードの流れで見る問題（test-write-ua-scores.php）

```
[session の SELECT]
  ↓
  $row === false → return ✅ ← ここは return している
  ↓
  check('scoreSession', ...) → FAIL でも続く ❌
  check('type', ...)         → FAIL でも続く ❌
  check('access_time_session', ...) → FAIL でも続く ❌
  ↓
[ip の SELECT] ← ← session の check が全部 FAIL でも必ずここに来る
  ↓
  check('scoreIp', ...)
```

`$row === false` の `return` は「行が見つからなかった場合」だけを守っています。  
行は見つかったけど **check が FAIL だった場合**は return していないため、ip 側に進んでしまいます。

---

### なぜ問題なのか（連鎖的に意味を失う）

session の check が失敗しているということは、**writeUaScores() がそもそも正しく動いていない**可能性があります。  
その状態で ip の check が PASS しても、「ip だけ偶然合っている」のか「本当に正常」なのか判断できません。  
テストの信頼性が下がります。

---

### 解決策：`check()` に戻り値を持たせ、失敗時に return する

**ステップ1：`check()` が `bool` を返すように変更する**

function-check.php の `check()` を `void` → `bool` に変更します。

```php
function check(string $label, mixed $actual, mixed $expected): bool {
    // ... 既存の処理 ...
    if ($actual === $expected) {
        // PASS
        return true;
    } else {
        // FAIL
        return false;
    }
}
```

**ステップ2：session の check 群をまとめて判定し、1つでも失敗したら return する**

```php
// session の check 群
$ok  = check('subject_key_session_mock',     $row['subject_key'], $session_id);
$ok &= check('subject_key_session_contents', $row['subject_key'], $contents['session_id']);
$ok &= check('scoreSession',                 $row['score'],       $scoreSessionExpected);
$ok &= check('type',                         $row['subject_type'], 'session');
$ok &= check('access_time_session',          $diff >= 0 && $diff < ACCEPTABLE_TIME_DIFF_SEC, true);

if (!$ok) {
    echo "  session checks failed. skipping ip checks.<br><br>";
    return; // ← session が失敗したら ip には進まない
}

// ip の SELECT・check はここから
```

---

### まとめ

| 状況 | 現状 | 修正後 |
|---|---|---|
| session の SELECT で行なし | return ✅ | return ✅（変わらず） |
| session の `check()` が FAIL | ip に進む ❌ | return する ✅ |
| session の `check()` が全 PASS | ip に進む ✅ | ip に進む ✅（変わらず） |

`check()` は「結果を表示する係」、`if (!$ok) return` が「失敗したら止める係」と役割を分けるのがポイントです。

# student_test\test-write-ua-scores.php を実行しました。
ブラウザーでの表示は、以下に示すようになります。
テストコードの改善点を指摘してください。
Database connection successful.

Case
session insert / ip insert, $is_no_ua === 1
Table ua_scores truncated successfully.

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

Check record count after session insert / ip insert
PASS: Record count is as expected. Count: 2

Result: 11 passed, 0 failed.

# フィードバック


Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/student_test/test-write-ua-scores.php#1-1), lines 1 to 50

Searched for files matching `**/student_test/function-check.php`, 1 match

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/write-ua.php#1-1), lines 1 to 100

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/student_test/function-check.php)

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/write-ua.php#100-100), lines 100 to 200

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/mock_request_content.php#1-1), lines 1 to 60

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/mock_request_content.php#60-60), lines 60 to 97

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/mock_ua_repository.php)

テスト全体を読みました。改善点を重要度順にまとめます。

---

## 改善点

### 1. 【重要】`calculate_expected_score()` がプロダクションコードのミラーになっている

**現状の問題**: 期待値を `calculate_expected_score()` で計算していますが、これは `UserAgentRiskEvaluator` のスコア計算ロジックをそのまま再実装したものです。本番コードとテストコードに**同じバグが存在すると、テストはPASSしてしまいます**。

```php
// 現状：ロジックを再計算して期待値を求めている
$scoreSessionExpected = calculate_expected_score(
    $uaData['score_session'],
    $contents['is_no_ua'], ...
);
```

**改善案**: 各テストケースで、期待値をハードコードした具体的な数値にする。

```php
// 改善後：is_no_ua=1 なので +1 、その他は加算なし → 期待値は 0+1 = 1
$scoreSessionExpected = 1;
$scoreIpExpected      = 1;
```

これにより、テストが「入力に対して正しい出力が返るか」を直接検証できます。

---

### 2. 【重要】ip チェック側で `$ok` を追跡していない

session 側と ip 側で `$ok` の扱いが非対称です。

```php
// session 側：$ok を積み重ね、失敗時にスキップ判定している
$ok  = check('subject_key_session_mock', ...);
$ok &= check('scoreSession', ...);
if (!$ok) { return; }

// ip 側：check() の戻り値を $ok に代入していない
check('subject_key_ip_mock', ...);   // ← 戻り値を無視
check('scoreIp', ...);               // ← 戻り値を無視
```

ip チェックの失敗を `$totalFail` に積めているのは `check()` 内部のグローバル変数経由のためですが、**ip チェック失敗時に後続の `check()` を続けるかどうかが制御できていません**。session と同様に `$ok` で追跡すべきです。

---

### 3. 【重要】エラー終了時に `$totalFail` をインクリメントしていない

`runTestWriteUaScores()` 内でエラーが発生すると `return` しますが、失敗カウントが増えません。

```php
if ($row === false) {
    echo "  FAIL: No log entry found in ua_scores.<br><br>";
    // $totalFail++ が抜けている
    return;
}
```

これらのパスも `$totalFail` をインクリメントすべきです。

---

### 4. `$ok &=` のビット演算が型として不明確

```php
$ok  = check(...); // bool
$ok &= check(...); // ビット AND → 結果は int になる
```

`bool` と `int` が混在します。全 `check()` を実行しつつ結果を収集したい意図は理解できますが、明示的に書く方が安全です。

```php
$ok  = check(...);
$ok  = check(...) && $ok;  // 短絡しない場合
// または
$ok  = check(...)  & $ok;  // 全部実行したい場合はビット演算を明示
```

あるいは `(bool)($ok & check(...))` のように型を明示します。

---

### 5. `runTestCaseError()` が定義されているが一度も呼ばれていない

例外発生を期待するエラー系テスト用関数が定義されていますが、現在どのテストからも呼ばれていません。削除するか、対応するテストケースを追加する必要があります。

---

### 6. テストケースが 1 ケースのみ

ファイル冒頭のコメントに多数の `//: to do` が残っており、実装されているのは `session insert / ip insert, $is_no_ua === 1` の 1 ケースだけです。特に以下は優先度が高いです。

- `session update / ip update`（同一キーで2回書き込む）
- `access_count_session 59, 60, 61` の境界値テスト
- `is_ua_mismatch === 1 && is_over_threshold === 1` の複合条件

---

### まとめ（優先度順）

| # | 問題                                      | 影響 |
|---|------                                    |------|
| 1 | 期待値をロジックで計算している（ミラーリング問題） | バグを検出できない偽陽性 |
| 2 | ip 側の `$ok` が未追跡                      | ip 失敗後も処理が続く |
| 3 | エラー時に `$totalFail` が増えない           | 結果カウントが不正確 |
| 4 | `$ok &=` の型不明確                        | 可読性・型安全性 |
| 5 | `runTestCaseError()` が未使用              | デッドコード |
| 6 | テストケースが 1 つのみ                      | カバレッジ不足 |

# session update / ip update のテストケースを実装する場合は、
1. table ua_scores を truncate する設定で
runTestWriteUaScores() を実行する。
2. table ua_scores を truncate しない設定で
同じ session_id / ip_address で
runTestWriteUaScores() を実行する。
3. record count が 2 であることを確認する。

という方向性でよろしいでしょうか？

# session insert / ip update のテストケースを実装する場合は、
1. table ua_scores を truncate する設定で
runTestWriteUaScores() を実行する。

2. table ua_scores を truncate しない設定で,
- 1. と違う session_id で、
- 1. と同じ ip_address で
- ただし、ip ベースのスコアの値は１.と異なる値になるようにする。
という条件で
runTestWriteUaScores() を実行する。

ip ベースの score を 1. と 2. で異なる値にする理由は、
update のときは score が同じ場合と
異なる場合でsqlの挙動が変わるので、異なる値になるようにする、
という意図です。
3. record count が 3 であることを確認する。
     record count が 3 であることを確認する理由は、
     1. の session insert / ip insert のときに
    2つのレコードが作成されている
     2. の session insert / ip update のときに
     1つのレコードが作成されるので、
     合計3つのレコードが作成されることを想定しているためです。

4. 分かりやすいように、適切にコメントを入れる。
という方向性でよろしいでしょうか？

# 答え
To Do リストを更新しました

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/student_test/test-write-ua-scores.php#1-1), lines 1 to 100

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/student_test/test-write-ua-scores.php#100-100), lines 100 to 280

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/student_test/test-write-ua-scores.php#280-280), lines 280 to 450

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/student_test/test-write-ua-scores.php#450-450), lines 450 to 500

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/student_test/test-write-ua-scores.php#500-500), lines 500 to 560

コードの全体構造を把握しました。計画を立てます。 

Read [](file:///c%3A/Users/aigas/OneDrive/dev/student/student_test/test-write-ua-scores.php#560-560), lines 560 to 650

コードの構造をよく理解しました。計画を整理します。 

Created memory file [](file:///c%3A/Users/aigas/AppData/Roaming/Code/User/workspaceStorage/96854da97b29815c4ca80c0f41e1431b/GitHub.copilot-chat/memory-tool/memories/YzYwZWEyZDAtOWY0Ny00M2M2LTk5YjUtNTU3ODQxMDZhYmU4/plan.md)

はい、その方向性で問題ありません。計画をまとめます。

---

## Plan: session insert / ip update テストケース追加

**テストデータ設計**

| | session_id | ip_address | score_ip（mock） | scoreIpExpected |
|---|---|---|---|---|
| Step 1 (CLEAR_DB) | `ip_update_session_01` | `172.16.0.1` | 0 | **1** |
| Step 2 (NOT_CLEAR_DB) | `ip_update_session_02` ← **異なる** | `172.16.0.1` ← **同じ** | 1 ← step1の結果 | **2** ← step1と異なる値 ✓ |

**Steps**
1. Step 1: CLEAR_DB で実行 → `ip_update_session_01`（session INSERT）＋ `172.16.0.1`（ip INSERT）= 2レコード
2. Step 2: NOT_CLEAR_DB で実行 → `ip_update_session_02`（session INSERT）＋ `172.16.0.1`（ip **UPDATE**、score 1→2）= +1レコード
3. `checkRecordCount = 3` を確認
4. 各ステップに「なぜ ip score が 1 と 2 で異なる値になるか」の理由コメントを追加

**挿入位置**
test-write-ua-scores.php の session update / ip update の `checkRecordCount` 直後

**Relevant files**
- test-write-ua-scores.php — 挿入・コメント追加対象
- ファイル冒頭の `//: to do //session insert / ip update` も実装済みに更新

**Verification**
1. ブラウザで test-write-ua-scores.php を実行
2. step 1 / step 2 がそれぞれ全 PASS
3. `Record count is as expected. Count: 3` の PASS を確認

---

**1点補足**：`ip ベースのスコアの値を異なる値にする理由` のコメントについて、具体的にはこう記載する想定です。

```
// ip ベースの score を step 1（1）と step 2（2）で意図的に異なる値にしている。
// UPDATE 時に score が同じ値の場合と異なる値の場合で SQL の挙動が変わるため、
// UPDATE が確実に実行されることを確認するために、異なる値にしている。
```

この方向性で実装に進めてよろしいでしょうか？

To Do 項目が 5 件作成されました

# 現在カバーできていない箇所
【Critical】 decreaseScore() の未テスト分岐
decreaseScore() には5つの分岐がありますが、2つが完全に未テストです。

1. isSuspiciousAccess === 1  → return (no decrease)  ✅
2. decreased === 1           → return (no decrease)  ❌ 未テスト
3. isNoAnomaly === 1         → score -= 1, return    △ 部分的（score=0のみ）
4. recaptchaSolved === 1     → score -= 4/1, return  ❌ 未テスト
5. else                      → return (no decrease)  ✅

必要なテストケース：

#	テストケース	確認する分岐
A	is_decreased_session/ip = 1 のとき score が変化しない	分岐2
B	is_no_anomaly = 1 かつ score > 0 のとき -1 減算される	分岐3（実際の減算）
C	recaptcha_solved = 1 のとき session -4、ip -1 減算される	分岐4
D	recaptcha_solved = 1 かつ score < 4（session）のとき max(0) でクランプされる	分岐4 の floor


【Important】 加算ロジックの未テスト組み合わせ
#	テストケース	理由
E	is_ua_mismatch = 1 単独（threshold なし）	テスト11は mismatch+threshold の複合のみ
F	is_ua_mismatch = 1 && is_no_ua = 1 → mismatch が無視される	ロジックに isNoUa !== 1 の条件あり

【Nice to have】 UPDATE/UPDATE の score 組み合わせ
現在のテスト2は UPDATE/UPDATE だが score は両方変化している。

#	テストケース	理由
G	session UPDATE (score same) / ip UPDATE (score same)	両方 UPDATE かつ両方同値という最もシンプルな同値 UPDATE
H	session UPDATE (score change) / ip UPDATE (score same)	混在パターン

# 優先度まとめ
Priority 1（ロジックパスが全く実行されていない）
  A. is_decreased === 1  → 30分以内に減算済みのため skip
  C. recaptcha_solved === 1  → session -4 / ip -1 の減算

Priority 2（部分的にしか実行されていない）
  B. is_no_anomaly === 1 かつ score > 0  → 実際に score が 1 下がることを確認

Priority 3（組み合わせの網羅）
  E. is_ua_mismatch = 1 単独
  F. is_ua_mismatch = 1 + is_no_ua = 1 → mismatch が無視されること
  D. recaptcha の score floor (max 0)
  G/H. UPDATE/UPDATE の score 同値混在


# review
To Do リストを更新しました

Read [](file:///c%3A/dev/student/write-ua.php#316-316), lines 316 to 400

## `writeUaScores()` レビュー

---

### ✅ 良い点

- **SQL インジェクション対策**: プレースホルダー（`:session_id` 等）を一貫して使用している
- **例外ハンドリング**: `PDOException` を `DbWriteException` に適切にラップしている
- **`rowCount()` コメント**: `ON DUPLICATE KEY UPDATE` 時の `rowCount()` が信頼できない理由が正確かつ詳細に説明されており、設計判断の根拠が明確

---

### ⚠️ 指摘事項

**① 同名パラメーターの二重使用（要確認）**

```php
$sqlSession = "INSERT INTO ua_scores ... VALUES (..., :score_session, ...)
               ON DUPLICATE KEY UPDATE score = :score_session, ...";
```

`:score_session` が同一 SQL 内で **2 回** 登場しています。PDO のエミュレーションモード ON（デフォルト）では動作しますが、エミュレーション OFF（ネイティブプリペアドステートメント）の環境では挙動が保証されません。

より安全な書き方:
```sql
ON DUPLICATE KEY UPDATE score = VALUES(score), updated_at = VALUES(updated_at)
```
ただし `VALUES(col)` は MySQL 8.0.20 以降では非推奨のため、モダンな書き方は:
```sql
INSERT INTO ua_scores (...) VALUES (...) AS new_row
ON DUPLICATE KEY UPDATE score = new_row.score, updated_at = new_row.updated_at
```

現在のテストがすべて PASS しているため、現環境（エミュレーション ON）では問題ありませんが、環境依存のコードであることは認識しておくべきです。

---

**② トランザクションなし（設計上の選択として確認）**

session 書き込みと ip 書き込みが別々の try-catch に分かれています。session 書き込みが成功して ip 書き込みが失敗した場合、`ua_scores` テーブルに session のみ書き込まれた不整合な状態になります。

```
session UPSERT 成功 → ip UPSERT 失敗 → DbWriteException スロー
→ ua_scores に session レコードのみ存在する状態
```

整合性を保証する場合はトランザクションでまとめる選択肢があります:
```php
$pdo->beginTransaction();
try {
    // session UPSERT
    // ip UPSERT
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    throw new DbWriteException(...);
}
```

ただし、スコアの session/ip は独立した指標として扱い、片方だけ更新されることを許容する設計であれば現状のままで問題ありません。**設計として意図した判断かどうかの確認をお勧めします。**

---

### 📋 まとめ

| 項目 | 評価 | 対応 |
|--|--|--|
| SQL インジェクション対策 | ✅ | 不要 |
| 例外ハンドリング | ✅ | 不要 |
| `rowCount()` 方針 | ✅ | 不要 |
| 同名パラメーター二重使用 | ⚠️ | 環境確認 or SQL リファクタリング推奨 |
| トランザクションなし | ⚠️ | 設計意図の明確化推奨 |

To Do 項目が 3 件作成されました


```
$sql = "
INSERT INTO ua_scores (
    subject_type,
    subject_key,
    score,
    updated_at
)
VALUES (
    'session',
    :session_id,
    :score_insert,
    NOW()
)
ON DUPLICATE KEY UPDATE
    score = :score_update,
    updated_at = NOW()
";
```