<?php

/**
 * 
 * このコードは「同一セッション内で UA が変わったら、
 * そのセッションをハイジャック疑いとして潰す」
 * という目的はきちんと果たしています。
 * ここは問題ではありません。
 * セッションを廃棄する以上、first_simple_ua が残らず、
 * 「セッションをまたいで UA を変え続ける攻撃者を追跡できない」
 * のは自然な結果であり、
 * そのこと自体は「脆弱性」とまでは言えません
 * （そもそも UA だけで継続的に攻撃者を特定するのは限界があります）。
 * 他のレイヤでの防御策（IPアドレスチェック、多要素認証、reCAPTCHAなど）と組み合わせて
 * 総合的にセキュリティを確保する設計が重要です。
 * 
 * 
 * 
 * user_agent_check - ユーザーエージェントとアクセス頻度の検証
 *
 * - 初回アクセス時に simple UA をセッションに保存する
 *   ($_SESSION['first_simple_ua'])
 * - セッションID + IPアドレス + simple UA ごとにアクセスログをDBに記録する
 *   テーブル想定: user_agent_logs(
 *       session_id    VARCHAR(...),
 *       ip_address    VARCHAR(...),
 *       simple_ua     VARCHAR(...),
 *       is_ua_mismatch TINYINT(1),
 *       access_time   DATETIME
 *   )
 * - 1分間のアクセス回数および UA 不一致回数に基づきしきい値チェックを行う
 * - UA 不一致時やしきい値超過時には SessionHijackingException をスローする
 *   （呼び出し側でセッション廃棄やエラーページ / reCAPTCHA への誘導を行う）
 * @param PDO $pdo PDOインスタンス
 * @throws SessionHijackingException セッションハイジャックが疑われる場合
 */

