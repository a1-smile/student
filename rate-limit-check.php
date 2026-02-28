<?php

// 攻撃検知時のリダイレクト先に関する
// 考え方は以下のように整理できます。

//  soft_failure を超えたらSecurityException::LEVEL_MEDIUM
// max_failures を超えたら
// SecurityException::LEVEL_HIGH
// さらに攻撃が来たら、
// SecurityException::LEVEL_CRITICAL
// として、
// 例外をなげて、
// CATCH BLOCK で
// SecurityException::LEVEL_MEDIUM
// で、リキャプチャ
// SecurityException::LEVEL_HIGH
// で、１回session廃棄、ログアウト
// SecurityException::LEVEL_CRITICAL
// で、ログアウト。
// 

// 方針そのものはとても良いです。
// ただし「どのタイミングを HIGH／CRITICAL とみなすか」
// を少し整理すると、より安全で分かりやすくなります。

// 1. レベル設計の方向性

// あなたの案：

// soft_failure 超え → LEVEL_MEDIUM → reCAPTCHA
// max_failures 超え → LEVEL_HIGH → 1回だけ session 廃棄＋ログアウト
// さらに攻撃 → LEVEL_CRITICAL → ログアウト
// この「段階的に強くする」考え方はとても良いです。

// 2. 実装上もう少し整理したい点

// 特に重要なのは「さらに攻撃が来たら」の扱いです。

// いまの rate_limit_check にはすでに：

// is_blocked($pdo, $session_key) / is_blocked($pdo, $device_key)
// → ここで LEVEL_CRITICAL を投げている
// max_failures 超えのとき
// → record_block() して LEVEL_CRITICAL を投げている
// という構造があります。

// これをあなたの案に合わせるなら、ざっくりこう整理するときれいです：

// soft_failure 超え（まだブロック登録はしない）
// → SecurityException::LEVEL_MEDIUM（＝再試行は許可、reCAPTCHA など）
// max_failures 超え（初めて「本格ブロック」に入るタイミング）
// → SecurityException::LEVEL_HIGH
// → record_block() して「このキーを一定時間ブロック」
// → catch 側で この1回だけ セッション廃棄＋ログアウト
// すでに is_blocked(...) でブロック中なのにさらにリクエスト
// → SecurityException::LEVEL_CRITICAL
// → catch 側では セッションをいじらず、ブロック／ログアウト済みページを出すだけ
// こうしておくと：

// セッション廃棄は HIGH に上がったタイミングの1回だけ
// その後 CRITICAL でいくら攻撃されても
// 「セッション開始も再生成もしない＋単純な block_page だけ返す」
// ので、セッション廃棄ループやDoS化を避けられます。
// 3. catch ブロック側の役割

// レベルごとの処理イメージ：

// LEVEL_MEDIUM（soft_failure 超え）
// セッション維持
// 「リクエストが多めです／reCAPTCHA を解いてください」ページへ
// CSRF トークンは新しく発行して次のフォームで使用


// LEVEL_HIGH（max_failures 到達直後のみ）
// record_block() 済み
// 1回だけ session_unset() / session_destroy() 相当でログアウト
// 「セキュリティ上の理由でログアウトしました。再ログインしてください。」ページへ


// LEVEL_CRITICAL（is_blocked に引っかかり続けるケース）
// セッション操作は原則しない（もう信用していないため）
// 単純に「ブロック中です」ページ or ログアウト済みページを返すだけ
// ログ・監視には強めに記録


// 4. CSRF 文脈での「セッション廃棄しない」選択肢について
// CSRF は「正規ユーザのブラウザを悪用する攻撃」なので、
// 多くの場合は「このリクエストだけ拒否」で十分です。
// なので、必ずしも CSRF 検知で毎回セッション廃棄が必須ではありません。
// ただし「明らかに異常な頻度＋rate limit で HIGH に達した」ときに
// 一度だけセッション廃棄＋ログアウトを行う、という設計は教材としても現実的です。


// まとめると：
// soft_failure → LEVEL_MEDIUM + reCAPTCHA
// max_failures → LEVEL_HIGH + このタイミングで1回だけ セッション廃棄＋ログアウト＋ブロック登録
// ブロック中の再攻撃 → LEVEL_CRITICAL + セッションには触らず block/ログアウト済みページを返すだけ
// という形に少し整理すると、あなたの案の意図を保ちながら、DoS 的なセッション廃棄ループも避けられるので、おすすめです。


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
        SecurityException::LEVEL_HIGH,
        null
    );
}elseif ($failure_count 
    >=
    RATE_LIMITS['ip']['soft_failure']) {
    throw CSRFException::fromCurrentRequest(
        'IPアドレスからのリクエストが多めです',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_MEDIUM,
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
        SecurityException::LEVEL_HIGH,
        null
    );
}elseif ($failure_count 
        >=
        RATE_LIMITS['session']['soft_failure']) {
    throw CSRFException::fromCurrentRequest(
        'セッションからのリクエストが多めです',
        SecurityException::SEC_CSRF_ATTACK,
        [],
        SecurityException::LEVEL_MEDIUM,
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
            SecurityException::LEVEL_HIGH,
            null
        );
    }elseif ($failure_count 
    >=
    RATE_LIMITS['device']['soft_failure']) {
        throw CSRFException::fromCurrentRequest(
            'デバイスからのリクエストが多めです',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
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
            SecurityException::LEVEL_HIGH,
            null
        );
    }elseif ($failure_count 
    >=
    RATE_LIMITS['ip_prefix']['soft_failure']) {
        throw CSRFException::fromCurrentRequest(
            'IPプレフィックスからのリクエストが多めです',
            SecurityException::SEC_CSRF_ATTACK,
            [],
            SecurityException::LEVEL_MEDIUM,
            null
        );
    }
}
