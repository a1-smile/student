<?php
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
追跡できないプログラムになってしまうようです。


