<?php
session_start();

/*------------------------------------
  PDO 接続
------------------------------------*/
// function db()
// {
//     //  static 変数にすると接続を再利用できる
//     //  複数回呼び出されても最初の1回の接続が再利用される
//     static $pdo = null;

//     $access_info = 'mysql:host=localhost;dbname=school;charset=utf8mb4'

//     if ($pdo === null) {
//         $pdo = new PDO(
//             'mysql:host=localhost;dbname=test;charset=utf8mb4',
//             'root',
//             '',
//             [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
//         );
//     }

//     return $pdo;
// }

// DBManager クラスから PDO 接続を取得
require_once __DIR__ . '/db-manager.php';
$dbm = new DBManager();
$pdo = $dbm->get_db();

/*------------------------------------
  device_id クッキー準備（1年有効）
------------------------------------*/
if (empty($_COOKIE['device_id'])) {
    $id = bin2hex(random_bytes(16));
    setcookie(
        'device_id', // 名前
        $id, // 値
        time() + 86400 * 365, // 有効期限（1年後）
        "/", // パス サイト内全域で有効
        "", // ドメイン 指定なしで現在のドメイン
        false, // HTTPS限定か？==> false
        true //  JavaScriptからアクセス不可==> true
    );
    $_COOKIE['device_id'] = $id;
}
/*------------------------------------
  IPプレフィックス取得
------------------------------------*/
// IPプレフィックス取得
$ipv4_blocks = $_ENV['IP_CHECK_IPV4_BLOCKS'] ?? 2;
$ipv6_blocks = $_ENV['IP_CHECK_IPV6_BLOCKS'] ?? 3;

$ip_prefix = get_ip_prefix_for_session($ipv4_blocks, $ipv6_blocks);

/*------------------------------------
  レート制限用キー生成（4層）
------------------------------------*/
$ip_key        = "ip:"        . $_SERVER['REMOTE_ADDR'];
$session_key   = "sess:"      . session_id();
$device_key    = "dev:"       . $_COOKIE['device_id'];
$ip_prefix_key = "ip_prefix:" . $ip_prefix;

/*------------------------------------
  閾値設定
 */
const RATE_LIMITS = [
    'ip' => [ 'window' => 300, 'max_failures' => 1000 ], // 5分で1000回
    'session' => [ 'window' => 300, 'max_failures' => 10 ], // 5分で10回
    'device' => [ 'window' => 300, 'max_failures' => 30 ], // 5分で30回
    'ip_prefix' => [ 'window' => 300, 'max_failures' => 1000 ], // 5分で1000回
];

/*------------------------------------
  レート制限共通ロジック
------------------------------------*/
/**
 * 失敗時刻とrate key 
 * @param string $key
 * @param PDO $pdo
 * 
 */
$pdo = $dbm->get_db();
function record_failure(PDO $pdo, string $key): void
{
    $stmt = $pdo->prepare("INSERT INTO rate_limits (key_name, failed_at) VALUES (?, ?)");
    $stmt->execute([$key, time()]);
}


$pdo = $dbm->get_db();

