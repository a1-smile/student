# リスク スコア を計算するロジック

## previous score
$previousScore
データベースから前回のスコアを取得する
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
## score if is no ua
```php
    if ($isNoUa === 1) {
        $isNoUaScore = $previousScore + 1;
    } else {
        $isNoUaScore = $previousScore;
    } END IF-ELSE
```
## score if is ua mismatch
```php
    if ($isUaMismatch === 1 and $isNoUa !== 1) {
        $isUaMismatchScore = $isNoUaScore + 2;
    } else {
        $isUaMismatchScore = $isNoUaScore;
    } END IF-ELSE
```
## score if over threshold
```php
    if ($isOverThreshold === 1) {
        $isOverThresholdScore = $isUaMismatchScore + 3;
    } else {
        $isOverThresholdScore = $isUaMismatchScore;
    } END IF-ELSE
```
## score を減算しない条件
- 異常があるアクセス
```    if ($isNoUa === 1 or $isUaMismatch === 1 or $isOverThreshold === 1)
``` 
- 30分以内に減算された


