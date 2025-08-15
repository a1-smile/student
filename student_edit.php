<?php
//  student_edit.php は学生情報を編集または削除するかを確認するページです。
//  画面遷移は、
//  遷移元は index.php からの POST のみです。
// <form action="student_edit.php" method="post" class="table-form">
//     <input type="hidden" name="id" value="{$id}">
//     <input type="hidden" name="data" value="delete">
//     <input type="hidden" name="csrf_token" value="{$token}">
//     <button type="submit">削除確認へ...</button>
// </form>
    
// <form action="student_edit.php" method="post" class="table-form">
//     <input type="hidden" name="id" value="{$id}">
//     <input type="hidden" name="data" value="update">
//     <input type="hidden" name="csrf_token" value="{$token}">
//     <button type="submit">編集確認へ...</button>
// </form>
//  POST で受け取る値は、id, data, csrf_token です。

//  遷移先は条件分岐して、
//  student_update.php または student_delete.php です。
//  メソッドは post です。
//  送信する値は、id, data, csrf_token です。
// function show_operations($id,$data,$operation, $post_file, $token) {
//         echo <<<OPERATIONS
//         <form action="{$post_file}" method="post" class="operation-form">
//             <input type="hidden" name="id" value="{$id}">
//             <input type="hidden" name="data" value="{$data}">
//             <input type="hidden" name="csrf_token" value="{$token}">
//             <input type="submit" value="{$operation}">
//         </form>
//         OPERATIONS;
//     }

//  データベース操作は、

// セッション開始前に安全なクッキー設定
// ただし、開発環境ではHTTPSが使えない場合もあります。
// その場合は、'secure' => false に設定します。
session_set_cookie_params([
    'lifetime' => 0,           // ブラウザを閉じるとクッキー削除
    'path'     => '/',         // サイト全体で有効
    'domain'   => '',            // 現在のドメインで有効
    'secure'   => true,          // HTTPSのみクッキーを送信
//  'secure'   => false,       // 開発環境ではHTTPSが使えない場合はfalseに設定

    'httponly' => true,        // JSアクセス禁止
    'samesite' => 'Strict'     // 他サイトからのリクエストでは
]);                            // クッキーを送らない  (CSRF対策)

//  以上は、セッション開始の前に設定を行う必要があります

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
    // ユーザーエージェントが前回と異なる場合はセッションを破棄
    session_unset();
    session_destroy();
    die('不正なアクセスです。新しい端末からのアクセス、もしくは通信環境が変わったため、再入力が必要です');
}

// IPアドレスの先頭部分をチェックする
// ここでは、IPv4とIPv6の両方に対応した方法を示します。
//  ip アドレスがipv4 かipv6をチェック

// 現在のアクセス元IP
$ip = $_SERVER['REMOTE_ADDR'];

//  初回アクセス時は ip_prefix を SESSION に記録しておく
//  プレフィックスの値がまだ設定されていない場合
if (!isset($_SESSION['ip_prefix'])) {
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

//  前回のip_prefix を SESSION から取得
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

if (!hash_equals($current_prefix, $prefix)) {
    // IPの接続元が変わった → セッションを破棄
    // 
    session_unset();  //  id が残る。
    session_destroy();  //  destroy だけではデータが残る可能性がある。
    die('新しい接続元からのアクセス、もしくは通信環境が変わったため、再入力が必要です');
    }
}

    //  CSRF対策のためのトークンを確認します。
    if (!isset($_SESSION['csrf_token'])) {
        session_unset();  //  id が残る。
        session_destroy();  //  destroy だけではデータが残る可能性がある。
        die('安全対策のため、学生情報一覧画面から再操作を行ってください');
    } else {
        $session_token = $_SESSION['csrf_token'];
    }

    if (!isset($_POST['csrf_token'])) {
        session_unset();  //  id が残る。
        session_destroy();  //  destroy だけではデータが残る可能性がある。
        die('POST からのトークンが取得できません');
    }else {
        $post_token = $_POST['csrf_token'];
    }
    if (!hash_equals($post_token, $session_token)) {
        session_unset();  //  id が残る。
        session_destroy();
        die('トークンが一致しません');
    }else {
        //  トークンを使い捨て
        unset($_SESSION['csrf_token']);
        $session_token = null;
        unset($_POST['csrf_token']);
        $post_token = null;
    }

        //  POST メソッドで送信されたかを確認し、
    //  そうでない場合は、不正なアクセスとして処理を終了します。
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        die('通信形式が一致しません、POST のみを受け付けます');
    }


    
    //  $_POST['id'] が設定されているかを確認します。
    //  設定されていない場合は、不正なアクセスとして処理を終了します。
    $id = $_POST['id'] ?? null;
    if ($id === null) {
        die('$id が送信されていません');
    }
    //  $id をエスケープ
    $id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');

    //  get_student() メソッドは
    //  $id を引数に取り、学生情報を取得します。
    //  戻り値は、連想配列の形で学生情報が格納されます。
    //  学生情報が存在しない場合は、null を返します。
    $member = $dbm->get_student($id);
    if ($member === null) {
        die('指定された学生番号は存在しません');
    }

    show_top('個別の学生情報');
    //  table で学生情報を表示します。
    show_student($member);
    //  学生情報を更新するか、削除するかを
    //  条件分岐します。
    //  $_POST['data'] の値が 'update' の場合は
    //  $operation = '更新します';
    //  $post_file = 'student_update.php';

    //  $_POST['data'] の値が 'delete' の場合は
    //  $operation = '削除します';
    //  $post_file = 'student_delete.php';
    $operation = '';
    $post_file = '';
    
    $data = htmlspecialchars($_POST['data'] ?? '', ENT_QUOTES, 'UTF-8');
    if ($data === '') {
        die('data が指定されていません');
    }
    if (isset($_POST['data']) && $_POST['data'] === 'update') {
        $operation = '更新ページへ...';
        $post_file = 'student_update.php';
    } elseif (isset($_POST['data']) && $_POST['data'] === 'delete') {
        $operation = '削除ページへ...';
        $post_file = 'student_delete.php';
    } else {
        die('不正な操作です');
    }
    //  学生情報を削除するか、更新するかを
    //  選択します。
    //  「学生情報を削除」ボタンまたは「学生情報を更新」ボタンを表示します。
    //  あらためて、$id, $data, $operation, $post_file, をエスケープします。
    $id        = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    $data      = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    $operation = htmlspecialchars($operation, ENT_QUOTES, 'UTF-8');
    $post_file = htmlspecialchars($post_file, ENT_QUOTES, 'UTF-8');
    
    //  トークンを再生成します。
    $token = bin2hex(random_bytes(32));
    $token = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
    $_SESSION['csrf_token'] = $token;

    //  show_operations() 関数を呼び出して、操作ボタンを表示します。
    show_operations($id, $data, $operation, $post_file, $token);
    //  「学生情報一覧に戻る」リンクを表示します。
    show_bottom(true);

?>