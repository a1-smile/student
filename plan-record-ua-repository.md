# Plan for WriteUa Data to DB
plan 変更：
UaRepositoryImplementation
でuaに関係するデータベース処理を行う
予定でしたが、
可読性を考慮して、
DBにデータを記録する処理は別クラスで行うことにします。

class WriteUa{
  private PDO $pdo;
  private RequestContent $requestContent;
  private UaRepository $uaRepository; 

  private instance $userAgentRiskEvaluator;

  private instance $riskEvaluationResult;

  private string $sessionId;
  private string $ipAddress;
  private string $simpleUa;
  private int    $isUaMismatch;

  private int $isNoUa;
  private int $isOverThresholdSession;
  private int $isOverThresholdIp; 

  private int $scoreSession;
  private int $scoreIp;

  private int $recaptchaSolved;
  private int $isDecreasedSession;
  private int $isDecreasedIp;


  public function __construct(
    PDO $pdo,
    RequestContent $request_content,
    UaRepository $ua_repository,
    instance $user_agent_risk_evaluator
    ) {
        $this->pdo = $pdo;
        $this->requestContent = $request_content;
        $this->uaRepository   = $ua_repository;

        $this->userAgentRiskEvaluator =
          $user_agent_risk_evaluator;

        $sessionId    = $this->requestContent->getSessionId();
        $ipAddress    = $this->requestContent->getIpAddress();
        $simpleUa     = $this->requestContent->getCurrentSimpleUa();
        $isUaMismatch = $this->requestContent->getIsUaMismatch();

        $isNoUa = $this->requestContent->getIsNoUa();

        $this->isOverThresholdSession =
          $this->userAgentRiskEvaluator->getIsOverThresholdSession();
        $this->isOverThresholdIp =
          $this->userAgentRiskEvaluator->getIsOverThresholdIp();

        $this->riskEvaluationResult =
          $this->userAgentRiskEvaluator->evaluate();

        $this->scoreSession =
          $this->riskEvaluationResult->getScoreForSession();
        $this->scoreIp      =
          $this->riskEvaluationResult->getScoreForIp();

        $this->recaptchaSolved =
          $this->requestContent->getRecaptchaSolved();

        $this->isDecreasedSession =
          $this->userAgentRiskEvaluator->getIsDecreasedSession();
        $this->isDecreasedIp =
          $this->userAgentRiskEvaluator->getIsDecreasedIp();
    }

    public function writeUserAgentLog(
      PDO $pdo,
    string $sessionId,
      string $ipAddress,
      string $simpleUa,
      int $isUaMismatch,
    ) {
      // user_agent_logs テーブルにデータを記録する処理
      $sql = "INSERT INTO user_agent_logs (
                session_id,
                ip_address,
                simple_ua,
                is_ua_mismatch, 
                access_time
                )
              VALUES (
                :session_id,
                :ip_address,
                :simple_ua,
                :is_ua_mismatch,
                NOW())";
      $stmt = $this->pdo->prepare($sql);
      //  パラメーターの型を指定してバインドする
      $stmt->bindParam(':session_id', $sessionId, PDO::PARAM_STR);
      $stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
      $stmt->bindParam(':simple_ua', $simpleUa, PDO::PARAM_STR);
      $stmt->bindParam(':is_ua_mismatch', $isUaMismatch, PDO::PARAM_INT);
      $stmt->execute();


    }
}
- CREATE TABLE user_agent_logs (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  session_id     VARCHAR(128) NOT NULL,
  ip_address     VARCHAR(64)  NOT NULL,
  simple_ua      VARCHAR(128) NOT NULL,
  is_ua_mismatch TINYINT(1)   NOT NULL,
  access_time    DATETIME     NOT NULL,
  INDEX idx_sess_ip_time (session_id, ip_address, access_time)
) 

user_agent_logs にデータを記録する処理をかんがえます。

