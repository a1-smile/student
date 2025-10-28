<?php
/**
 * 失敗記録の追加
 * @param string $ip クライアントのIPアドレス
 * @param array $rate_data 現在のレート制限データ
 * @return void
 * $rate_data['failures'] に現在時刻を追加
 * $_SESSION[$rate_key] に更新された $rate_data を保存
 */
function recordFailure(string $ip,array $rate_data): void {
    $rate_key = "csrf_attempts_{$ip}";
    $rate_data['failures'][] = time();
    $_SESSION[$rate_key] = $rate_data;
}
/**
 * 成功記録の追加
 * @param string $ip クライアントのIPアドレス
 * @param array $rate_data 現在のレート制限データ
 * @return void
 * $rate_data['successes'] に現在時刻を追加
 * $_SESSION[$rate_key] に更新された $rate_data を保存
 */
function recordSuccess(string $ip,array $rate_data): void {
    $rate_key = "csrf_attempts_{$ip}";
    $rate_data['successes'][] = time();
    $_SESSION[$rate_key] = $rate_data;
}

/**
 * セッション破棄前にセキュリティデータを保存
 * @param string $ip クライアントのIPアドレス
 * @return array $data 保存されたセキュリティデータ
 */
function preserveSecurityData(string $ip): array {
    $rate_key = "csrf_attempts_{$ip}";
    
    $data = $_SESSION[$rate_key] ?? [
        'failures' => [],
        'successes' => [],
        'blocked_until' => 0,
        'session_resets' => 0,
        'last_reset_time' => 0
    ];    
    return $data;
}

/**
 * 新しいセッションにセキュリティデータを復元
 * @param string $ip クライアントのIPアドレス
 * @param array $preserved_data 復元するセキュリティデータ
 */
function restoreSecurityData(string $ip, array $preserved_data): void {
    $rate_key = "csrf_attempts_{$ip}";
    $_SESSION[$rate_key] = $preserved_data;
    
    // セッション破棄の記録も追加
    $_SESSION['security_events'] = [
        'last_session_destroy' => time(),
        'destroy_reason' => 'critical_csrf_attack',
        'destroy_ip' => $ip
    ];
}

/**
 * セッションリセットの記録
 * token検証失敗数と時刻を記録
 * @param string $ip クライアントのIPアドレス
 * @return void
 * $ip に対する $rate_key がなければ何もしない
 * $_SESSION[$rate_key]['session_resets'] をインクリメント
 * $_SESSION[$rate_key]['last_reset_time'] に現在時刻をセット
 */
function recordSessionReset(string $ip): void {
    $rate_key = "csrf_attempts_{$ip}";
    if (!isset($_SESSION[$rate_key])) {
        return;
    }
    
    $_SESSION[$rate_key]['session_resets']++;
    $_SESSION[$rate_key]['last_reset_time'] = time();
}

/**
 * 失敗時のクリーンアップ
 * @param string $failed_token 失敗したCSRFトークン
 * @return void
 * $ip にアクセス元のIPアドレスをセット
 * recordSessionReset() を呼び出しセッションリセットを記録
 * $_SESSION['csrf_token'],
 * $_SESSION['csrf_token_time'],
 * $_POST['csrf_token'] を削除
 * 失敗したトークンを使用済みとして記録
 * sessionが有効なら session_regenerate_id(true)
 * を呼び出しセッションIDを再生成
 */
function cleanupAfterFailure(string $failed_token): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // ✅ 攻撃検知時は即座にトークンを無効化
    if (isset($_SESSION['csrf_token'])) {    
    unset($_SESSION['csrf_token']);
    }
    if (isset($_SESSION['csrf_token_time'])) {
    unset($_SESSION['csrf_token_time']);
    }
    if (isset($_POST['csrf_token'])) {
    unset($_POST['csrf_token']);
    }
    
    // 失敗したトークンも使用済みとして記録（再利用防止）
    if (!empty($failed_token)) {
        if (!isset($_SESSION['used_csrf_tokens'])) {
            $_SESSION['used_csrf_tokens'] = [];
        }
        $_SESSION['used_csrf_tokens'][] = $failed_token;
        if (count($_SESSION['used_csrf_tokens']) > 10) {
            array_shift($_SESSION['used_csrf_tokens']);
        }
    }
}

/** 
 * 成功時のクリーンアップ
 * @param string $used_token 使用されたCSRFトークン
 * @return void
 * $used_token を使用済みトークンとして記録
 * 使用済みトークンの履歴が10件を超えたら古いものを削除
 * $_SESSION['csrf_token'],
 * $_SESSION['csrf_token_time'],
 * $_POST['csrf_token'] を削除
 */
