<?php
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
        die('不正なアクセス');
    }

        // IPアドレスの先頭部分をチェックする
    // ここでは、IPv4とIPv6の両方に対応した方法を示します。
    //  ip アドレスがipv4 かipv6をチェック

    // 現在のアクセス元IP
    $ip = $_SERVER['REMOTE_ADDR'];

    //  初回アクセス時は $_SESSION['ip_prefix'] を記録しておく
    //  プレフィックスの値がまだ設定されていない場合
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
    //  すでに記録済み → 同じ接続元か確認
    //  $prefix にセッションのプレフィックスをセット
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
    //  現在のIPのプレフィックスとセッションのプレフィックスを比較
    //  プレフィックスが異なる場合はセッションを破棄
    if ($current_prefix !== $prefix) {
        // IPの接続元が変わった → セッションを破棄
        // 
        session_unset();  //  id が残る。
        session_destroy();  //  destroy だけではデータが残る可能性がある。
        die('セッションハイジャック防止のため再入力が必要です');
        }
    }
    //  セッションの有効期限、ユーザーエージェント、IPアドレスのチェックが完了しました。

    //  さらに、トークンの確認を行います。
    // //  CSRF対策のためのトークンを確認します。
    if (!isset($_SESSION['csrf_token'])) {
        session_unset();  //  id が残る。
        session_destroy();  //  destroy だけではデータが残る可能性がある。
        die('安全対策のため、学生情報一覧画面から再操作を行ってください');
    }
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        session_unset();  //  id が残る。
        session_destroy();
        die('不正なアクセス');
    } 
    
        //  POST メソッドで送信されたかを確認し、
    //  そうでない場合は、不正なアクセスとして処理を終了します。
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        die('通信形式が一致しません');
    }
    //  共通の関数を読み込みます。
    //  common.php が存在するかを確認します。
    //  存在しない場合は、エラーメッセージを表示します。
        if (file_exists(__DIR__."/common.php")) {
        require_once (__DIR__."/common.php");
    }else {
        die('common.php が見つかりません');
    }

    //  $old_id に $_POST['id'] の値をセットします。
    //  $_POST['id'] が設定されていない場合は、null をセットします。
    $old_id = $_POST['id'] ?? null;
    if ($old_id === null) {
        die('不正なアクセス');
    }
    //  POST で送信される値は、$id と トークン、update です。
    //  学生情報を取得します。
    $member = $dbm->get_student($old_id);
    //  $member は連想配列の形で学生情報が格納されます。
    // 学生情報が存在しない場合は、$member は [] となります。
    if (empty($member)) {
        die('指定された学生情報が存在しません');
    }
    $id    = $member['id'];
    $name  = $member['name'];
    $grade = $member['grade'];

    //  h1 タグで学生情報の更新を表示します。
    show_top('学生情報の更新');
    //  学生情報の更新フォームを表示します。
    show_update($id, $name, $grade, $old_id);
    //  それぞれ、デフォルトの値を設定します。
    //  入力されなければ、デフォルトの値が送信されます。
    //  更新ボタンをクリックすると、post_data.php にデータが送信されます。
    //  post_data.php では、$_POST['data']の値に応じて
    //  データベースの更新や削除を行います。
    //  data => 'update' の場合は
    //  学生情報の更新を行い、
    //  data => 'delete' の場合は
    //  学生情報の削除を行います。
    //
    //  「学生情報一覧に戻る」リンクを表示します。
    show_bottom(true);
?>