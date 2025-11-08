<?php
// test コード
ob_start();
/**
 * 失敗記録の追加
 * @param string $ip クライアントのIPアドレス
 * @param array $rate_data 現在のレート制限データ
 * @return array $rate_data 更新されたレート制限データ
 * $rate_data['failures'] に現在時刻を追加
 * $_SESSION[$rate_key] に更新された $rate_data を保存
 */
function recordFailure(string $ip,array $rate_data): array {
  echo "<h2>recordFailure 呼び出し</h2>";
    $rate_key = "csrf_attempts_{$ip}";
    $rate_data['failures'][] = time();
  echo '\'failure\' 配列の件数: ' . count($rate_data['failures']) . '<br>';


// ✅ 2. 件数制限（最大50件）
    $max_records = 50;
    
    if (count($rate_data['failures']) > $max_records) {
        // 古いものから削除（配列の先頭から削除）
        $rate_data['failures'] = array_slice($rate_data['failures'], -$max_records);
  echo "'failure' のかずが50個に制限されました<br>";
    }


    $_SESSION[$rate_key] = $rate_data;
    return $rate_data;
}
/**
 * 成功記録の追加
 * @param string $ip クライアントのIPアドレス
 * @param array $rate_data 現在のレート制限データ
 * @return array $rate_data 更新されたレート制限データ
 * $rate_data['successes'] に現在時刻を追加
 * $_SESSION[$rate_key] に更新された $rate_data を保存
 */
function recordSuccess(string $ip,array $rate_data): array {
    $rate_key = "csrf_attempts_{$ip}";
    $rate_data['successes'][] = time();


    // ✅ 2. 件数制限（最大50件）
    $max_records = 50;
        
    if (count($rate_data['successes']) > $max_records) {
        $rate_data['successes'] = array_slice($rate_data['successes'], -$max_records);
    }



    $_SESSION[$rate_key] = $rate_data;
    return $rate_data;
}

/**
 * セッション破棄前にセキュリティデータを保存
 * @param string $ip クライアントのIPアドレス
 * @return array $rate_data 保存されたセキュリティデータ
 */
function preserveSecurityData(string $ip): array {
    $rate_key = "csrf_attempts_{$ip}";

    $rate_data = $_SESSION[$rate_key] ?? [
        'failures' => [],
        'successes' => [],
        'blocked_until' => 0,
        'session_resets' => 0,
        'last_reset_time' => 0
    ];    
    
    // 追加のセキュリティデータも保存
    $preserved_data = [
        'rate_data' => $rate_data,
        'used_csrf_tokens' => $_SESSION['used_csrf_tokens'] ?? [],  
    ];

    return $preserved_data;

}

/**
 * 新しいセッションにセキュリティデータを復元
 * @param string $ip クライアントのIPアドレス
 * @param array $preserved_data 復元するセキュリティデータ
 * @return array $rate_data 更新されたレート制限データ
 */
function restoreSecurityData(string $ip, array $preserved_data): array {
    $rate_key = "csrf_attempts_{$ip}";


    // レート制限データを復元
    if (isset($preserved_data['rate_data'])) {
        $_SESSION[$rate_key] = $preserved_data['rate_data'];
    }

    // 使用済みトークンを復元
    if (isset($preserved_data['used_csrf_tokens'])) {
        $_SESSION['used_csrf_tokens'] = $preserved_data['used_csrf_tokens'];
    }

    // セッション破棄の記録も追加
    $_SESSION['security_events'] = [
        'last_session_destroy' => time(),
        'destroy_reason' => 'csrf_attack',
        'destroy_ip' => $ip
    ];
    $rate_data = $_SESSION[$rate_key] ?? [];
    return $rate_data;
}

/**
 * セッションリセットの記録
 * token検証失敗数と時刻を記録
 * @param string $ip クライアントのIPアドレス
 * @return array $rate_data 更新されたレート制限データ
 * $ip に対する $rate_key がなければ何もしない
 * $_SESSION[$rate_key]['session_resets'] をインクリメント
 * $_SESSION[$rate_key]['last_reset_time'] に現在時刻をセット
 */