function cleanupAfterSuccess(string $used_token): void {
    // 使用済みトークンとして記録
    if (!isset($_SESSION['used_csrf_tokens'])) {
        $_SESSION['used_csrf_tokens'] = [];
    }
    
    $_SESSION['used_csrf_tokens'][] = $used_token;
    if (count($_SESSION['used_csrf_tokens']) > 10) {
        array_shift($_SESSION['used_csrf_tokens']);
    }
    
    // トークンを削除
    unset($_SESSION['csrf_token']);
    unset($_SESSION['csrf_token_time']);
    unset($_POST['csrf_token']);
}


/**
 * CSRF攻撃の例外処理を条件分岐で実装
 * @param CSRFException $e スローされた例外
 * @param int $severity 攻撃の重大度 ('low', 'high', 'critical')
 * @param string $ip クライアントのIPアドレス
 * @return void
 * 重大度に応じて以下の処理を実行
 * 'critical': セッションを即座に破棄し、IPを1時間ブロック
 * 'high': トークン無効化とセッションID再生成
 * 'low': 標準的なクリーンアップ
 */
function handleCSRFAttack(CSRFException $e, int $severity, string $ip): void {
    switch ($severity) {
        case SecurityException::LEVEL_CRITICAL:
            cleanupAfterFailure($_POST['csrf_token'] ?? '');
            // ✅ セッション破棄前にデータを保存
            recordSessionReset($ip);
            $preserved_data = preserveSecurityData($ip);
            // セッション破棄
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            session_start();
            session_regenerate_id(true);
            
            // ✅ セキュリティデータを新セッションに復元
            restoreSecurityData($ip, $preserved_data);

            error_log("重大CSRF攻撃検知 - IP: {$ip}, セッション破棄実行");
            break;
            
        case SecurityException::LEVEL_HIGH:
            // ✅ セッションID再生成前に記録
            recordSessionReset($ip);
            // トークン無効化
            cleanupAfterFailure($_POST['csrf_token'] ?? '');
                // セッション自体の再生成（強化策）
            if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            }
            error_log("高リスクCSRF攻撃 - IP: {$ip}, セッション再生成");
            break;

        case SecurityException::LEVEL_MEDIUM:
        default:
            // ✅ セッションID再生成前に記録   
            // トークン無効化
            cleanupAfterFailure($_POST['csrf_token'] ?? '');
            error_log("CSRF攻撃検知 - IP: {$ip}, セッション再生成");
            break;
    }
}


// 成功と失敗を分けたレート制限システム
/**
 * レート制限の更新
 * 同一IPからの連続アクセス監視、制限
 * 5分以内で10回以上失敗した場合は30分ブロック
 * copilot-rules.md に従って実装
 * @param string $ip クライアントのIPアドレス
 * @return array 現在の試行データ
 * 失敗回数、成功回数、ブロック期限を含む配列
 * $_SESSIONに保存される
 * @throws CSRFException ブロック中,
 * または10回以上失敗した場合にスロー
 * 
 */
