#student_test\test-write-user-agent-log.php
の改善バージョンの実行結果を以下に示します。

Database connection successful.

Table user_agent_logs truncated successfully.

PASS: session_id
PASS: ip_address
PASS: simple_ua
PASS: is_ua_mismatch
Check the user_agent_logs table for the inserted log.


student_test\test-write-user-agent-log.php
の改善点をさらに指摘してください。

答え＝＞
現在は正常系（is_ua_mismatch = 0）の1ケースしかありません。例えば以下の異常系を追加すべきです。

is_ua_mismatch = 1 のとき、DBに 1 で記録されるか
session_id が長い文字列のとき、正しくINSERTされるか


#DBに記録するデータ
        $sessionId    = $mock_request_content->getSessionId();
        $ipAddress    = $mock_request_content->getIpAddress();
        $simpleUa     = $mock_request_content->getCurrentSimpleUa();
        $isUaMismatch = $mock_request_content->getIsUaMismatch();

        $isNoUa = $mock_request_content->getIsNoUa();

        $isOverThresholdSession =
          $risk_evaluator->getIsOverThresholdSession();
        $isOverThresholdIp =
          $risk_evaluator->getIsOverThresholdIp();

        $riskEvaluationResult =
          $risk_evaluator->getRiskEvaluationResult  ();

        $scoreSession =
          $riskEvaluationResult->getScoreForSession();
        $scoreIp      =
          $riskEvaluationResult->getScoreForIp();

        $recaptchaSolved =
          $mock_request_content->getRecaptchaSolved();

        $isDecreasedSession =
          $risk_evaluator->getResultIsDecreasedSession();
        $isDecreasedIp =
          $risk_evaluator->getResultIsDecreasedIp();

        $isNoAnomalySession =
          $risk_evaluator->getIsNoAnomalySession();
        $isNoAnomalyIp =
          $risk_evaluator->getIsNoAnomalyIp();


# student_test\test-write-user-agent-log.php
を実行した結果、以下のような出力が得られました。
データベース接続に成功しました。

ケース 1: 正常系 (is_ua_mismatch = 0)
PASS: session_id
PASS: ip_address
PASS: simple_ua
PASS: is_ua_mismatch
PASS: access_time

ケース 2: 異常系 (is_ua_mismatch = 1)
PASS: session_id
PASS: ip_address
PASS: simple_ua
PASS: is_ua_mismatch
PASS: access_time

ケース 3: 異常系(session_id が 128 文字の長い文字列)
PASS: session_id
PASS: ip_address
PASS: simple_ua
PASS: is_ua_mismatch
PASS: access_time

結果: 15 件が成功、0 件が失敗しました。

student/write-ua.php
に定義されている
class WriteUaに記述されているwriteUserAgentLog() メソッドの
改善点を指摘してください。

答え＝＞
1. 例外処理がない

prepare() や execute() が失敗したとき、
PDO::ERRMODE_EXCEPTION が設定されていれば
PDOExceptionが発生しますが、
それをキャッチしていません。
他のメソッドや呼び出し元にも catch がなければ、
エラーが握りつぶされる可能性があります。


// 改善例
try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':session_id',    $sessionId,    PDO::PARAM_STR);
    $stmt->bindParam(':ip_address',    $ipAddress,    PDO::PARAM_STR);
    $stmt->bindParam(':simple_ua',     $simpleUa,     PDO::PARAM_STR);
    $stmt->bindParam(':is_ua_mismatch',$isUaMismatch, PDO::PARAM_INT);
    $stmt->execute();
} catch (PDOException $e) {
    throw new RuntimeException('writeUserAgentLog failed: ' . $e->getMessage(), 0, $e);
}
この処理はPDOExceptionをRuntimeExceptionに
  ラップして再スロー、その後、
  writeUserAgentLogを呼び出す側で
  RuntimeExceptionをキャッチして適切に処理することを想定していますか？
たとえば、以下のように記述することができますか？
try {
    $writeUa->writeUserAgentLog();
} catch (RuntimeException $e) {
    // ログ出力失敗の処理（例: エラーログに記録、ユーザーへの通知など）
    error_log('Failed to write user agent log: ' . $e->getMessage());
    //  ../db-error-page.php にリダイレクトする。
    //  ステイタスコード 500 を返す。
    http_response_code(500);
    header('Location: ../db-error-page.php');
    exit;
}
例外を catch してステータスコード 500 をつけて
エラーページにリダイレクトしようと考えます。
error_page.php は、
もう一つ役割がありまして、
疑わしいアクセスをリダイレクトして、
連続アクセスをやりにくくする役割を想定しています。
もう一つファイルを追加して、
db-error-page.php として、DBエラー専用のエラーページを作成するほうが
適切ですか？
答え
=>
はい、db-error-page.php を作成して、
DBエラー専用のエラーページにリダイレクトするのが適切です。
これにより、ユーザーに対してより具体的なエラーメッセージを提供でき、
システムの問題を明確に伝えることができます。
また、疑わしいアクセスを処理する error_page.php と
役割を分けることで、コードの責任範囲が明確になり、
保守性も向上します。

