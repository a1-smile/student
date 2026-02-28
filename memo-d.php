<?php

/*
user-agent-check.phpで定義した
関数 user_agent_check() のチェックをおこないます。

vs code のターミナルの git bash で
curl コマンドを使用して
動作確認を行います。
test_index.php へアクセス
してから、
test-basic-safety.php へアクセスする流れを
を想定しています。
以前に正規ユーザーが一回アクセスする場合は
test-1.shで実行し、
テストを通過して次の処理へ進むことが確認できています。

次に、
session を維持したまま、
同一のユーザーエージェントで
連続アクセスした場合の動作を確認をしました。
この場合、閾値    
$ACCESS_THRESHOLD_ERROR       = ['session'=>5, 'ip'=>7];  // 1分間のアクセス回数でエラー扱い
のうちの$ACCESS_THRESHOLD_ERROR['session']（つまり５）
を超えた場合にerror_page.phpへリダイレクトされることを確認できています。
test-2.shで実行しました。

動作確認後テスト用の閾値は、本番環境と同じに戻します。

以上を踏まえて、
次に、同一ipアドレスから、
session id
を変化させながら、
同一ユーザーエージェントで
連続アクセスした場合の動作を確認を行います。
この場合、閾値    
$ACCESS_THRESHOLD_ERROR       = ['session'=>5, 'ip'=>7];
のうちの
$ACCESS_THRESHOLD_ERROR['ip']（つまり７）
を超えた場合にerror_page.phpへリダイレクト
されることを確認したいと思います。

以下の手順で確認を行います。

まず、
ユーザーエージェントを一定に
設定しておきます。

以下はループに入ります。
まず、
cookie を保存するファイルをクリアしておきます。
最初はcookie が無い状態です。
先に設定しておいたユーザーエージェントを指定して
test_index.php へアクセスすると、
session が開始されます。
cookie を取得します。
cookie に session id が保存されます。
これで、新しい session id が発行されます。
この時レスポンスボディーは表示しません。

その後、cookie を使用して、
同一ユーザーエージェントで
test-basic-safety.php へアクセスします。
test-basic-safety.php 内で
ユーザーエージェントは一致していると判定されます。
また、同一ipアドレスからのアクセスとして
アクセスがカウントされます。
カウント数がデータベースに保存されます。


ヘッダーとhttpレスポンス、リダイレクト先を確認します。
ループ終了します。
これを閾値を超えるまで繰り返します。
このテストを、test-3.shをvsコードのターミナルのgit bashで実行しました。
$ACCESS_THRESHOLD_ERROR['ip']（つまり７）
を超えた場合にerror_page.phpへリダイレクト
されることを確認できました。

次に、
user-agent なしでアクセスした場合の動作を確認します。
この場合、user-agent が空であるため、
警告ログが出力されることを確認されました。

次に、
単発でuser-agent 不一致の場合、これの動作を確認します。
user-agent-check.php内で
*/
// UA 不一致判定
    //  ミスマッチなら 1,  マッチなら 0
    $is_ua_mismatch = ($first_simple_ua !== $current_simple_ua) ? 1 : 0;
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
/*の記述から、エラーページへリダイレクトされることが想定されます。
このテストは、test-5.shをvsコードのターミナルのgit bashで実行しました。
エラーページへリダイレクトされることを確認できました。
そして、データベースにis_ua_mismatch === 1であることが
記録されていることも確認できました。

次に、student\only_cli.php を実行して、
コマンドラインインターフェイス（CLI）からの
実行で、データーベースの古いユーザーエージェントログが
削除されることを確認しました。
以下の sql 文が実行されています。
 */
$sql = 'DELETE FROM user_agent_logs WHERE access_time < (NOW() - INTERVAL 1 DAY)';
    $deleted = $pdo->exec($sql);
/*
次に recaptcha.php へのリダイレクトを確認
したいと思います。
*/
 // しきい値
    // 共有 ip アドレス環境を考慮して、
    //  ip アドレスベースのしきい値は
    // セッションIDベースのしきい値よりも緩やかに設定
    // （例: セッションIDベースの10倍）
    $ACCESS_THRESHOLD_ERROR       = ['session'=>30, 'ip'=>300];  // 1分間のアクセス回数でエラー扱い


    //  test 用閾値 テストのみ有効化
    $ACCESS_THRESHOLD_ERROR       = ['session'=>5, 'ip'=>7];  // 1分間のアクセス回数でエラー扱い


    $ACCESS_THRESHOLD_RECAPTCHA   = ['session'=>60, 'ip'=>600]; // 1分間のアクセス回数で reCAPTCHA へ


    //  test 用閾値 テストのみ有効化
    $ACCESS_THRESHOLD_RECAPTCHA   = ['session'=>10, 'ip'=>15];


    $MISMATCH_THRESHOLD_RECAPTCHA = ['session'=>5,  'ip'=>50]; // UA不一致回数で reCAPTCHA へ


    //  test 用閾値 テストのみ有効化
    $MISMATCH_THRESHOLD_RECAPTCHA = ['session'=>2,  'ip'=>3];