function user_agent_check(PDO $pdo): void {
    // UA未送信は警告のみ（他レイヤで防御する方針は維持）

    /* ua が session 継続中に代わるなどの
      異常検出は行うと、セキュリティ強化に寄与する*/

    $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($current_user_agent === '') {
        error_log(sprintf(
            '警告: User-Agentが未送信です - IP:%s, URI:%s, セッションID:%s',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['REQUEST_URI'] ?? 'unknown',
            session_id() ?: 'unknown'
        ));
        return;
    }
//  session_id をハッシュ化して
// $session_id = hash('sha256', session_id() ?: 'unknown');
    $session_id = hash('sha256', session_id() ?: 'unknown');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';


    // 初回アクセス時に simple UA を記録

    //  現在の simple UA を取得
    $current_simple_ua = get_simple_ua($current_user_agent);

    //  first simple UA が未設定なら現在の simple UA を保存
    //  この処理は保険的に行う（本来は設定されているはず）
    if (!isset($_SESSION['first_simple_ua'])) {
        $_SESSION['first_simple_ua'] = $current_simple_ua;
    }
    //  first simple UA を取得
    $first_simple_ua = $_SESSION['first_simple_ua'];

    // UA 不一致判定
    //  ミスマッチなら 1,  マッチなら 0
    $is_ua_mismatch = ($first_simple_ua !== $current_simple_ua) ? 1 : 0;

    // 直近1分間のアクセス数 / UA不一致数の初期化
    $access_count_last_minute   = ['session_id_access_count'=>0, 'ip_address_access_count'=>0];
    $mismatch_count_last_minute = ['session_id_mismatch_count'=>0, 'ip_address_mismatch_count'=>0];

        

    // アクセスログを記録
    ua_log_access($pdo, $session_id, $ip_address, $current_simple_ua, $is_ua_mismatch);

    // 10回に1回古いログを削除（1日より前のデータ）
    ua_maybe_cleanup_old_logs($pdo);

            // 直近1分間のアクセス数 / UA不一致数を取得
    $access_count_last_minute = ua_get_access_count_last_minute($pdo, $session_id, $ip_address);


    $mismatch_count_last_minute = ua_get_mismatch_count_last_minute($pdo, $session_id, $ip_address);

    

    // しきい値
    // 共有 ip アドレス環境を考慮して、
    //  ip アドレスベースのしきい値は
    // セッションIDベースのしきい値よりも緩やかに設定
    // （例: セッションIDベースの10倍）
    $ACCESS_THRESHOLD_ERROR       = ['session'=>30, 'ip'=>300];  // 1分間のアクセス回数でエラー扱い


    //  test 用閾値 テストのみ有効化
    // $ACCESS_THRESHOLD_ERROR       = ['session'=>5, 'ip'=>7];  // 1分間のアクセス回数でエラー扱い


    $ACCESS_THRESHOLD_RECAPTCHA   = ['session'=>60, 'ip'=>600]; // 1分間のアクセス回数で reCAPTCHA へ


    //  test 用閾値 テストのみ有効化
    // $ACCESS_THRESHOLD_RECAPTCHA   = ['session'=>10, 'ip'=>15];


    $MISMATCH_THRESHOLD_RECAPTCHA = ['session'=>5,  'ip'=>50]; // UA不一致回数で reCAPTCHA へ


    //  test 用閾値 テストのみ有効化
    // $MISMATCH_THRESHOLD_RECAPTCHA = ['session'=>2,  'ip'=>3];


    // access が $ACCESS_THRESHOLD_RECAPTCHA を超えた場合は recaptcha.php へリダイレクト
    if ($access_count_last_minute['session_id_access_count'] >= $ACCESS_THRESHOLD_RECAPTCHA['session']) {
        $context = ua_build_context([
            'reason'                    => 'ACCESS_RATE_RECAPTCHA',
            'access_count_last_minute[\'session_id_access_count\']'  => $access_count_last_minute['session_id_access_count'],
            'mismatch_count_last_minute[\'session_id_mismatch_count\']'=> $mismatch_count_last_minute['session_id_mismatch_count'],
            'redirect_target'           => 'recaptcha.php',
        ]);
        $redirect_target = SessionHijackingException::REDIRECT_TARGET_RECAPTCHA;

        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（高頻度アクセス: reCAPTCHA 推奨）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }

    if ($access_count_last_minute['ip_address_access_count'] >= $ACCESS_THRESHOLD_RECAPTCHA['ip']) {
        $context = ua_build_context([
            'reason'                    => 'ACCESS_RATE_RECAPTCHA',
            'access_count_last_minute[\'ip_address_access_count\']'  => $access_count_last_minute['ip_address_access_count'],
            'mismatch_count_last_minute[\'ip_address_mismatch_count\']'=> $mismatch_count_last_minute['ip_address_mismatch_count'],
            'redirect_target'           => 'recaptcha.php',
        ]);

        $redirect_target = SessionHijackingException::REDIRECT_TARGET_RECAPTCHA;
        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（高頻度アクセス: reCAPTCHA 推奨）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }


    // mismatch が $MISMATCH_THRESHOLD_RECAPTCHA を超えた場合は recaptcha.php へリダイレクト
    if ($mismatch_count_last_minute['session_id_mismatch_count'] >= $MISMATCH_THRESHOLD_RECAPTCHA['session']) {
        $context = ua_build_context([
            'reason'                    => 'UA_MISMATCH_RECAPTCHA',
            'access_count_last_minute[\'session_id_access_count\']'  => $access_count_last_minute['session_id_access_count'],
            'mismatch_count_last_minute[\'session_id_mismatch_count\']'=> $mismatch_count_last_minute['session_id_mismatch_count'],
            'redirect_target'           => 'recaptcha.php',
        ]);

        $redirect_target = SessionHijackingException::REDIRECT_TARGET_RECAPTCHA;
        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（UA不一致多発: reCAPTCHA 推奨）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }

    if ($mismatch_count_last_minute['ip_address_mismatch_count'] >= $MISMATCH_THRESHOLD_RECAPTCHA['ip']) {
        $context = ua_build_context([
            'reason'                    => 'UA_MISMATCH_RECAPTCHA',
            'access_count_last_minute[\'ip_address_access_count\']'  => $access_count_last_minute['ip_address_access_count'],
            'mismatch_count_last_minute[\'ip_address_mismatch_count\']'=> $mismatch_count_last_minute['ip_address_mismatch_count'],
            'redirect_target'           => 'recaptcha.php',
        ]);

        $redirect_target = SessionHijackingException::REDIRECT_TARGET_RECAPTCHA;
        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（UA不一致多発: reCAPTCHA 推奨）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }

    

    // 単発の UA 不一致は error_page.php へリダイレクト
    if ($is_ua_mismatch === 1) {
        $context = ua_build_context([
            'reason'          => 'UA_MISMATCH_SINGLE',
            'first_simple_ua' => $first_simple_ua,
            'current_simple_ua'=> $current_simple_ua,
            'redirect_target' => 'error_page.php',
        ]);

        $redirect_target = SessionHijackingException::REDIRECT_TARGET_ERROR_PAGE;
        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（UA不一致）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }

    // access が $ACCESS_THRESHOLD_ERROR を超えた場合は error_page.php へリダイレクト
    if ($access_count_last_minute['session_id_access_count'] >= $ACCESS_THRESHOLD_ERROR['session']) {
        $context = ua_build_context([
            'reason'                    => 'ACCESS_RATE_WARNING',
            'access_count_last_minute[\'session_id_access_count\']'  => $access_count_last_minute['session_id_access_count'],
            'mismatch_count_last_minute[\'session_id_mismatch_count\']'=> $mismatch_count_last_minute['session_id_mismatch_count'],
            'redirect_target'           => 'error_page.php',
        ]);

        $redirect_target = SessionHijackingException::REDIRECT_TARGET_ERROR_PAGE;
        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（高頻度アクセス）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }

    if ($access_count_last_minute['ip_address_access_count'] >= $ACCESS_THRESHOLD_ERROR['ip']) {
        $context = ua_build_context([
            'reason'                    => 'ACCESS_RATE_WARNING',
            'access_count_last_minute[\'ip_address_access_count\']'  => $access_count_last_minute['ip_address_access_count'],
            'mismatch_count_last_minute[\'ip_address_mismatch_count\']'=> $mismatch_count_last_minute['ip_address_mismatch_count'],
            'redirect_target'           => 'error_page.php',
        ]);

        $redirect_target = SessionHijackingException::REDIRECT_TARGET_ERROR_PAGE;
        throw new SessionHijackingException(
            $redirect_target,
            'セッションハイジャックが疑われます（高頻度アクセス）',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }

    // ここまで到達した場合は異常なし
    return;
}

/**
 * UAアクセスログを記録
 */
function ua_log_access(PDO $pdo, string $session_id, string $ip_address, string $simple_ua, int $is_ua_mismatch): void {
    $sql = 'INSERT INTO user_agent_logs (session_id, ip_address, simple_ua, is_ua_mismatch, access_time)
            VALUES (:session_id, :ip_address, :simple_ua, :is_ua_mismatch, NOW())';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':session_id', $session_id, PDO::PARAM_STR);
    $stmt->bindValue(':ip_address', $ip_address, PDO::PARAM_STR);
    $stmt->bindValue(':simple_ua', $simple_ua, PDO::PARAM_STR);
    $stmt->bindValue(':is_ua_mismatch', $is_ua_mismatch, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * 10回に1回、1日より古いログを削除
 */
function ua_maybe_cleanup_old_logs(PDO $pdo): void {
    if (!isset($_SESSION['ua_log_cleanup_counter'])) {
        $_SESSION['ua_log_cleanup_counter'] = 0;
    }
    $_SESSION['ua_log_cleanup_counter']++;

    if ($_SESSION['ua_log_cleanup_counter'] % 10 !== 0) {
        return;
    }

    $sql = 'DELETE FROM user_agent_logs WHERE access_time < (NOW() - INTERVAL 1 DAY)';
    $pdo->exec($sql);
}

/**
 * 直近1分間のアクセス数を取得
 *
 * 戻り値の例:
 * [
 *   'session_id_access_count' => 5,
 *   'ip_address_access_count' => 2,
 * ]
 */
function ua_get_access_count_last_minute(PDO $pdo, string $session_id, string $ip_address): array {
    $sql = "SELECT 
                COUNT(CASE WHEN session_id = :sid THEN 1 END) as session_id_access_count,
                COUNT(CASE WHEN ip_address = :ip THEN 1 END) as ip_address_access_count
            FROM user_agent_logs 
            WHERE access_time >= (NOW() - INTERVAL 1 MINUTE)";

    $stmt = $pdo->prepare($sql);

    // パラメータのバインドと実行
    $stmt->execute([
        ':sid' => $session_id,
        ':ip'  => $ip_address,
    ]);

    // 結果を連想配列として取得
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $access_count_last_minute = $result;
    return $access_count_last_minute;
}

/**
 * 直近1分間の UA 不一致回数を取得
 *
 * 戻り値の例:
 * [
 *   'session_id_mismatch_count' => 3,
 *   'ip_address_mismatch_count' => 1,
 * ]
 */
function ua_get_mismatch_count_last_minute(PDO $pdo, string $session_id, string $ip_address): array {
    $sql = "SELECT 
                COUNT(CASE WHEN session_id = :sid THEN 1 END) as session_id_mismatch_count,
                COUNT(CASE WHEN ip_address = :ip THEN 1 END) as ip_address_mismatch_count
            FROM user_agent_logs 
            WHERE access_time >= (NOW() - INTERVAL 1 MINUTE) AND is_ua_mismatch = 1";

    $stmt = $pdo->prepare($sql);

    // パラメータのバインドと実行
    $stmt->execute([
        ':sid' => $session_id,
        ':ip'  => $ip_address,
    ]);

    // 結果を連想配列として取得
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $mismatch_count_last_minute = $result;
    return $mismatch_count_last_minute;
}

/**
 * 例外コンテキストを構築
 */
function ua_build_context(array $extra = []): array {
    $base = [
        'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent'     => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'referer'        => $_SERVER['HTTP_REFERER'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'request_uri'    => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_time'   => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time()),
        'session_id'     => session_id() ?: 'unknown',
    ];

    return array_merge($base, $extra);
}

