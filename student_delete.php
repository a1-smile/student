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
    //  セッションをクリアして、プログラムを終了します。
    session_unset();
    session_destroy();
    die('10分以上経過したため、プログラムを停止しました。トップページに戻り、再読み込みをしてください。');
    }else {
    // セッションの有効期限が切れていない場合は
    // 最終アクティビティのタイムスタンプを更新します。
        $_SESSION['last_activity'] = time();
        // セッションIDを再生成します。
        session_regenerate_id(true);
    }

        //  ユーザーエージェントをチェックする
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    } elseif ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        // ユーザーエージェントが異なる場合はセッションを破棄
        session_unset();
        session_destroy();
        die('ユーザーエージェントが変更されたため、セッションを破棄しました。');
    }

        // IPアドレスの先頭部分をチェックする
    // ここでは、IPv4とIPv6の両方に対応した方法を示します。
    //  ip アドレスがipv4 かipv6をチェック

    // 現在のアクセス元IP
    $ip = $_SERVER['REMOTE_ADDR'];

    //  初回アクセス時は記録しておく
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
            die('IPv4,IPv6 のいずれでもない場合は停止します。');
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
        die('IPv4,IPv6 のいずれでもない場合は停止します。');
    }

    if ($current_prefix !== $prefix) {
        // IPの接続元が変わった → セッションを破棄
        // 
        session_unset();  //  id が残る。
        session_destroy();  //  destroy だけではデータが残る可能性がある。
        die('接続元のIPアドレスが変更されたため、セッションを破棄しました。');
        }
    }

    
    //  SESSION から、 token を取得
    if (!isset($_SESSION['csrf_token'])) {
        session_unset();  //  id が残る。
        session_destroy();  //  destroy だけではデータが残る可能性がある。
        die('セッションからトークンが取得できません');
    }else{
        $token_s = $_SESSION['csrf_token'];
        //  エスケープします。
        $token_s = htmlspecialchars($token_s, ENT_QUOTES, 'UTF-8');
    }


    //  送信メソッドを確認します。
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        delete_post($token_s);
    }elseif ($method === 'GET') {
        delete_get($token_s);
    } else {
        die('メソッドが不正です。POST または GET でアクセスしてください。');
    }
    


    function delete_post($token_s) {
    //  共通ファイルを読み込みます。
    //  common.php が存在しない場合はエラーを表示します。
        if (file_exists(__DIR__."/common.php")) {
            require_once (__DIR__."/common.php");
        }else {
            die('common.php が見つかりません');
        }
        global $dbm; // グローバル変数$dbmにアクセス
        //  POST からのデータを取得します。
        if (!isset($_POST['csrf_token'])) {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('POST からのトークンが取得できません');
        }else {
            //  CSRF トークンが設定されている場合は、
            //  htmlspecialchars() を使用してエスケープします。
            $token_p = htmlspecialchars($_POST['csrf_token'], ENT_QUOTES, 'UTF-8');
        }
        if (!isset($_POST['id'])) {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('POST で、学生番号が送信されていません');
        } else {
            $id = htmlspecialchars($_POST['id'], ENT_QUOTES, 'UTF-8');
        }
        if (!isset($_POST['data'])) {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('POST で、$data が送信されていません');
        } else {
            $data = htmlspecialchars($_POST['data'], ENT_QUOTES, 'UTF-8');
        }

        //  トークンを比較します。
        if ($token_p !== $token_s) {
            session_unset();  //  id が残る。
            session_destroy();
            die('トークンが一致しません');
        }
    $member = $dbm->get_student($id);
    show_top('学生情報の削除');
    //  show_delete() 関数を呼び出して
    //  table で学生情報を表示し、
    //  学生情報の削除フォームを表示します。
    show_delete($member);
    //  true なら、「学生情報の一覧に戻る」というリンクを表示します。
    show_bottom(true);  
    }
    function delete_get($token_s) {
    //  共通ファイルを読み込みます。
    //  common.php が存在しない場合はエラーを表示します。
        if (file_exists(__DIR__."/common.php")) {
            require_once (__DIR__."/common.php");
        }else {
            die('common.php が見つかりません');
        }

        //  post_data.php からリダイレクトされるときに
        //  SESSION にデータを保存して、
        //  ここで取得します。
        if (!isset($_SESSION['id'])) {
            die('学生番号が保存されていません');
        }else {
            $id = $_SESSION['id'];
        }
        
        if (!isset($_SESSION['error'])) {
            die('エラーが保存されていません');
        }else {
            $error = $_SESSION['error'];
        }
        
        if (!isset($_SESSION['token_re'])) {
            session_unset();  //  id が残る。
            session_destroy();  //  destroy だけではデータが残る可能性がある。
            die('token_re が保存されていません');
        }else {
            $token_re = $_SESSION['token_re'];
        }
        //  エスケープします。
        $token_re = htmlspecialchars($token_re, ENT_QUOTES, 'UTF-8');
        $id       = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $error    = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
        //  CSRF トークンが一致するかを確認します。
        if ($token_re !== $token_s) {
            session_unset();  //  id が残る。
            session_destroy();
            die('トークンが一致しません');
        }
        global $dbm; // グローバル変数$dbmにアクセス
        $member = $dbm->get_student($id);
    show_top('学生情報の削除');
    //  show_delete() 関数を呼び出して
    //  table で学生情報を表示し、
    //  学生情報の削除フォームを表示します。
    show_delete($member);
    //  true なら、「学生情報の一覧に戻る」というリンクを表示します。
    show_bottom(true);


    }
?>