function advancedRateLimit(string $ip) {
    // $ip を受け取り 
    // 配列のキーをcsrf_attempts_{$ip}として定義
    $rate_key = "csrf_attempts_{$ip}";
    // 現在の時刻を取得
    $current_time = time();
    
    //  過去にアクセスがなければ初期化
    if (!isset($_SESSION[$rate_key])) {
        $_SESSION[$rate_key] = [
            'failures'        => [], // 失敗時刻の配列
            'successes'       => [], // 成功時刻の配列  
            'blocked_until'   => 0,  // ブロック期限
            'session_resets'  => 0,  // セッションリセット回数
            'last_reset_time' => 0   // 最後のリセット時刻
        ];
    }
    
    //  アクセス情報を$dataにセット
    $data = $_SESSION[$rate_key];

    // セッションリセットの異常検知
    if ($data['session_resets'] > 5 && ($current_time - $data['last_reset_time']) < 300) {
        // 5分間で5回以上のセッションリセットは異常
        $data['blocked_until'] = $current_time + 7200; // 2時間ブロック
        $_SESSION[$rate_key] = $data;
        recordFailure($ip, $data); // 異常記録として失敗を追加
        $attack_severity = SecurityException::LEVEL_CRITICAL;
        error_log("異常なセッションリセット検知 - IP: {$ip}, 2時間ブロック開始");
        throw CSRFException::fromCurrentRequest(
            "異常な操作検知: 2時間ブロック",
            SecurityException::SEC_CSRF_ATTACK,
            [],
            $attack_severity,
            null
            );
    }



    // ブロック期間中かチェック
    //  ブロック中なら残り時間を計算して例外をスロー
    if ($data['blocked_until'] > $current_time) {
        $attack_severity = SecurityException::LEVEL_HIGH;
        $remaining = $data['blocked_until'] - $current_time;
        recordFailure($ip, $data); // ブロック中のアクセスも失敗として記録
        throw CSRFException::fromCurrentRequest(
            "ブロック中: 残り{$remaining}秒",
            SecurityException::SEC_CSRF_ATTACK,
            [],
            $attack_severity,
            null
            );
    }
    
    // 古い記録を削除（スライディングウィンドウ）
    $window = 300; // 5分間
    //  array_filter()は条件に合う要素だけを残す
    //  配列の中から($current_time - $time) < $window
    //  を満たす要素だけを残す
    //  無名関数(function)は、
    //  配列の各要素を$timeとして引数で受け取る
    //  use()は外部変数を無名関数内で使うための宣言
    //  つまり 5分以内の失敗記録だけを残す
    $data['failures'] = array_filter($data['failures'], function($time) use ($current_time, $window) {
        //  $time は配列の各要素に記録した時刻
        return ($current_time - $time) < $window;
    });

        // ✅ 成功記録のクリーンアップも追加
    $data['successes'] = array_filter($data['successes'], function($time) use ($current_time, $window) {
        return ($current_time - $time) < $window;
    });


    // ✅ 2. 件数制限（最大50件）
    $max_records = 50;
    
    if (count($data['failures']) > $max_records) {
        // 古いものから削除（配列の先頭から削除）
        $data['failures'] = array_slice($data['failures'], -$max_records);
    }
    
    if (count($data['successes']) > $max_records) {
        $data['successes'] = array_slice($data['successes'], -$max_records);
    }
    
    // 失敗回数のチェック

    if (count($data['failures']) >= 10) {
        // 5分以内で10回以上失敗した場合は30分ブロック
        $data['blocked_until'] = $current_time + 1800;
        $_SESSION[$rate_key] = $data;

                // ✅ ログ記録を追加
        error_log("レート制限ブロック開始 - IP: {$ip}, 失敗回数: " . count($data['failures']) . 
                  ", ブロック期限: " . date('Y-m-d H:i:s', $data['blocked_until']));
        $attack_severity = 'high';
        recordFailure($ip, $data); // ブロック記録として失敗を追加
        throw CSRFException::fromCurrentRequest(
            "試行回数上限: 30分間ブロック",
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_HIGH,
            null
        );
    }
    //  $rate_key の配列の情報を返す
    //  失敗情報とブロック状況を更新して
    //  return する
    //  関数外で$_SESSION[$rate_key]は
    //  変更しないのでここで更新
    $_SESSION[$rate_key] = $data;
    return $data;
}

/**
 * CSRFトークンの検証とレート制限
 * function generate_csrf_token() {
 *   $token = bin2hex(random_bytes(32));
 *   $_SESSION['csrf_token'] = $token;
 *   $_SESSION['csrf_token_time'] = time();
 *   return $token;
 * }で生成されたトークンを使用して確認
 * .copilot-rules.md に従って実装
 * @throws CSRFException CSRFが疑われる場合にスロー
 * @return void
 * 
 */
