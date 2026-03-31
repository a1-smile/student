# Project Management Documentation

# UA check
- student/index.php :file_path
  validate_user_agent() :using in it.
    student\validate-u-a.php :file_path

- get_simple_ua()
      student\get_simple_ua.php
      で、UAを簡略化して返す関数を作成する。
      例えば、"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
      というUAがあった場合、"Chrome" と返すようにする。

- interface RequestContent :name of interface
  getUserAgent(): string
  getIpAddress(): string
  getRequestUri(): string


- UaRepository : interface
  public function getScoreSession(): int;
  public function getScoreIp(): int;

  public function getAccessCountSession(): int;
  public function getAccessCountIp(): int;

  public function getIsDecreasedSession(): int;
  public function getIsDecreasedIp(): int;

  public function getIsNoAnomalySession(): int;
  public function getIsNoAnomalyIp(): int;


  saveUaData(RequestContent $requestContent): void





# class UaRepositoryImplementation implements UaRepository

##プロパティ

-serverからの基本情報
    private int $SessionId;
    private int $IpAddress;

-PDO のインスタンスを保持するプロパティ
    private PDO $pdo;

-databaseからの情報
    private int $scoreSession;
    private int $scoreIp;
    private int $isDecreasedSession;
    private int $isDecreasedIp;

-メソッド間でデータを共有するためのプロパティ
    private array $accessCountArray;
    private int $accessCountSession;
    private int $accessCountIp;


#
database の ua_anomaly_events

countNoAnomalyEventsLast10MinutesForTwoSubjects(
  $sessionId,
  $ipAddress
)

[
    'session_anomaly_count' => (int)セッションIDベースの過去10分のレコード数,
    'ip_anomaly_count' => (int)IPアドレスベースの過去10分のレコード数
]

$anomalyCountArray というプロパティをクラス内に定義する。

$this->anomalyCountArray = 
$this->countNoAnomalyEventsLast10MinutesForTwoSubjects($sessionId, $ipAddress);

extractIsNoAnomalySessionFlag($anomalyCountArray){
$sessionAnomalyCount = $anomalyCountArray['session_anomaly_count'];
if ($sessionAnomalyCount > 0) {
    $isNoAnomalySessionFlag = 0;
} elseif ($sessionAnomalyCount === 0) {
    $isNoAnomalySessionFlag = 1;
}
return $isNoAnomalySessionFlag;
}

プロパティ$isNoAnomalySession をクラス内に定義

コンストラクタ内で

$this->isNoAnomalySession = $this->extractIsNoAnomalySessionFlag($this->anomalyCountArray);