function recordSessionReset(string $ip): array {
  echo "<h2>recordSessionReset 呼び出し</h2>";
    $rate_key = "csrf_attempts_{$ip}";
    if (!isset($_SESSION[$rate_key])) {
        return [];
    }

  echo "セッションリセット前の回数: " . $_SESSION[$rate_key]['session_resets'] . "<br>";
    $_SESSION[$rate_key]['session_resets']++;
  echo "セッションリセット後の回数: " . $_SESSION[$rate_key]['session_resets'] . "<br>";
  echo "セッションリセット時間更新前: " . date('Y-m-d H:i:s', $_SESSION[$rate_key]['last_reset_time']) . "<br>";
    $_SESSION[$rate_key]['last_reset_time'] = time();
  echo "セッションリセット時間更新後: " . date('Y-m-d H:i:s', $_SESSION[$rate_key]['last_reset_time']) . "<br>";
    $rate_data = $_SESSION[$rate_key];;
    return $rate_data;
}

/**
 * 失敗時のクリーンアップ
 * @param string $failed_token 失敗したCSRFトークン
 * @return void
 */
function cleanupAfterFailure(string $failed_token): void {
  echo "<h2>cleanupAfterFailure 呼び出し</h2>";
    
    // 失敗したトークンも使用済みとして記録（再利用防止）
    // 型チェックを追加して文字列の場合のみ処理
    if (!empty($failed_token) && is_string($failed_token)) {
        if (!isset($_SESSION['used_csrf_tokens'])) {
            $_SESSION['used_csrf_tokens'] = [];
        }
  echo "追加する前の使用済みトークン数: " . count($_SESSION['used_csrf_tokens']) . "<br>";

        $_SESSION['used_csrf_tokens'][] = $failed_token;
  echo "追加した後の使用済みトークン数: " . count($_SESSION['used_csrf_tokens']) . "<br>";

        if (count($_SESSION['used_csrf_tokens']) > 10) {
            array_shift($_SESSION['used_csrf_tokens']);
        }
  echo "古い使用済みトークンを削除後の数: " . count($_SESSION['used_csrf_tokens']) . "<br>";
    } else {
        echo "無効なトークン形式のため、使用済みトークンリストに追加しませんでした。<br>";
    }
 
    
    // トークンを削除
    if (isset($_SESSION['csrf_token'])) {
        unset($_SESSION['csrf_token']);
    }
    if (isset($_SESSION['csrf_token_time'])) {
        unset($_SESSION['csrf_token_time']);
    }
    if (isset($_POST['csrf_token'])) {
        unset($_POST['csrf_token']);
    }
  echo "セッショントークン：".($_SESSION['csrf_token'] ?? "が削除されました。") . "<br>";
  echo "トークンタイム：".($_SESSION['csrf_token_time'] ?? "が削除されました。") . "<br>";
  echo "ポストトークン：".($_POST['csrf_token'] ?? "が削除されました。") . "<br>";
    // セッションID再生成
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
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
    if (isset($_SESSION['csrf_token'])) {
        unset($_SESSION['csrf_token']);
    }
    
    if (isset($_SESSION['csrf_token_time'])) {
        unset($_SESSION['csrf_token_time']);
    }
    if (isset($_POST['csrf_token'])) {
        unset($_POST['csrf_token']);
    }
    echo "セッショントークン：".($_SESSION['csrf_token'] ?? "が削除されました。") . "<br>";
  echo "トークンタイム：".($_SESSION['csrf_token_time'] ?? "が削除されました。") . "<br>";
  echo "ポストトークン：".($_POST['csrf_token'] ?? "が削除されました。") . "<br>";
}