CREATE TABLE ua_anomaly_events (
id INT AUTO_INCREMENT PRIMARY KEY,
session_id VARCHAR(128) NOT NULL,
ip_address VARCHAR(45) NOT NULL,
is_no_ua TINYINT(1) NOT NULL DEFAULT 0,
is_ua_mismatch TINYINT(1) NOT NULL DEFAULT 0,
is_over_threshold_session TINYINT(1) NOT NULL DEFAULT 0,
is_over_threshold_ip TINYINT(1) NOT NULL DEFAULT 0,
access_time DATETIME NOT NULL,
INDEX idx_session_time (session_id, access_time),
INDEX idx_ip_time (ip_address, access_time),
INDEX idx_time (access_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




session_id を取得します。
$request_content = new RequestContentImplementation();

もしくは、RequestContent を
引数にとるクラスを作成して、
そこに RequestContentImplementation
のインスタンスを渡す方法も考えられます。
{private RequestContent $requestContent;
   construct(RequestContent $request_content) {
        $this->requestContent = $request_content;
}
}

$sessionId = $this->requestContent->getSessionId();
ip_address を取得します。
$ipAddress = $this->requestContent->getIpAddress();
simple_ua を取得します。
$simpleUa = $this->requestContent->getCurrentSimpleUa();
is_ua_mismatch を取得します。
$isUaMismatch = $this->requestContent->getIsUaMismatch();

テーブル ua_anomaly_events  にデータを記録する処理をかんがえます。
is_no_ua を取得します。
$isNoUa = $this->requestContent->getIsNoUa();

$ua_repository = new UaRepositoryImplementation(
  $session_id,
  $ip_address,
)

$ua_risk_evaluator = new UserAgentRiskEvaluator(
  $ua_repository,
  $request_content
);

$this->isOverThresholdSession =
  $this->userAgentRiskEvaluator->getIsOverThresholdSession();
$this->isOverThresholdIp =
  $this->userAgentRiskEvaluator->getIsOverThresholdIp();


CREATE TABLE ua_scores (
  subject_type ENUM('session', 'ip') NOT NULL,
  subject_key  VARCHAR(128) NOT NULL,
  score        INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at   DATETIME NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (subject_type, subject_key)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE ua_score_history (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  subject_type      ENUM('session', 'ip') NOT NULL,
  subject_key       VARCHAR(128) NOT NULL,
  is_no_ua          TINYINT(1) NOT NULL DEFAULT 0,
  is_ua_mismatch    TINYINT(1) NOT NULL DEFAULT 0,
  is_over_threshold_session TINYINT(1) NOT NULL DEFAULT 0,
  is_over_threshold_ip TINYINT(1) NOT NULL DEFAULT 0,
  is_no_anomaly     TINYINT(1) NOT NULL DEFAULT 0,
  recaptcha_solved  TINYINT(1) NOT NULL DEFAULT 0,
  is_decreased      TINYINT(1) NOT NULL DEFAULT 0,
  access_time       DATETIME NOT NULL,
  INDEX idx_subject_time (subject_type, subject_key, access_time)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

のカラム
is_decreased の値を取得するために、
classUserAgentRiskEvaluator 内で
プロパティ
  $isDecreasedSession;
  $isDecreasedIp;

  $riskEvaluationResult;

  $currentScoreSession;
  $currentScoreIp;
  を定義して、

  $this->riskEvaluationResult = $this->evaluate();

  $this->currentScoreSession =
    $this->riskEvaluationResult->getScoreForSession();
  $this->currentScoreIp =
    $this->riskEvaluationResult->getScoreForIp();

  private function isScoreDecreased(
    int $previousScore,
    int $currentScore,
  ) : int {
    if ($currentScore < $previousScore) {
      return 1;
    } else {
      return 0;
    }
  }

  $this->isDecreasedSession =
    $this->isScoreDecreased($previousScoreSession, $currentScoreSession);
  $this->isDecreasedIp =
    $this->isScoreDecreased($previousScoreIp, $currentScoreIp);

  // getter for isDecreasedSession and isDecreasedIp
  public function getIsDecreasedSession(): int {
    return $this->isDecreasedSession;
  }

  public function getIsDecreasedIp(): int {
    return $this->isDecreasedIp;
  }


というロジックをUserAgentRiskEvaluatorに追加します。