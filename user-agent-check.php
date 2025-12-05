<?php

/**
 * userAgentCheck - ユーザーエージェントの検証
 * $_SESSION['user_agent'] がセットされていない場合は
 * $_SERVER['HTTP_USER_AGENT'] をセットします。
 * そうでなければ、$stored_user_agent = $_SESSION['user_agent']
 * と $current_user_agent = $_SERVER['HTTP_USER_AGENT'] をセットします。
 * $result = $stored_user_agent === $current_user_agent;
 * もし $result が false なら
 * コンテキストに[
 *           'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
 *           'user_agent'     => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
 *           'referer'        => $_SERVER['HTTP_REFERER'] ?? 'unknown',
 *           'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
 *           'request_uri'    => $_SERVER['REQUEST_URI'] ?? 'unknown',
 *           'request_time'   => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time())
 *           'session_id'     => session_id() ?? 'unknown'
 *       ]
 * ログを記録し、
 * SessionHijackingExceptionをスローします。
 * @throws SessionHijackingException セッションハイジャックが疑われる場合
 */
function userAgentCheck() {
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return ;
    }
    
    $stored_user_agent = $_SESSION['user_agent'];
    $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
     //  User-Agentが空の場合もセッションハイジャックの可能性があるとして例外を投げる
     //  空のUser-Agentは通常ありえないため,
     //  User-Agentが空の場合にアクセスを拒否しても、
     //  正常なユーザーに影響を与えることはほとんどないと考えられます。

    if (empty($current_user_agent)) {
        $context = [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'stored_user_agent' => $stored_user_agent,
            'current_user_agent' => $current_user_agent,
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time()),
            'session_id' => session_id() ?? 'unknown'
        ];
        throw new SessionHijackingException(
            'User-Agentが空です', 
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null);
    }

    
    $result = $stored_user_agent === $current_user_agent;
    
    if (!$result) {
        $context = [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'stored_user_agent' => $stored_user_agent,
            'current_user_agent' => $current_user_agent,
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time()),
            'session_id' => session_id() ?? 'unknown'
        ];
        throw new SessionHijackingException(
            'セッションハイジャックが疑われます',
            SecurityException::SEC_SESSION_HIJACK,
            $context,
            null
        );
    }
    
}