/**
 * CSRF攻撃の例外処理を条件分岐で実装
 * @param int $severity 攻撃の重大度 (SecurityException::LEVEL_MEDIUM, 'high', 'critical')
 * @param string $ip クライアントのIPアドレス
 * @return void
 * 重大度に応じて以下の処理を実行
 * LEVEL_CRITICAL: セッションを即座に破棄、トークン無効化とセッションID再生成
 * LEVEL_HIGH:     セッションを即座に破棄、トークン無効化とセッションID再生成
 * LEVEL_MEDIUM:   トークン無効化とセッションID再生成
 */
function handleCSRFAttack(int $severity, string $ip): void {
  echo "<h2>handleCSRFAttack 呼び出し </h2>";
  echo '$attack_severity'." = {$severity} を認識しています。<br>";
  echo "IPアドレス: {$ip} を受け取りました。<br>";
    switch ($severity) {
        case SecurityException::LEVEL_CRITICAL:  //  ブロック中にさらに攻撃検知
  echo "クリティカルレベル(4)の攻撃処理を実行します。<br>";

            // 型安全な値を渡す
            $failed_token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) 
                          ? $_POST['csrf_token'] 
                          : '';
            cleanupAfterFailure($failed_token);

            //  リセット回数インクリメント
            //  リセットタイム更新
            $rate_data = recordSessionReset($ip);

            // ✅ セッション破棄前にデータを保存
            $preserved_data = preserveSecurityData($ip);
            // セッション破棄            
            reset_session();
            // ✅ セキュリティデータを新セッションに復元
            $rate_data = restoreSecurityData($ip, $preserved_data);

            error_log("重大CSRF攻撃検知 - IP: {$ip}, セッション破棄実行");
            break;
            
        case SecurityException::LEVEL_HIGH:  //  連続失敗
  echo "ハイレベル(3)の攻撃処理を実行します。<br>";
            // 型安全な値を渡す
            $failed_token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) 
                          ? $_POST['csrf_token'] 
                          : '';
            cleanupAfterFailure($failed_token);
            $rate_data = recordSessionReset($ip);
            // ✅ セッション破棄前にデータを保存
            $preserved_data = preserveSecurityData($ip);

  echo  '保存された値$preserved_data:<br>' . htmlspecialchars(print_r($preserved_data, true)) . '<br>';
  echo  '$_SESSION[$rate_key] の値:<br>' . htmlspecialchars(print_r($_SESSION["csrf_attempts_{$ip}"] ?? [], true)) . '<br>';



            // セッション破棄            
            reset_session();

  echo  '廃棄後の$_SESSION[$rate_key] の値:<br>' . htmlspecialchars(print_r($_SESSION["csrf_attempts_{$ip}"] ?? [], true)) . '<br>';


            // ✅ セキュリティデータを新セッションに復元
            $rate_data = restoreSecurityData($ip, $preserved_data);

  echo  '復元後の$_SESSION[$rate_key] の値:<br>' . htmlspecialchars(print_r($_SESSION["csrf_attempts_{$ip}"] ?? [], true)) . '<br>';
  echo  '復元後の値$rate_data:<br>' . htmlspecialchars(print_r($rate_data, true)) . '<br>';


            error_log("高リスクCSRF攻撃 - IP: {$ip}, セッション破棄実行");
            break;

        case SecurityException::LEVEL_MEDIUM:
  echo "ミディアムレベル(2)の攻撃処理を実行します。<br>";
            // 型安全な値を渡す
            $failed_token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) 
                          ? $_POST['csrf_token'] 
                          : '';
            cleanupAfterFailure($failed_token);
            error_log("中リスクCSRF攻撃 - IP: {$ip}");
            break;
        default:
  echo "デフォルト処理を実行します。 想定外の処理です。<br>";  
            // 型安全な値を渡す
            $failed_token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) 
                          ? $_POST['csrf_token'] 
                          : '';
            cleanupAfterFailure($failed_token);
            error_log("CSRF攻撃検知 - IP: {$ip}");
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

