<?php
// セッション開始前に安全なクッキー設定
// ただし、開発環境ではHTTPSが使えない場合もあ。
// その場合は、'secure' => false に設定します。
session_set_cookie_params([
    'lifetime' => 0,           // ブラウザを閉じるとクッキー削除
    'path' => '/',         // サイト全体で有効
    'domain' => '',            // 現在のドメインで有効
    'secure' => true,          // HTTPSのみクッキーを送信
    // 'secure' => false,       // 開発環境ではHTTPSが使えない場合はfalseに設定

    'httponly' => true,        // JSアクセス禁止
    'samesite' => 'Strict'     // 他サイトからのリクエストでは
]);                            // クッキーを送らない  (CSRF対策)

 // 以上は、セッション開始の前に設定を行う必要があります
//  セッションを開始します。
session_start();
//  session timeout を設定します。
//  ここでは、10 分に設定しています。
$session_timeout = 600; // 10分（600秒）
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // セッションの有効期限が切れた場合は
    //  session_regenerate_id(true) を呼び出して
    // セッションIDを再生成します。
    session_regenerate_id(true);
}else {
    // セッションの有効期限が切れていない場合は
    // 最終アクティビティのタイムスタンプを更新します。
    $_SESSION['last_activity'] = time();
}
//  ユーザーエージェントをチェックする
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} elseif ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    // ユーザーエージェントが異なる場合はセッションを破棄
    session_unset();
    session_destroy();
    die('新しい端末からのアクセス、もしくは通信環境が変わったため、再入力が必要です');
}


// IPアドレスの先頭部分をチェックする
// ここでは、IPv4とIPv6の両方に対応した方法を示します。
//  ip アドレスがipv4 かipv6をチェック

// 現在のアクセス元IP
$ip = $_SERVER['REMOTE_ADDR'];

// 初回アクセス時は記録しておく
if (!isset($_SESSION['ip_prefix'])) {
    // IPv6かどうかを判定
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // IPv6 → コロン区切りの先頭3ブロックだけ記録（例：2001:0db8:85a3）
        $parts = explode(':', $ip);
        $_SESSION['ip_prefix'] = implode(':', array_slice($parts, 0, 3));
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        // IPv4 → ドット区切りの先頭2ブロックだけ記録（例：192.168）
        $parts = explode('.', $ip);
        $_SESSION['ip_prefix'] = implode('.', array_slice($parts, 0, 2));
    } else {
        die('不正なIPアドレス');
    }
} else {
    // すでに記録済み → 同じ接続元か確認
    $prefix = $_SESSION['ip_prefix'];

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        $current_prefix = implode(':', array_slice($parts, 0, 3));
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $current_prefix = implode('.', array_slice($parts, 0, 2));
    } else {
        die('不正なIPアドレス');
    }

    if ($current_prefix !== $prefix) {
        // IPの接続元が変わった → セッションを破棄
        // 
        session_unset();  //  id が残る。
        session_destroy();  //  destroy だけではデータが残る可能性がある。
        die('新しい端末からのアクセス、もしくは通信環境が変わったため、再入力が必要です');
    }
}

    //  トークンを生成します。
    $token = bin2hex(random_bytes(32)); // 32バイトのランダムなトークンを生成
    $_SESSION['csrf_token'] = $token;

        if (file_exists(__DIR__."/common.php")) {
        require_once (__DIR__."/common.php");
        }else {
            die('common.php が見つかりません');
        }
    
    //  HTMLの開始タグ、h1 タグを表示
    show_top();

    // すべての学生情報を取得します。
    $members = $dbm->get_allstudents();

    if ($members === []) {
        // 学生情報が存在しない場合は、メッセージを表示します。
        echo '<p>学生情報が登録されていません。</p>';
    }else {
        // 学生情報が存在する場合は、学生情報を表示します。
        show_student_list($members);
    }

    //  新しい学生情報を登録するためのリンクを表示します。
    //  このリンクは、student_input.php に遷移します。
    echo '<a href="student_input.php">新しい学生情報を登録する</a>';
    echo '<br><br>';
    //  HTML の閉じタグを表示します。
    show_bottom();
?>
    
