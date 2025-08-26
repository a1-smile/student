<?php
//  index.php では、データベースからすべての学生情報を取得し、表示します。
//  画面遷移は、student_edit.php （form から POST で、）
//            student_input.php （a タグで、）
//            form から送信するデーターは、データベースから取得した学生情報の
//            idと
//            csrf_token です。


// <form action="student_edit.php" method="post" class="table-form">
//             <input type="hidden" name="id" value="{$id}">
//             <input type="hidden" name="data" value="update">
//             <input type="hidden" name="csrf_token" value="{$token}">
//             <button type="submit">編集確認へ...</button>
//         </form>

//  <form action="student_edit.php" method="post" class="table-form">
//             <input type="hidden" name="id" value="{$id}">
//             <input type="hidden" name="data" value="delete">
//             <input type="hidden" name="csrf_token" value="{$token}">
//             <button type="submit">削除確認へ...</button>
//         </form>
//    
//  
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

//  共通ファイルを読み込みます。
    if (file_exists(__DIR__."/common.php")) {
        require_once (__DIR__."/common.php");
        }else {
            die('common.php が見つかりません');
        }
//  session timeout を設定します。
handle_session_timeout();


//  ユーザーエージェントをチェックする
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} elseif (!hash_equals($_SESSION['user_agent'], $_SERVER['HTTP_USER_AGENT'])) {
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

//  初回アクセス時は記録しておく
//  ここでは、IPv4とIPv6の両方に対応した方法を示します。

//  $ip の先頭部分が記憶されているかをチェック
if (!isset($_SESSION['ip_prefix'])) {
    //  ip_prefix が保存されていない場合
    // IPv6かどうかを判定
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // IPv6 → コロン区切りの先頭3ブロックだけ記録（例：2001:0db8:85a3）
        //  explode()は文字列を区切り文字で分割し、配列に変換します。
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
    //  現在の接続元 ip_prefix と、保存されている ip_prefix を比較
    //  hash_equals() は、2つの文字列が等しいかどうかを比較します。
    if (!hash_equals($current_prefix, $prefix)) {
        // IPの接続元が変わった → セッションを破棄
        // 
        session_unset();  //  id が残る。
        session_destroy();  //  destroy だけではデータが残る可能性がある。
        die('新しい端末からのアクセス、もしくは通信環境が変わったため、再入力が必要です');
    }
}

    
    
//  HTMLの開始タグ、h1 タグを表示
show_top();

// すべての学生情報を取得します。
try{
    $members = $dbm->get_allstudents();
} catch (Exception $e) {
    // エラーメッセージを表示します。
    echo '<p>データーベースエラーが発生しました: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    $members = [];
}

if ($members === []) {
    // 学生情報が存在しない場合は、メッセージを表示します。
    echo '<p>データベースエラーまたは、学生情報が登録されていません。</p>';
}else {
        // 学生情報が存在する場合は、学生情報を表示します。
//  トークンを生成します。
$token = bin2hex(random_bytes(32)); // 32バイトのランダムなトークンを生成
$_SESSION['csrf_token'] = $token;
$_SESSION['csrf_token_time'] = time(); // トークンの生成時間を記録

show_student_list($members);
}

//  新しい学生情報を登録するためのリンクを表示します。
//  このリンクは、student_input.php に遷移します。
echo '<a href="student_input.php">新しい学生情報を登録する</a>';
echo '<br><br>';
//  HTML の閉じタグを表示します。
show_bottom();
?>
    