function csrfValidation() : void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $rate_key = "csrf_attempts_{$ip}";
    $rate_data = $_SESSION[$rate_key] ?? [
        'failures'        => [],
        'successes'       => [],
        'blocked_until'   => 0,
        'session_resets'  => 0,
        'last_reset_time' => 0
    ];

    $attack_detected = false;
    $attack_severity = SecurityException::LEVEL_LOW;
    
    try {
    //  sessionにトークンがセットされていなければ
    //  throw CSRFException::fromCurrentRequest(
    //  'CSRFトークンが存在しません');
    if (!isset($_SESSION['csrf_token'])) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'セッションにCSRFトークンが存在しません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    $session_token = $_SESSION['csrf_token'];
    //  sessionにトークンタイムがセットされていなければ
    //  throw CSRFException::fromCurrentRequest(
    //  'CSRFトークンタイムが存在しません');
    if (!isset($_SESSION['csrf_token_time'])) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'CSRFトークンタイムが存在しません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    $token_time = $_SESSION['csrf_token_time'];
    //  トークンタイムが30分以上前なら
    //  throw CSRFException::fromCurrentRequest(
    //  'CSRFトークンの有効期限が切れています');
    if (time() - $token_time > 1800) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'CSRFトークンの有効期限が切れています',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    //  POSTされたトークンがあるか確認
    if (!isset($_POST['csrf_token'])) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'POSTのCSRFトークンが存在しません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    $post_token = $_POST['csrf_token'];

    //  token が文字列か確認
    //  random_bytes()はバイナリデータで
    //  bin2hex()で16進数文字列に変換される
    if (!is_string($post_token)) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'POSTされたCSRFトークンが文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    if (!is_string($session_token)) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'セッションのCSRFトークンが文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    //  token の長さを確認
    //  bin2hex(random_bytes(32)) は64文字の16進数文字列
    //  bin2hex()で64文字になるのは
    //  32バイトのバイナリデータを16進数に変換するため
    //  64文字でなければ不正
    //  strlen()はバイト数を返す
    if (strlen($post_token) !== 64) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'ポストCSRFトークンの長さが不正です',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    if (strlen($session_token) !== 64) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'セッションのCSRFトークンの長さが不正です',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    //  ctype_xdigit()で16進数文字列か確認
    if (!ctype_xdigit($post_token) ) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'POSTされたCSRFトークンが16進数文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    if (!ctype_xdigit($session_token) ) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'セッションのCSRFトークンが16進数文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }

    //  referer を監視します
    //  ここでは処理を中断しません。
    //  補助的にチェックするにとどめます。
        // 5. リファラーチェック（追加のセキュリティ）
    $referer = $_SERVER['HTTP_REFERER'] ?? ''; //  アクセス元のURL
    $host = $_SERVER['HTTP_HOST'] ?? '';       //  アクセス先（さき）のドメイン
    
    //  strpos(文字列,探したい部分文字列) は、部分文字列の位置を検索します。
    //  文字列の一致を探す関数。
    if (empty($referer) || strpos($referer, $host) === false) {
        // 警告レベル（ブロックはしない）
        // 補助的な監視に留めるため、処理を止めない。

        error_log("警告: 不審なリファラー - リファラー: {$referer}, ホスト: {$host}, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }

            // 10. 使用済みトークンチェック（早期チェック）
    if (!isset($_SESSION['used_csrf_tokens'])) {
        $_SESSION['used_csrf_tokens'] = [];
    }
            //  in_array(確認したい要素, 配列, true) は、配列の中に要素が存在するか確認します。
        //  第3引数をtrueにすると型も厳密に比較します
    if (in_array($post_token, $_SESSION['used_csrf_tokens'], true)) {
        $attack_severity = 'low';
        $attack_detected = true;
        recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            '使用済みトークンの再利用',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }



    //  レート制限チェック
    //  同一ipからの連続アクセスを監視
    //  advancedRateLimit()を呼び出し
        //  advancedRateLimit()は
        //  失敗と成功を分けて記録する
        //  失敗が10回以上なら30分ブロック
        //  成功時はカウントするが$rate_dataはリセットしない
        //  呼び出し元で例外処理を行う
        //  $rate_data は現在の試行データの配列
        //  失敗回数、成功回数、ブロック期限を含む
        //  $_SESSIONに保存される
    $rate_data = advancedRateLimit($ip);

    // CSRF検証
    if (!hash_equals($post_token, $session_token)) {
        // ✅ 失敗を記録  一回だけの失敗は低'low'レベル
        $attack_severity = 'low';
        $attack_detected = true;
        //  失敗時間を配列に追加
        recordFailure($ip, $rate_data);      
        throw CSRFException::fromCurrentRequest(
            'トークン不一致',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
            );
    }
    
    // ✅ 成功を記録（失敗カウンターはリセットしない）
    error_log("CSRFトークン検証成功 - IP: {$ip}");
    cleanupAfterSuccess($post_token);
    recordSuccess($ip, $rate_data);

    } catch (CSRFException $e) {
    // ✅ 攻撃レベルに応じたクリーンアップ
    handleCSRFAttack($e, $attack_severity, $ip);
    // 例外を再スロー
    throw $e;
    }
}

// //  使用例
// try {
//     csrfValidation();
//     // CSRF検証成功後の処理をここに記述
// } catch (CSRFException $e) {
//     $security_level = $e->getSecurityLevel();
//     switch ($security_level) {
//         case SecurityException::LEVEL_CRITICAL:
//             // 重大な攻撃の場合の処理
//             header("Location: logout.php");
//             exit;
//         case SecurityException::LEVEL_HIGH:
//             // 高リスクの攻撃の場合の処理
//             header("Location: recapture.php");
//             exit;
//         case SecurityException::LEVEL_MEDIUM:
//         default:
//             // 中程度の攻撃の場合の処理
//             header("Location: error.php");
//             exit;
//     }
// }catch (Exception $e) {
//     // その他の例外処理
//     error_log("予期しないエラー: " . $e->getMessage());
//     header("Location: error.php");
//     exit;
// }
//  以下テストコード
require_once 'exceptions.php';
session_start();