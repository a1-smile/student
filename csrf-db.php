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
        "/", // パス :サイト内全域で有効
        "", // ドメイン :指定なしで現在のドメイン
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
  session_id hash化
------------------------------------*/
    $session_id = session_id();
    $session_id_hash = hash('sha256', $session_id);
/*------------------------------------
  レート制限用キー生成（4層）
------------------------------------*/
$ip_key        = "ip:"        . $_SERVER['REMOTE_ADDR'];
$session_key   = "sess:"      . $session_id_hash;
$device_key    = "dev:"       . $_COOKIE['device_id'];
$ip_prefix_key = "ip_prefix:" . $ip_prefix;

/*------------------------------------
  閾値設定
 */
const RATE_LIMITS = [
    'ip' => [ 'window' => 300, 'max_failures' => 1000, 'soft_failure' => 300], // 5分で1000回
    'session' => [ 'window' => 300, 'max_failures' => 10, 'soft_failure' => 3], // 5分で10回
    'device' => [ 'window' => 300, 'max_failures' => 30, 'soft_failure' => 10], // 5分で30回
    'ip_prefix' => [ 'window' => 300, 'max_failures' => 5000, 'soft_failure' => 1500], // 5分で5000回
];

const BLOCK_DURATION_SESSION = 1800; // 30分
const BLOCK_DURATION_DEVICE  = 1800; // 30分

/*------------------------------------
  レート制限共通ロジック
------------------------------------*/
/**
 * 失敗時刻とrate key 
 * @param string $key
 * @param PDO $pdo
 * 
 */
// $pdo = $dbm->get_db(); として、
// PDO オブジェクトを取得してから呼び出します。
function record_failure(PDO $pdo, string $key): void
{
    $stmt = $pdo->prepare("INSERT INTO rate_limits (key_name, failed_at) VALUES (?, ?)");
    $stmt->execute([$key, time()]);
}


// $pdo = $dbm->get_db(); として、
// PDO オブジェクトを取得してから呼び出します。

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


//$pdo = $dbm->get_db(); として、
// PDO オブジェクトを取得してから呼び出します。

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
 * ブロック記録（30分など）
*/
function record_block(PDO $pdo, string $key, int $block_duration): void
{
    $blocked_until = time() + $block_duration;
    // REPLACE: 既存 key_name 行を置換
    $stmt = $pdo->prepare("REPLACE INTO rate_blocks (key_name, blocked_until) VALUES (?, ?)");
    $stmt->execute([$key, $blocked_until]);
}

/**
 * ブロック確認
*/
function is_blocked(PDO $pdo, string $key): bool
{
    $stmt = $pdo->prepare("SELECT blocked_until FROM rate_blocks WHERE key_name = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($row && (int)$row['blocked_until'] > time());
}




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
  事前ブロック確認（session / device）
------------------------------------*/
if (is_blocked($pdo, $session_key)) {
    throw CSRFException::fromCurrentRequest(
        'セッションが一時的にブロックされています',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_CRITICAL,
        null
    );
}
if (is_blocked($pdo, $device_key)) {
    throw CSRFException::fromCurrentRequest(
        'デバイスが一時的にブロックされています',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_CRITICAL,
        null
    );
}

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
}elseif ($failure_count 
    >=
    RATE_LIMITS['ip']['soft_failure']) {
    throw CSRFException::fromCurrentRequest(
        'IPアドレスからのリクエストが多めです',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_HIGH,
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
           // 閾値超え → ブロック登録
    record_block($pdo, $session_key, BLOCK_DURATION_SESSION);
    throw CSRFException::fromCurrentRequest(
        'セッションからのリクエストが多すぎます',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_CRITICAL,
        null
    );
}elseif ($failure_count 
        >=
        RATE_LIMITS['session']['soft_failure']) {
    throw CSRFException::fromCurrentRequest(
        'セッションからのリクエストが多めです',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_HIGH,
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
            // 閾値超え → ブロック登録
    record_block($pdo, $device_key, BLOCK_DURATION_DEVICE);
        throw CSRFException::fromCurrentRequest(
            'デバイスからのリクエストが多すぎます',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_CRITICAL,
            null
        );
    }elseif ($failure_count 
    >=
    RATE_LIMITS['device']['soft_failure']) {
        throw CSRFException::fromCurrentRequest(
            'デバイスからのリクエストが多めです',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_HIGH,
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
    }elseif ($failure_count 
    >=
    RATE_LIMITS['ip_prefix']['soft_failure']) {
        throw CSRFException::fromCurrentRequest(
            'IPプレフィックスからのリクエストが多めです',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_HIGH,
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
    unset_token();
    //  security_level を $e から取得
    $security_level = $e->getSecurityLevel();
    if ($security_level === SecurityException::LEVEL_CRITICAL) {

        //ログアウトページへリダイレクト
        http_response_code(403);
        header('Location: blocked.php');
        exit;
    }elseif ($security_level === SecurityException::LEVEL_HIGH) {

        // recaptchaページへリダイレクト
        http_response_code(403);
        header('Location: recaptcha.php');
        exit;
    }
    
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
        
        
        
        throw CSRFException::fromCurrentRequest(
            'CSRFトークン不一致です',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
} catch (CSRFException $e) {
    
    //  unset token
    unset_token();

    // 失敗として4層に記録
    record_failure($pdo, $ip_key);
    record_failure($pdo, $session_key);
    record_failure($pdo, $device_key);
    record_failure($pdo, $ip_prefix_key);

    //エラーページへリダイレクト
    http_response_code(403);
    header('Location: error_page.php');
    exit;
}

/*------------------------------------
  成功したので session_id 再生成
------------------------------------*/
session_regenerate_id(true);

//  unset token
unset_token();