function get_failures(PDO $pdo, string $key, int $window): array
{
    $stmt = $pdo->prepare("
        SELECT failed_at 
        FROM rate_limits 
        WHERE key_name = ?
        AND failed_at > ?
    ");
    $stmt->execute([$key, time() - $window]);
    //  fetchAll() は全ての行のデータを取得
    //  配列で返す。
    //  つまり、失敗時刻の配列が返る
    //  PDO::FETCH_COLUMN は1列だけ取得するオプション
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}


$pdo = $dbm->get_db();

function clean_old_logs(PDO $pdo): void
{
    // 1時間より前のログは削除
    //  exec() は結果セットを返さないSQLを実行するメソッド
    //  DELETE, UPDATE, INSERT などに使う
    //  1時間 = 3600秒
    $pdo->exec("DELETE FROM rate_limits WHERE failed_at < " . (time() - 3600));
}


/*------------------------------------
  古いログ掃除
------------------------------------*/
clean_old_logs($pdo);

/**
 * 4層レート制限チェック
 * @param string $ip_key
 * @param string $session_key
 * @param string $device_key
 * @param string $ip_prefix_key
 * @param PDO $pdo
 * @throws CSRFException レート制限超過時
 */
function rate_limit_check(
    PDO $pdo,
    string $ip_key, 
    string $session_key,
    string $device_key,
    string $ip_prefix_key): void {
/*------------------------------------
  4層レート制限の実施
------------------------------------*/

// 第1層：IP（5分で100回）
//  5分間の失敗タイムスタンプを配列で取得
$failure_array = get_failures($pdo, $ip_key, RATE_LIMITS['ip']['window']);
//  失敗回数をカウント
$failure_count = count($failure_array);
if ($failure_count 
    >=
    RATE_LIMITS['ip']['max_failures']) {
    throw CSRFException::fromCurrentRequest(
        'IPアドレスからのリクエストが多すぎます',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_CRITICAL,
        null
    );
}

// 第2層：セッションID（5分で10回） ← 本命
//  5分間の失敗タイムスタンプを配列で取得
$failure_array = get_failures($pdo, $session_key, RATE_LIMITS['session']['window']);
//  失敗回数をカウント
$failure_count = count($failure_array);
if ($failure_count 
    >=
    RATE_LIMITS['session']['max_failures']) {
    throw CSRFException::fromCurrentRequest(
        'セッションからのリクエストが多すぎます',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_CRITICAL,
        null
    );
}

// 第3層：device_id（5分で30回）
//  5分間の失敗タイムスタンプを配列で取得
$failure_array = get_failures($pdo, $device_key, RATE_LIMITS['device']['window']);
//  失敗回数をカウント
$failure_count = count($failure_array);
if ($failure_count 
    >=
    RATE_LIMITS['device']['max_failures']) {
        throw CSRFException::fromCurrentRequest(
            'デバイスからのリクエストが多すぎます',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_CRITICAL,
            null
        );
    }
    
    // 第4層：IPプレフィックス（5分で1000回）
    //  5分間の失敗タイムスタンプを配列で取得
    $failure_array = get_failures($pdo, $ip_prefix_key, RATE_LIMITS['ip_prefix']['window']);
    //  失敗回数をカウント
    $failure_count = count($failure_array);
    if ($failure_count 
    >=
    RATE_LIMITS['ip_prefix']['max_failures']) {
        throw CSRFException::fromCurrentRequest(
            'IPプレフィックスからのリクエストが多すぎます',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_CRITICAL,
            null
        );
    }
}

/*------------------------------------
レート制限チェック実行
------------------------------------*/
try {
    rate_limit_check($pdo, $ip_key, $session_key, $device_key, $ip_prefix_key);
} catch (CSRFException $e) {
    // 4つの層で失敗としてカウント
    record_failure($pdo, $ip_key);
    record_failure($pdo, $session_key);
    record_failure($pdo, $device_key);
    record_failure($pdo, $ip_prefix_key);

    //  unset token
    if (isset($_SESSION['csrf_token'])){
        unset($_SESSION['csrf_token']);
    }
    if (isset($_SESSION['csrf_token_time'])){
        unset($_SESSION['csrf_token_time']);
    }
    if (isset($_SESSION['csrf_token'])){
        unset($_SESSION['csrf_token']);
    }
    
    //ログアウトページへリダイレクト
    http_response_code(403);
    header('Location: index.php');
    exit;
}
/*------------------------------------
  CSRF チェック
------------------------------------*/
$token_valid = (
    isset($_POST['csrf_token']) &&
    isset($_SESSION['csrf_token']) &&
    hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
);


try{

    if (!$token_valid) {
        
        // 失敗として4層に記録
        record_failure($ip_key);
        record_failure($session_key);
        record_failure($device_key);
        record_failure($ip_prefix_key);

        //  unset token
        if (isset($_SESSION['csrf_token'])){
            unset($_SESSION['csrf_token']);
        }
        if (isset($_SESSION['csrf_token_time'])){
            unset($_SESSION['csrf_token_time']);
        }
        if (isset($_POST['csrf_token'])){
            unset($_POST['csrf_token']);
        }
        
        throw CSRFException::fromCurrentRequest(
            'CSRFトークン不一致です',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_HIGH,
            null
        );
    }
} catch (CSRFException $e) {
    
    //recaptchaページへリダイレクト
    http_response_code(403);
    header('Location: index.php');
    exit;
}

/*------------------------------------
  成功したので session_id 再生成
------------------------------------*/
session_regenerate_id(true);

//  unset token
if (isset($_SESSION['csrf_token'])){
    unset($_SESSION['csrf_token']);
}
if (isset($_SESSION['csrf_token_time'])){
    unset($_SESSION['csrf_token_time']);
}
if (isset($_SESSION['csrf_token'])){
    unset($_SESSION['csrf_token']);
}

