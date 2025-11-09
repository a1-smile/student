<?php
ob_start();
require_once 'exceptions.php';
require_once 'generate-token.php';
require_once 'csrf.php';
require_once 'session-reset.php';

session_start();
reset_session();

date_default_timezone_set('Asia/Tokyo');

echo '=== CSRF 攻撃シミュレーションテスト 3 ===<br>';

for ($i = 0; $i < 20; $i++) {

echo '<h1>=== 試行回数: ' . ($i + 1) . " ===</h1>";


generate_csrf_token();

$attack_token = bin2hex(random_bytes(32));

$_POST['csrf_token'] = $attack_token;
try {
    csrfValidation();
    // CSRF検証成功後の処理をここに記述
} catch (CSRFException $e) {
    echo '呼び出しもとで、再スローキャッチしました<br>';
    $security_level = $e->getSecurityLevel();
    echo 'message: ' . $e->getMessage() . "<br>";
    echo 'セキュリティーレベルを表示します: ' . $security_level . "<br>";
    switch ($security_level) {
        case SecurityException::LEVEL_CRITICAL:
            // 重大な攻撃の場合の処理
            echo "LEVEL_CRITICAL:4 クリティカルな攻撃が検出されました。";
            break;
        case SecurityException::LEVEL_HIGH:
            // 高リスクの攻撃の場合の処理
            echo "LEVEL_HIGH:3 高リスクの攻撃が検出されました。";
            break;
        case SecurityException::LEVEL_MEDIUM:
        default:
            // 中程度の攻撃の場合の処理
            echo "LEVEL_MEDIUM:2 中程度の攻撃が検出されました<br>";
        
    }
}catch (Exception $e) {
    // その他の例外処理
    error_log("予期しないエラー: " . $e->getMessage());
    echo "予期しないエラーが発生しました。";
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_key = "csrf_attempts_{$ip}";
$rate_data = $_SESSION[$rate_key] ?? null;

echo 'failure の数を表示: ';
if ($rate_data !== null) {
    echo count($rate_data['failures']);
} else {
    echo 'No data';
}
echo "<br>";
echo 'success の数を表示: ';
if ($rate_data !== null) {
    echo count($rate_data['successes']);
} else {
    echo 'No data';
}
echo "<br>";
echo 'blocked_until の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['blocked_until'];
} else {
    echo 'No data';
}
echo "<br>";
echo 'session_resets の数を表示: ';
if ($rate_data !== null) {
    echo $rate_data['session_resets'];
} else {
    echo 'No data';
}   
echo "<br>";
echo '<h2>last_reset_time の値を表示: </h2>';
echo '<br>';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
    echo "<br>";
    $reset_time = $rate_data['last_reset_time'];
    echo " (" . date('Y-m-d H:i:s', $reset_time) . ")";
    echo "<br>";
} else {
    echo 'No data';
}
echo "<br>";
echo 'used_csrf_tokens の数を表示: ';
if (isset($_SESSION['used_csrf_tokens'])) {
    echo count($_SESSION['used_csrf_tokens']);
} else {
    echo 'No data';
} 
echo "<br>";

echo "<hr>";


}





ob_end_flush();