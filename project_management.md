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