/*
「test 用閾値 テストのみ有効化」を適用した状態で、
同一 session で10回連続アクセスすると、
recaptcha.php へリダイレクトされることを確認できました。
このテストは、
test-6.shをvsコードのターミナルのgit bashで実行しました。

次に、session を変化させながら、
同一ipアドレスから、
同一ユーザーエージェントで
連続アクセスした場合の動作を確認を行います。
「test 用閾値 テストのみ有効化」を適用した状態で、
    $ACCESS_THRESHOLD_RECAPTCHA   = ['session'=>10, 'ip'=>15];
    のうち $ACCESS_THRESHOLD_RECAPTCHA['ip']（つまり15）
を超えた場合に、
recaptcha.php へリダイレクトされることを確認できました。
このテストは、
test-7.shをvsコードのターミナルのgit bashで実行しました。

次に、UA不一致回数で
recaptcha.php へリダイレクトされることを確認します。
「test 用閾値 テストのみ有効化」を適用した状態で、
    $MISMATCH_THRESHOLD_RECAPTCHA = 
    ['session'=>2,  'ip'=>3];
のうち $MISMATCH_THRESHOLD_RECAPTCHA['session']（つまり2）
同一 session で,
不正なユーザーエージェントを用いて
2回連続アクセスした場合に、
recaptcha.php へリダイレクトされることを確認します。
このテストは、
test-8.shをvsコードのターミナルのgit bashで実行します。

この部分は想定通りの動作をしないことが確認されました。
理由は、以下に示す通りです。
*/

/*
student\test-basic-safety.php で、
*/
$current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$current_simple_ua = get_simple_ua($current_user_agent);

if (!isset($_SESSION['first_simple_ua'])) {
        $_SESSION['first_simple_ua'] = $current_simple_ua;
    }

$first_simple_ua = $_SESSION['first_simple_ua'];

$is_ua_mismatch = ($first_simple_ua !== $current_simple_ua) ? 1 : 0;

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
/*
の記述から、ユーザーエージェントが不一致なら、
例外が投げられ、
catch ブロックで
 */
catch (SessionHijackingException $e) {
    $log_message = $e->getLogMessage();
    error_log($log_message);
    
    //  session 完全廃棄
    session_unset();
    session_destroy();
    session_write_close();

    $redirect_target = $e->getRedirectTarget();
    switch ($redirect_target) {
        case SessionHijackingException::REDIRECT_TARGET_RECAPTCHA:
            http_response_code(302);  // Found 一時的リダイレクト
            //  呼び出し先でアクセス禁止403のステータスコードを設定する方針
            header('Location: recaptcha.php', true, 302);
            exit;
        case SessionHijackingException::REDIRECT_TARGET_ERROR_PAGE:
        default:
            http_response_code(302);  // Found 一時的リダイ302
            //  呼び出し先でアクセス禁止403のステータスコードを設定する方針
            // http_response_code(403);  // Forbidden アクセス禁止
            header('Location: error_page.php', true, 302);
            exit;
    }
    
} 
/*
とセッション廃棄が行われるので、
セッションに
first_simple_ua が残り続けることはなくなります。
従って、ユーザーエージェントを変えながら連続アクセスした場合に
*/
$current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$current_simple_ua = get_simple_ua($current_user_agent);

if (!isset($_SESSION['first_simple_ua'])) {
        $_SESSION['first_simple_ua'] = $current_simple_ua;
    }

$first_simple_ua = $_SESSION['first_simple_ua'];

$is_ua_mismatch = ($first_simple_ua !== $current_simple_ua) ? 1 : 0;
/*の処理が繰り返され、
$is_ua_mismatch の値は 0 となり、
ユーザーエージェントを変えながらの連続アクセスを
追跡できないプログラムになってしまいます。
これを補うために、他のレイヤーで、
セキュリティー対策を講じる方針とします。




