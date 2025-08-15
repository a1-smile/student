<?php
//  student_delete.php の説明。
//  画面遷移は、
//  遷移元  student_edit.php(POSTメソッド) または  post_data.php（headerリダイレクト）
//  取得データは、$id POST['id'] または $_SESSION['id']
//  データベース操作は、$id に対応する学生情報を連想配列で取得します。
//  table で学生情報を表示し、
//  get_error() 関数を使用してエラーメッセージを取得します。
//  form で学生情報の削除フォームを表示します。
//  submit で $id, data='delete', token を送信します。

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
    //  共通ファイルを読み込みます。
//  common.php が存在しない場合はエラーを表示します。
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

   
    


    //  送信メソッドを確認します。
    $method = $_SERVER['REQUEST_METHOD'];

    //  $id を取得します。
    if ($method === 'POST') {
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
            unset($_POST['id']);
        } else {
            die('POST メソッドで id が指定されていません。');
        }
    } elseif ($method === 'GET') {
        if (isset($_SESSION['id'])) {
            $id = $_SESSION['id'];
            unset($_SESSION['id']);
        } else {
            die('GET メソッドで id が指定されていません。');
        }
    } else {
        die('POST, GET のいずれでもない遷移です。');
    }

    //  POST の場合は、CSRFトークンをチェックします。
            if ($method === 'POST') {
                if (isset($_POST['csrf_token'])) {
                    $token_post = $_POST['csrf_token'];
                    unset($_POST['csrf_token']);
                }else{
                    die('POST メソッドで CSRF トークンが指定されていません。');
                }
                if (isset($_SESSION['csrf_token'])) {
                    $token_session = $_SESSION['csrf_token'];
                    unset($_SESSION['csrf_token']);
                } else {
                    die('セッションに CSRF トークンが存在しません。');
                }
                if ($token_post !== $token_session) {
                    die('CSRF トークンが一致しません。');
                }
            }

   
   
//  token を再生成します。
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

        //  デバッグ用コード

    //     echo "=== student_delete.php デバッグ開始 ===<br>";
    
    // if (file_exists(__DIR__."/common.php")) {
    //     echo "common.php 読み込み前<br>";
    //     require_once (__DIR__."/common.php");
    //     echo "common.php 読み込み後<br>";
    // } else {
    //     die('common.php が見つかりません');
    // }
    
    // echo "global 宣言前の \$dbm: " . (isset($dbm) ? 'セット済み' : '未セット') . "<br>";
    // global $dbm;
    // echo "global 宣言後の \$dbm: " . (isset($dbm) ? 'セット済み' : '未セット') . "<br>";
    
    // if ($dbm === null) {
    //     die('$dbm が null です');
    // }
    
    

        // if ($dbm === null) {
        //     die('$dbm が null です。DBManagerの初期化に失敗している可能性があります。');
        // }
        // if (!($dbm instanceof DBManager)) {
        //     die('$dbm は DBManager のインスタンスではありません。');
        // }
        //  デバッグ用コード終了

//  データベースから個別の学生情報を連想配列で取得します。
    try {
        $member = $dbm->get_student($id);
    } catch (Exception $e) {
        die('$id から $member を取得できませんでした: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
    show_top('学生情報の削除');
    //  show_delete() 関数を呼び出して
    //  table で学生情報を表示し、
    //  学生情報の削除フォームを表示します。
    show_delete($member, htmlspecialchars($token, ENT_QUOTES, 'UTF-8'));

    //  show_bottom（） は、true なら、「学生情報の一覧に戻る」というリンクを表示します。
    show_bottom(true);  

    
?>