<?php

/**
 * ip_prefix のチェック
 * @param int|null $ipv4_blocks IPv4の場合に何ブロックまで取るか（1-4）。nullの場合は環境変数またはデフォルト値を使用
 * @param int|null $ipv6_blocks IPv6の場合に何ブロックまで取るか（1-8）。nullの場合は環境変数またはデフォルト値を使用
 * @throws SessionHijackingException セッションハイジャック検出時
 */
function ip_check_for_session(int $ipv4_blocks = null, int $ipv6_blocks = null): void {
    // 環境に応じたデフォルト値
    $ipv4_blocks = $ipv4_blocks ?? ($_ENV['IP_CHECK_IPV4_BLOCKS'] ?? 2);
    $ipv6_blocks = $ipv6_blocks ?? ($_ENV['IP_CHECK_IPV6_BLOCKS'] ?? 3);

    $current_prefix = get_ip_prefix_for_session($ipv4_blocks, $ipv6_blocks);

    // 初回アクセス時の処理
    if (!isset($_SESSION['ip_prefix'])) {
        
            $_SESSION['ip_prefix'] = $current_prefix;
        
    } else {
        // 既存のセッションとの比較
        $stored_prefix = $_SESSION['ip_prefix'];
        $result = $current_prefix === $stored_prefix;
        if (!$result) {
            //  IPアドレスの先頭部分が異なる場合はコンテキストを記録して、
            //  SessionHijackingExceptionをスロー
            $context = [
                'previous_ip_prefix' => $stored_prefix,
                'current_ip_prefix' => $current_prefix,
                'session_id' => session_id(),
            ];
            throw new SessionHijackingException(
                'IPアドレスが変更されました', //$message
                SecurityException::SEC_SESSION_HIJACK, //$code
                $context, // $context
                SecurityException::LEVEL_HIGH, // $securityLevel
                null // $previous
                );
        }
    }

}
//  使用例
// //  セッションスタート
// session_start();
// try {
//     // 現在のIPアドレスの先頭部分を取得
//     ip_check_for_session();
// } catch (SessionHijackingException $e) {
//     $log_message = $e->getLogMessage();
//     error_log($log_message);
    
//     //  session 完全廃棄
//     session_unset();
//     session_destroy();
//     session_write_close();
//     http_response_code(403);  // Forbidden アクセス禁止
//     die('セッションハイジャック攻撃検出<br>
//          セキュリティ上の理由により処理を中断します。<br>
//          この攻撃は記録され、管理者に通報されました。<br>
//          攻撃検出ID: ' . uniqid() . '<br>
//          正常な操作を行う場合は、トップページから再開してください。');
// } catch (InvalidArgumentException $e) {
//     // 不正なIPアドレス形式
//     error_log("不正なIPアドレス - IP:".($_SERVER['REMOTE_ADDR'] ?? 'unknown').", エラー: " . $e->getMessage() . ", UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
    
//     session_unset();
//     session_destroy();
//     session_write_close();
//     http_response_code(400);  // Bad Request 不正なリクエスト
//     die('不正なアクセスです。サポートされていないネットワーク環境からのアクセスです。');
         
// } catch (RuntimeException $e) {
//     // システムエラー
    
//     $session_id = session_id();
//     error_log("IPアドレス処理エラー - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", エラー: " . $e->getMessage() . ", セッションID: " . $session_id);

//     session_unset();
//     session_destroy();
//     session_write_close();
//     http_response_code(500);  // Internal Server Error 内部サーバーエラー
//     die('システムエラーが発生しました。ネットワーク環境を確認してください。<br>
//          エラーID: ' . uniqid() . '<br>
//          <a href="index.php">学生一覧に戻る</a>');
         
// } catch (Exception $e) {
//     // その他の予期しないエラー
//     error_log("予期しないエラー - IPアドレス処理 - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", エラー: " . $e->getMessage());
    
//     session_unset();
//     session_destroy();
//     session_write_close();
//     http_response_code(500);  // Internal Server Error 内部サーバーエラー
//     die('システムエラーが発生しました。管理者にお問い合わせください。<br>
//          エラーID: ' . uniqid() . '<br>
//          <a href="index.php">学生一覧に戻る</a>');
// }