//  echo "<h2>advancedRateLimit 呼び出し - IP: {$ip}</h2>";
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

    //  アクセス情報を$rate_dataにセット
    $rate_data = $_SESSION[$rate_key];

    // セッションリセットの異常検知
    if ($rate_data['session_resets'] > 5 && ($current_time - $rate_data['last_reset_time']) < 300) {
        // 5分間隔でアクセスがあり、5回以上のセッションリセットは異常
        echo "<h2>6回以上のセッションリセット検知</h2>";
        $rate_data['blocked_until'] = $current_time + 7200; // 2時間ブロック
        echo "<h2>2時間ブロック設定</h2>";
        $rate_data = recordFailure($ip, $rate_data); // 異常記録として失敗を追加
  echo '$rate_data[\'blocked_until\'] = ' . $rate_data['blocked_until'] . '<br>';
  echo '$rate_data[\'last_reset_time\'] = ' . $rate_data['last_reset_time'] . '<br>';

        $attack_severity = SecurityException::LEVEL_CRITICAL;
    echo "<h2>セキュリティレベル: クリティカル{$attack_severity}</h2>";
        error_log("異常なセッションリセット検知 - IP: {$ip}, 2時間ブロック開始");
  echo 'レート制限で異常検知例外スローします。<br>';
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
    if ($rate_data['blocked_until'] > $current_time) {
        //  ブロック中のアクセスはセキュリティーレベルHIGHとして扱う
        echo "<h2>ブロック中のアクセス検知</h2>";
        $attack_severity = SecurityException::LEVEL_HIGH;
        $remaining = $rate_data['blocked_until'] - $current_time;
        $rate_data = recordFailure($ip, $rate_data); // ブロック中のアクセスも失敗として記録
      echo 'レート制限でブロック中例外スローします。<br>';
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
    $rate_data['failures'] = array_filter($rate_data['failures'], function($time) use ($current_time, $window) {
        //  $time は配列の各要素に記録した時刻
        return ($current_time - $time) < $window;
    });

        // ✅ 成功記録のクリーンアップも追加
    $rate_data['successes'] = array_filter($rate_data['successes'], function($time) use ($current_time, $window) {
        return ($current_time - $time) < $window;
    });

    // 失敗回数のチェック

    if (count($rate_data['failures']) >= 10) {
        echo "<h2>レート制限: 5分以内に10回以上の失敗検知</h2>";
        // 5分以内で10回以上失敗した場合は30分ブロック
        $rate_data['blocked_until'] = $current_time + 1800;
      echo '$rate_data[\'blocked_until\'] = ' . $rate_data['blocked_until'] . '<br>';
        $_SESSION[$rate_key] = $rate_data;

                // ✅ ログ記録を追加
        error_log("レート制限ブロック開始 - IP: {$ip}, 失敗回数: " . count($rate_data['failures']) . 
                  ", ブロック期限: " . date('Y-m-d H:i:s', $rate_data['blocked_until']));
        $attack_severity = SecurityException::LEVEL_HIGH;
        $rate_data = recordFailure($ip, $rate_data); // ブロック記録として失敗を追加
      echo 'レート制限でブロック例外スローします。<br>';
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
    $_SESSION[$rate_key] = $rate_data;
    return $rate_data;
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

     $attack_severity = SecurityException::LEVEL_LOW;

    try {
    //  sessionにトークンがセットされていなければ
    //  throw CSRFException::fromCurrentRequest(
    //  'CSRFトークンが存在しません');
    if (!isset($_SESSION['csrf_token'])) {
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        $rate_data = recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'セッションにCSRFトークンが存在しません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            $attack_severity,
            null
        );
    }
    $session_token = $_SESSION['csrf_token'];
    //  sessionにトークンタイムがセットされていなければ
    //  throw CSRFException::fromCurrentRequest(
    //  'CSRFトークンタイムが存在しません');
    if (!isset($_SESSION['csrf_token_time'])) {
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        $attack_detected = true;
        $rate_data = recordFailure($ip, $rate_data);
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
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        $attack_detected = true;
        $rate_data = recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'CSRFトークンの有効期限が切れています',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            $attack_severity,
            null
        );
    }

    //  POSTされたトークンがあるか確認
    if (!isset($_POST['csrf_token'])) {
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        $rate_data = recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'POSTのCSRFトークンが存在しません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            $attack_severity,
            null
        );
    }
    $post_token = $_POST['csrf_token'];

    //  token が文字列か確認
    //  random_bytes()はバイナリデータで
    //  bin2hex()で16進数文字列に変換される
    if (!is_string($post_token)) {
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        echo '$attack_severity = ' . $attack_severity . '<br>';
        echo 'SecurityException::LEVEL_MEDIUM = ' . SecurityException::LEVEL_MEDIUM . '<br>';
        $rate_data = recordFailure($ip, $rate_data);
        echo '代入して関数を呼び出します。<br>';
        $message = 'POSTされたCSRFトークンが文字列ではありません';
        throw CSRFException::fromCurrentRequest(
            $message,
            SecurityException::SEC_CSRF_ATTACK,
            [],
            $attack_severity,
            null
        );
    }

    if (!is_string($session_token)) {
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        $rate_data = recordFailure($ip, $rate_data);
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
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        $rate_data = recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            'ポストCSRFトークンの長さが不正です',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    if (strlen($session_token) !== 64) {
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        $rate_data = recordFailure($ip, $rate_data);
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
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        $rate_data = recordFailure($ip, $rate_data);
        echo 'post_token に不正な文字が含まれています。<br>';
        throw CSRFException::fromCurrentRequest(
            'POSTされたCSRFトークンが16進数文字列ではありません',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
    if (!ctype_xdigit($session_token) ) {
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        $rate_data = recordFailure($ip, $rate_data);
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
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        $attack_detected = true;
        $rate_data = recordFailure($ip, $rate_data);
        throw CSRFException::fromCurrentRequest(
            '使用済みトークンの再利用',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }


  echo "<h2>token の型式チェック終了</h2>";
  echo "<h2>レート制限チェック開始</h2>";



    //  レート制限チェック
    //  同一ipからの連続アクセスを監視
    //  advancedRateLimit()を呼び出し
        //  advancedRateLimit()は
        //  5分間隔以内でアクセスがあり、sessionリセットが
        //  6回以上なら2時間ブロック、クリティカルレベル設定

        //  失敗が10回以上なら30分ブロック
        //  ハイレベル設定

        //  呼び出し元で例外処理を行う
        //  $rate_data は現在の試行データの配列
        //  失敗回数、成功回数、ブロック期限を含む
        //  $_SESSIONに保存される
    try {
        $rate_data = advancedRateLimit($ip);
    } catch (CSRFException $e) {
        //  例外が発生した場合は攻撃を検知
      echo "<h2>レート制限で例外発生をキャッチ</h2>";
      echo 'レート制限での例外を再スローします。<br>';
        //  ここでは再スローします
        throw $e;
    }

    // CSRF検証
    if (!hash_equals($post_token, $session_token)) {
      echo "<h2>CSRFトークン不一致検知</h2>";
        // ✅ 失敗を記録  一回だけの失敗はSecurityException::LEVEL_MEDIUMレベル
        $attack_severity = SecurityException::LEVEL_MEDIUM;
        //  失敗時間を配列に追加
        $rate_data = recordFailure($ip, $rate_data);
  echo 'CSRFトークン不一致例外スローします。<br>';   
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
    $rate_data = recordSuccess($ip, $rate_data);

    } catch (CSRFException $e) {
        // スコープの外にある場合もあるので$attack_severity を取得
        $attack_severity = $e->getSecurityLevel();
  echo "</h2>関数内でCSRF例外キャッチ</h2>";  
    // ✅ 攻撃レベルに応じたクリーンアップ
    handleCSRFAttack($attack_severity, $ip);
    // 例外を再スロー
  echo '関数内でキャッチした例外を再スローします。<br>';
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
//  'safe-path.php' の絶対パスを取得して読み込み
$pass = __DIR__ . '/safe-path.php';
require_once $pass;

$safe_pass = safe_file_path('common.php');
try{
require_once $safe_pass;
} catch (Exception $e) {
    die("安全なパスの取得に失敗しました: " . $e->getMessage());
}
session_start();
reset_session();


for ($i = 0; $i < 53; $i++) {
    echo "<h1>トークンが一致する場合{$i}</h1>";

    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $_SESSION['csrf_token']; // 正しいトークンに変更してテストも可能
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
try {
    csrfValidation();
    // CSRF検証成功後の処理をここに記述
} catch (CSRFException $e) {
    echo '再スローキャッチしました<br>';
    $security_level = $e->getSecurityLevel();
    echo 'セキュリティーレベルを表示します: ' . $security_level . "<br>";
    echo 'message: ' . $e->getMessage() . "<br>";
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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


for ($i = 0; $i < 1; $i++) {
    echo "<h1>session にトークンがセットされていない場合{$i}</h1>";

    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $_SESSION['csrf_token']; // 正しいトークンに変更してテストも可能
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
    unset($_SESSION['csrf_token']); // セッションからトークンを削除してテスト
try {
    csrfValidation();
    // CSRF検証成功後の処理をここに記述
} catch (CSRFException $e) {
    echo '再スローキャッチしました<br>';
    $security_level = $e->getSecurityLevel();
    echo 'セキュリティーレベルを表示します: ' . $security_level . "<br>";
    echo 'message: ' . $e->getMessage() . "<br>";
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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



for ($i = 0; $i < 1; $i++) {
    echo "<h1>session にtoken time がセットされていない場合{$i}</h1>";

    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $_SESSION['csrf_token']; // 正しいトークンに変更してテストも可能
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
    unset($_SESSION['csrf_token_time']); // セッションからトークンタイムを削除してテスト
try {
    csrfValidation();
    // CSRF検証成功後の処理をここに記述
} catch (CSRFException $e) {
    echo '呼び出しもとで、再スローキャッチしました<br>';
    $security_level = $e->getSecurityLevel();
    echo 'セキュリティーレベルを表示します: ' . $security_level . "<br>";
    echo 'message: ' . $e->getMessage() . "<br>";
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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

for ($i = 0; $i < 1; $i++) {
    echo "<h1>token time が time out した場合{$i}</h1>";

    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $_SESSION['csrf_token']; // 正しいトークンに変更してテストも可能
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
    $_SESSION['csrf_token_time'] -= 1900; // セッションのトークンタイムを30分以上前に変更してテスト
try {
    csrfValidation();
    // CSRF検証成功後の処理をここに記述
} catch (CSRFException $e) {
    echo '呼び出しもとで、再スローキャッチしました<br>';
    $security_level = $e->getSecurityLevel();
    echo 'セキュリティーレベルを表示します: ' . $security_level . "<br>";
    echo 'message: ' . $e->getMessage() . "<br>";
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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

for ($i = 0; $i < 1; $i++) {
    echo "<h1>session にtoken time がセットされていない場合その2回目{$i}</h1>";

    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $_SESSION['csrf_token']; // 正しいトークンに変更してテストも可能
    unset($_SESSION['csrf_token_time']); // セッションからトークンタイムを削除してテスト
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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

$post_token_array = [];
//  複数のトークンを生成して配列に保存
//   文字列ではない不正なトークンを $post_token_array に追加
$post_token_array[] = 12345; // 整数
$post_token_array[] = null; // null
$post_token_array[] = [1,2,3]; // 配列


foreach ($post_token_array as $i => $invalid_token) {
    echo "<h1>post token が文字列でない場合{$i}</h1>";

    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $invalid_token; // 正しいトークンに変更してテストも可能
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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


foreach ($post_token_array as $i => $invalid_token) {
    echo "<h1>SESSION token が文字列でない場合{$i}</h1>";

    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $_SESSION['csrf_token'];
    $_SESSION['csrf_token'] = $invalid_token; // 正しいトークンに変更してテストも可能
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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

$token_not_64_array = [];
// strlen()が64ではない不正なトークンを追加
$token_not_64_array[] = 'short_token_123'; // 16文字（短すぎる）
$token_not_64_array[] = 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890extra'; // 69文字（長すぎる）
$token_not_64_array[] = ''; // 空文字列（0文字）




foreach ($token_not_64_array as $i => $invalid_token) {
    echo "<h1>post token が64文字でない場合{$i}</h1>";

    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $invalid_token; // 正しいトークンに変更してテストも可能
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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



foreach ($token_not_64_array as $i => $invalid_token) {
    echo "<h1>session token が64文字でない場合{$i}</h1>";
    $length = strlen($invalid_token);
    echo "invalid_token の長さ: {$length}<br>";
    var_dump($length);
    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $_SESSION['csrf_token'] ?? ''; // 正しいトークンに変更してテストも可能
    $_SESSION['csrf_token'] = $invalid_token;
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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


for ($i = 0; $i < 1; $i++) {
    echo "<h1>post にtoken time がセットされていない場合{$i}</h1>";

    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $_SESSION['csrf_token']; // 正しいトークンに変更してテストも可能
    unset($_POST['csrf_token']); // セッションからトークンタイムを削除してテスト
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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


$invalid_hex_tokens = [
    "abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRSTUV", // g-z含む
    "123456789012345678901234567890123456789012345678901234567890XYZW", // X,Y,Z,W含む
    "abcdef1234567890!@#$%^&*()_+-=[]{}|;:,.<>?/~`abcdef123456", // 記号含む
    "0123456789abcdefABCDEF0123456789abcdefABCDEF0123456789abcdefGH" // G,H含む
];

foreach ($invalid_hex_tokens as $i => $invalid_token) {
    echo "<h1>session token が16進数文字列でない場合{$i}</h1>";
    $length = strlen($invalid_token);
    echo "invalid_token の長さ: {$length}<br>";
    var_dump($length);
    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $_SESSION['csrf_token'] ?? ''; // 正しいトークンに変更してテストも可能
    $_SESSION['csrf_token'] = $invalid_token;
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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


$used_token_array = $_SESSION['used_csrf_tokens'] ?? [];

foreach ($used_token_array as $i => $invalid_token) {
    echo "<h1>post token が使用済みトークンの場合{$i}</h1>";

    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $_SESSION['csrf_token'] ?? ''; // 正しいトークンに変更してテストも可能
    $_POST['csrf_token'] = $invalid_token; // 使用済みトークンをセット
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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



for ($i = 0; $i < 1; $i++) {
    echo "<h1>すでに10回以上失敗の場合{$i}</h1>";

    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $_SESSION['csrf_token']; // 正しいトークンに変更してテストも可能
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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


for ($i = 0; $i < 1; $i++) {
    echo "<h1>ブロック中にアクセス試行: {$i}</h1>";

    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $_SESSION['csrf_token']; // 正しいトークンに変更してテストも可能
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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

for ($i = 0; $i < 20; $i++) {
    echo "<h1>リセットが6回以上の場合{$i}</h1>";

    generate_csrf_token();
    echo 'CSRFトークンを生成しました。<br>';
    //$_POST['csrf_token'] = 'invalid_token_for_test_purpose_only_1234567890abcdef';
    $_POST['csrf_token'] = $_SESSION['csrf_token']; // 正しいトークンに変更してテストも可能
    // generate_csrf_token(); // トークンを再生成して古いトークンを無効化
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
echo 'last_reset_time の値を表示: ';
if ($rate_data !== null) {
    echo $rate_data['last_reset_time'];
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