2. INSERT 成功の確認をしていない

execute() が成功しても rowCount() が 0 の場合
（制約違反で INSERT がスキップされるなど）に気づけません。
<?php
if ($stmt->rowCount() !== 1) {
    throw new RuntimeException('writeUserAgentLog: INSERT affected 0 rows.');
}

3. テストで未検証の境界値がある

Case 3 では 128 文字（VARCHAR(128) の上限）をテストしましたが、
129 文字以上のケースがありません。上限超過でDBエラーになるかどうかを確認するテストも追加すべきです。

# session id が 128 文字より長い場合はエラーになることを
確認しました。
session id に対しては、
hash('sha256', $sessionId)
  でハッシュ化して保存しますので、
  session id を DBに保存する際に問題はないと思います。
  また、
VARCHAR(128)が機能しています。
テストのブラウザー表示は以下に
なります。
Database connection successful.

Case 1: 正常系 (is_ua_mismatch = 0)
PASS: session_id
PASS: ip_address
PASS: simple_ua
PASS: is_ua_mismatch
PASS: access_time

Case 2: 異常系 (is_ua_mismatch = 1)
PASS: session_id
PASS: ip_address
PASS: simple_ua
PASS: is_ua_mismatch
PASS: access_time

Case 3: 異常系 (session_id が 128 文字の長い文字列)
PASS: session_id
PASS: ip_address
PASS: simple_ua
PASS: is_ua_mismatch
PASS: access_time

Case 4: 異常系 (session_id が 129 文字の長い文字列)

Fatal error: Uncaught PDOException: SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'session_id' at row 1 in C:\dev\student\write-ua.php:134 Stack trace: #0 C:\dev\student\write-ua.php(134): PDOStatement->execute() #1 C:\dev\student\student_test\test-write-user-agent-log.php(80): WriteUa->writeUserAgentLog() #2 C:\dev\student\student_test\test-write-user-agent-log.php(162): runTestCase('Case 4: \xE7\x95\xB0\xE5\xB8\xB8\xE7...', Array, Object(PDO)) #3 {main} Next RuntimeException: writeUserAgentLog failed: SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'session_id' at row 1 in C:\dev\student\write-ua.php:140 Stack trace: #0 C:\dev\student\student_test\test-write-user-agent-log.php(80): WriteUa->writeUserAgentLog() #1 C:\dev\student\student_test\test-write-user-agent-log.php(162): runTestCase('Case 4: \xE7\x95\xB0\xE5\xB8\xB8\xE7...', Array, Object(PDO)) #2 {main} thrown in C:\dev\student\write-ua.php on line 140

#write-ua.php
のメソッドで 
  public function writeUaAnomalyEvents()
では、
try catch 構文のブロックの
中で、RuntimeException をスローするようにしました。
-catch ブロックでキャッチしするのは、
PDOExceptionです。
-PDOException をキャッチして、RuntimeException にラップして再スローします。
-メソッドの呼び出し元で、2つの意味の RuntimeException をキャッチして、処理することは
可能ですか？
コードは、以下にようになります。
public function writeUaAnomalyEvents(): void {
    $pdo = $this->pdo;
    $sessionId = $this->sessionId;
    $ipAddress = $this->ipAddress;
    $isNoUa = $this->isNoUa;
    $isUaMismatch = $this->isUaMismatch;
    $isOverThresholdSession = $this->isOverThresholdSession;
    $isOverThresholdIp = $this->isOverThresholdIp;
    // UAに異常がない場合は何もせずに return
    if (
        $isNoUa === 0 
            && 
        $isUaMismatch === 0 
            && 
        $isOverThresholdSession === 0 
            && 
        $isOverThresholdIp === 0
        ) {
        return;
    }

    // ua_anomaly_events テーブルにデータを記録する処理
    $sql = "INSERT INTO ua_anomaly_events (
              session_id,
              ip_address,
              is_no_ua,
              is_ua_mismatch,
              is_over_threshold_session,
              is_over_threshold_ip,
              access_time
            ) VALUES (
              :session_id,
              :ip_address,
              :is_no_ua,
              :is_ua_mismatch,
              :is_over_threshold_session,
              :is_over_threshold_ip,
              NOW())";
    try{
      $stmt = $this->pdo->prepare($sql);
      // パラメーターの型を指定してバインドする
      $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_STR);
      $stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
      $stmt->bindParam(':is_no_ua', $isNoUa, PDO::PARAM_INT);
      $stmt->bindParam(':is_ua_mismatch', $isUaMismatch, PDO::PARAM_INT);
      $stmt->bindParam(':is_over_threshold_session', $isOverThresholdSession, PDO::PARAM_INT);
      $stmt->bindParam(':is_over_threshold_ip', $isOverThresholdIp, PDO::PARAM_INT);
      $stmt->execute();
      //  INSERTが正しく行われたか確認するために、影響を受けた行数をチェックする
      if ($stmt->rowCount() !== 1) {
          throw new RuntimeException('writeUaAnomalyEvents: INSERT affected 0 rows.');
      } // END IF
    }catch(PDOException $e){
        throw new RuntimeException('writeUaAnomalyEvents failed: ' . $e->getMessage(),
                                  self::DEFAULT_ERROR_CODE, //  code は自分で定義する。通常定数かする。マジックナンバーは避ける。 
                                  $e //  再スローする例外の前の例外のインスタンス。
                                  ); 
    } // END TRY CATCH
  } // END FUNCTION writeUaAnomalyEvents()

