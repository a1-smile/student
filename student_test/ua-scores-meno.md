# リスク スコア を計算するロジック

## previous score session
記録された、session に紐づけられた
scoreを取得する。
$previousScoreSession
データベースのテーブル ua_scores から前回のスコアを取得する
recordがなければ、0を代入する
```php
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
ua が不一致する場合は、スコアを2加算する。
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
```
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
```

## scoreSession を減算する条件
-10 分間以上がないアクセス元 -1

-リキャプチャを通過 -4





