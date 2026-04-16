#student アプリケーションのセキュリティー部分における
ua check モジュールでは、

interface_request_content.php
に定義した、
interface RequestContent
の実装によって、
$sessionId と $ipAddress などの
サーバーからの基本情報を取得します。

interface_ua_repository.php
に定義した、
interface UaRepository
の実装によって、DB から
$isNoAnomalySession や $isNoAnomalyIp などの
過去のアクセス情報を取得します。

user_agent_risk_evaluator.php
に定義されている
class UserAgentRiskEvaluator
において、
interface RequestContentの実装と
interface UaRepositoryの実装から
データを取得して、
アクセス元のセキュリティリスクを計算します。

その後、
計算結果をDBに保存し、
計算結果によって、その後の処理を分岐させます。

DB から値を取得する、インターフェイスの実装
class UaRepositoryImplementation implements UaRepository 
のメソッドである
private function countAnomalyLast10MinFor2(PDO $pdo, string $sessionId, string $ipAddress)
は、DB処理の効率化のためにコメントアウトして、
リファクタリングします。

このメソッドは、
session id と ip address の両方に対して、
過去10分間の異常イベントの数をカウントするためのものです。
このメソッドの戻り値をもとに、
過去10分間に異常イベントがあったかを表すフラグ
$isNoAnomalySession と $isNoAnomalyIp を確定させます。

この処理に必要なDBのテーブルは、
ua_anomaly_events というテーブルで、
テーブル構造は以下の通りです。
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

このテーブルにたいして、
$sql = "SELECT 
            COUNT(CASE WHEN session_id = :sessionId THEN 1 END) as session_anomaly_count,
            COUNT(CASE WHEN ip_address = :ipAddress THEN 1 END) as ip_anomaly_count
            
        FROM ua_anomaly_events WHERE access_time >= (NOW() - INTERVAL 10 MINUTE)";
というクエリは、インデックスを適切に活用していません。
インデックスを活用するためには、
クエリを以下のように変更する必要があります。

SELECT COUNT(*) as session_anomaly_count
FROM ua_anomaly_events 
WHERE session_id = :sessionId AND access_time >= (NOW() - INTERVAL 10 MINUTE);

SELECT COUNT(*) as ip_anomaly_count
FROM ua_anomaly_events 
WHERE ip_address = :ipAddress AND access_time >= (NOW() - INTERVAL 10 MINUTE);
このようにクエリを分割することで、インデックスが効果的に利用され、クエリのパフォーマンスが向上します。
さらに、これらのクエリを一度に実行するために、
以下のように UNION ALL を使用して結合することもできます。
SELECT 'session' as type, COUNT(*) as anomaly_count
FROM ua_anomaly_events 
WHERE session_id = :sessionId AND access_time >= (NOW() - INTERVAL 10 MINUTE)
UNION ALL
SELECT 'ip' as type, COUNT(*) as anomaly_count
FROM ua_anomaly_events 
WHERE ip_address = :ipAddress AND access_time >= (NOW() - INTERVAL 10 MINUTE);

このクエリは、
セッションIDとIPアドレスの両方の異常イベント数を
効率的に取得することができます。

このクエリを実行して、
fetchAll すると、以下のような結果が得られます。
$resultArray = [
    ['type' => 'session', 'anomaly_count' => '5'],
    ['type' => 'ip', 'anomaly_count' => '3']
]; 

$resultArrayを加工するために、
$anomalyCountArray = [];
という配列を定義して、
以下のforeach loop 処理を実行します。
foreach ($resultArray as $row) {
    if ($row['type'] === 'session') {
        $anomalyCountArray['session'] = (int)$row['anomaly_count'];
    } elseif ($row['type'] === 'ip') {
        $anomalyCountArray['ip'] = (int)$row['anomaly_count'];
    }
}

$anomalyCountArray = [
    'session' => $sessionAnomalyCount,
    'ip' => $ipAnomalyCount
];

この、$anomalyCountArray

を return
する処理を
ひとつのメソッドにまとめ、
private function countAnomalyLast10MinFor2(PDO $pdo, string $sessionId, string $ipAddress): array
とします。

戻り値を
プロパティ
$anomalyCountArray
に代入します。
（配列の中身の要素の値の型はstringです。）


function makeNoAnomalyFlagArray(array $anomalyCountArray): array
{
    $isNoAnomalyFlagArray = [
        'session' => $anomalyCountArray['session'] > 0 ? 0 : 1,
        'ip' => $anomalyCountArray['ip'] > 0 ? 0 : 1
    ];
    return $isNoAnomalyFlagArray;
}
という処理をします。

三項演算子を使用しなければ、
以下のように記述することもできます。
$sessionAnomalyCount =
 $anomalyCountArray['session'];

$ipAnomalyCount =
 $anomalyCountArray['ip'];

if ($sessionAnomalyCount > 0) {
    $isNoAnomalySession = 0;
} else {
    $isNoAnomalySession = 1;
}
if ($ipAnomalyCount > 0) {
    $isNoAnomalyIp = 0;
} else {
    $isNoAnomalyIp = 1;
}

$isNoAnomalyFlagArray = [
    'session' => $isNoAnomalySession,
    'ip' => $isNoAnomalyIp
];

return $isNoAnomalyFlagArray;


コンストラクタ内で、値を受け取って、プロパティに代入します。
$this->isNoAnomalySession = 
$this->isNoAnomalyFlagArray['session'];

$this->isNoAnomalyIp =
$this->isNoAnomalyFlagArray['ip'];


このようなロジックで、
ua_repository_implementation.php
に記述している
class UaRepositoryImplementation implements UaRepository 
のプロパティ
$isNoAnomalySession と $isNoAnomalyIp
を確定させます。

という予定ですが、
ひとまずは、
private function countAnomalyLast10MinFor2(PDO $pdo, string $sessionId, string $ipAddress)
を実装し、
テストコードも書いてみます。
student_test\countAnomalyLast10MinFor2test.php
にテストコードを記述します。
function countAnomalyLast10MinFor2Test()
のテストコードを実行した結果、ブラウザの表示が
以下になります。
テストコードと
function countAnomalyLast10MinFor2Test()
の改善点を、指摘してください。



接続状態:

expected: true
bool(true)

テスト開始

データベースに接続できました。

var_dump
array(2) { ["session"]=> int(4) ["ip"]=> int(2) }

print_r
Array ( [session] => 4 [ip] => 2 ) expected: $resultArray["session"] = 4, $resultArray["ip"] = 2
$resultArray["session"]: 4
$resultArray["ip"]: 2

エッジケースのテストが不足しています。

異常イベントが 0件 の場合（テーブルが空、またはマッチしないID）
session_id は一致するが ip_address は一致しない場合（逆も）
TRUNCATE 直後に何も INSERT せずに呼び出す場合