テスト結果
Database connection successful.

Case 1-1: anomaly event があるときにデータが正しく記録されることを確認します。
PASS: session_id
PASS: ip_address
PASS: is_no_ua
PASS: is_ua_mismatch
PASS: is_over_threshold_session
PASS: is_over_threshold_ip
PASS: access_time

Case 1-1-2: anomaly event があるときに（ua_mismatch = 1）データが正しく記録されることを確認し正しく記録されることを確認します。
PASS: session_id
PASS: ip_address
PASS: is_no_ua
PASS: is_ua_mismatch
PASS: is_over_threshold_session
PASS: is_over_threshold_ip
PASS: access_time

Case 1-1-3: anomaly event があるときに（ACCESS_COUNT_THRESHOLD_SESSION）データが正しく記録されることを確認し正しく記録されることを確認します。

PASS: session_id
PASS: ip_address
PASS: is_no_ua
PASS: is_ua_mismatch
PASS: is_over_threshold_session
PASS: is_over_threshold_ip
PASS: access_time

Case 1-1-4: anomaly event があるときに（ACCESS_COUNT_THRESHOLD_IP）データが正しく記録されることを確認し正しく記録されることを確認します。

PASS: session_id
PASS: ip_address
PASS: is_no_ua
PASS: is_ua_mismatch
PASS: is_over_threshold_session
PASS: is_over_threshold_ip
PASS: access_time

Case 1-2: 境界値 (session_id が 128 文字の長い文字列)
PASS: session_id
PASS: ip_address
PASS: is_no_ua
PASS: is_ua_mismatch
PASS: is_over_threshold_session
PASS: is_over_threshold_ip
PASS: access_time

Case 2-1: 異常がないときに、レコードが追加されないことを確認します。
過去のアクセスがゼロのとき（閾値以内）
expecting PASS: Record count did not change.

PASS: writeUaAnomalyEvents() executed without exceptions.
PASS: Record count did not change. Before: 1, After: 1

Case 4: SESSION UNDER THRESHOLD (59) expecting PASS: Record count did not change.

PASS: writeUaAnomalyEvents() executed without exceptions.
PASS: Record count did not change. Before: 1, After: 1

Case 5: SESSION THRESHOLD (60) expecting FAIL: Record count changed.

PASS: writeUaAnomalyEvents() executed without exceptions.
FAIL: Record count changed. Before: 1, After: 2

Case 6: SESSION OVER THRESHOLD (61) expecting FAIL: Record count changed.

PASS: writeUaAnomalyEvents() executed without exceptions.
FAIL: Record count changed. Before: 2, After: 3

Case 7: IP UNDER THRESHOLD (599) expecting PASS: Record count did not change.

PASS: writeUaAnomalyEvents() executed without exceptions.
FAIL: Record count changed. Before: 3, After: 4

Case 8: IP THRESHOLD (600) expecting FAIL: Record count changed.

PASS: session_id
PASS: ip_address
PASS: is_no_ua
PASS: is_ua_mismatch
PASS: is_over_threshold_session
PASS: is_over_threshold_ip
PASS: access_time

Case 9: IP OVER THRESHOLD (601) expecting FAIL: Record count changed.

PASS: session_id
PASS: ip_address
PASS: is_no_ua
PASS: is_ua_mismatch
PASS: is_over_threshold_session
PASS: is_over_threshold_ip
PASS: access_time

Result: 49 passed, 0 failed.

Now running error test case...

Case error: 異常系 (session_id が 129 文字の長い文字列)
PASS: 例外が発生しました: writeUaAnomalyEvents failed: SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'session_id' at row 1

Case 10: UAに異常がない場合は何もせずに return することを確認します。

Warning: Undefined array key "recaptcha_solved" in C:\dev\student\mock_request_content.php on line 41

Fatal error: Uncaught TypeError: Cannot assign null to property MockRequestContent1::$recaptchaSolved of type int in C:\dev\student\mock_request_content.php:41 Stack trace: #0 C:\dev\student\student_test\test-write-ua-anomaly-events.php(700): MockRequestContent1->__construct(Array) #1 C:\dev\student\student_test\test-write-ua-anomaly-events.php(771): testCaseNoAnomaly('Case 10: UA\xE3\x81\xAB\xE7...', Array, Array, Object(PDO)) #2 {main} thrown in C:\dev\student\mock_request_content.php on line 41