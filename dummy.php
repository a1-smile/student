<?php
class DBManager {
public function get_student($id) {
    try {
        // 入力値検証
        if (!is_numeric($id) || $id <= 0) {
            throw new InvalidArgumentException('不正な学生IDです: ' . $id);
        }
        
        // データベースに接続
        $this->connect();
        
        // SQL文を準備
        $sql = 'SELECT * FROM student WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        
        if ($stmt === false) {
            throw new RuntimeException('SQL文の準備に失敗しました');
        }
        
        // プレースホルダーに値をバインド
        $bind_result = $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        if ($bind_result === false) {
            throw new RuntimeException('パラメーターのバインドに失敗しました');
        }
        
        // SQL文を実行
        $res = $stmt->execute();
        if ($res === false) {
            $error_info = $stmt->errorInfo();
            throw new PDOException(
                'SQL実行エラー: ' . $error_info[2], 
                $error_info[1]
            );
        }
        
        // 結果を取得
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // データベースから切断
        $this->disconnect();
        
        // レコードが見つからない場合はnullを返す
        if ($member === false) {
            return null;
        }
        
        return $member;
        
    } catch (PDOException $e) {
        // 詳細なエラー情報をログに記録
        $error_details = [
            'method' => 'get_student',
            'id' => $id,
            'sqlstate' => $e->getCode(),
            'driver_code' => $e->errorInfo[1] ?? null,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
        error_log('DBManager PDOException: ' . json_encode($error_details, JSON_UNESCAPED_UNICODE));
        
        // 接続を切断
        $this->disconnect();
        
        // 例外を再スロー（カスタム例外でラッピングも可能）
        throw new DatabaseException(
            '学生情報の取得中にデータベースエラーが発生しました: ' . $e->getMessage(),
            $e->getCode(),
            ['original_error' => $error_details],
            $e->getCode(),
            $e->errorInfo[1] ?? null,
            $e
        );
        
    } catch (Exception $e) {
        // その他の例外
        error_log('DBManager Exception: ' . $e->getMessage());
        $this->disconnect();
        
        // 元の例外を再スロー
        throw $e;
    }
}
}

//  student_edit.php の修正
try {
    // データベース接続状態の確認
    if (!($dbm instanceof DBManager)) {
        throw new RuntimeException('オブジェクトの未生成');
    }
    
    // 学生情報の取得（修正されたDBManagerから例外が来る）
    $member = $dbm->get_student($id);
    
    // nullチェック（データが見つからない場合）
    if ($member === null) {
        throw new BusinessLogicException(
            'データの整合性に問題があります。index.phpから正常に遷移したはずのIDでデータが見つかりません',
            BusinessLogicException::ERR_STUDENT_NOT_FOUND,
            [
                'id' => $id,
                'session_id' => session_id(),
                'referrer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
                'stamp' => date('Y-m-d H:i:s'),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]
        );
    }
    
} catch (DatabaseException $e) {
    // カスタムデータベース例外の処理
    error_log($e->getLogMessage());
    
    $driver_code = $e->getDriverCode();
    switch ($driver_code) {
        case 2002:
        case 2003:
        case 2006:
            die('データベースサーバーに接続できません。<br>
                 しばらく待ってから再試行してください。<br>
                 <a href="index.php">学生一覧に戻る</a>');
            break;
        default:
            die('データベースエラーが発生しました。<br>
                 エラーID: ' . uniqid() . '<br>
                 <a href="index.php">学生一覧に戻る</a>');
    }
    
} catch (PDOException $e) {
    // 直接のPDOException処理（現在のコードと同じ）
    $sqlstate = $e->getCode();
    $error_info = $e->errorInfo;
    $driver_code = $error_info[1] ?? null;
    
    $log_message = sprintf(
        "PDOエラー - get_student() - ID: %s, SQLSTATE: %s, ドライバーコード: %s, メッセージ: %s, ファイル: %s, 行: %d, IP: %s, セッションID: %s",
        $id,
        $sqlstate,
        $driver_code,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $_SERVER['REMOTE_ADDR'],
        session_id()
    );
    error_log($log_message);
    
    // RuntimeExceptionでラッピングして再スロー
    throw new RuntimeException(
        '学生情報の取得中にデータベースエラーが発生しました',
        is_numeric($driver_code) ? (int)$driver_code : 0,
        $e
    );
    
} catch (BusinessLogicException $e) {
    // ビジネスロジックエラーの処理
    $context = $e->getContext();
    $log_message = sprintf(
        "ビジネスロジックエラー - ID: %s, メッセージ: %s, コンテキスト: %s, IP: %s, 時刻: %s",
        $context['id'] ?? 'unknown',
        $e->getMessage(),
        json_encode($context, JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'],
        date('Y-m-d H:i:s')
    );
    error_log($log_message);
    
    switch ($e->getCode()) {
        case BusinessLogicException::ERR_STUDENT_NOT_FOUND:
            die('指定された学生情報が見つかりません。データの整合性に問題が発生しました。<br>
                 管理者に報告されました。<br>
                 エラーID: ' . uniqid() . '<br>
                 <a href="index.php">学生一覧に戻る</a>');
            break;
        default:
            die('データの整合性に問題が発生しました。管理者に報告されました。<br>
                 エラーID: ' . uniqid() . '<br>
                 <a href="index.php">学生一覧に戻る</a>');
    }
}