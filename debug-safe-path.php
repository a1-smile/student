<?php
/**
 * safe_file_path - path validation and normalization
 * 
 * @param string $file_name 読み込むファイル名
 * （関数を実行するディレクトリを基準にした相対パス）
 * （同じディレクトリ内のファイルならファイル名）
 * @param array $options オプション設定
 * @throws InvalidArgumentException 引数が無効な場合
 * @throws RuntimeException ファイル操作エラーの場合
 * @throws LogicException ファイルが既に読み込まれている場合
 * @return string $real_path 正規化されたファイルパス
 */
//  使用例
// try {
//     // 1. 安全なファイルパスの取得
//     $safe_path = safe_file_path('common.php');
    
//     // 2. グローバルスコープでrequire_once実行
//     require_once $safe_path;
// } catch (InvalidArgumentException $e) {
//     die("引数エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
// } catch (LogicException $e) {
//     die("ℹ️ ファイルは既に読み込み済みです: " . $e->getMessage() . "<br>");    
// } catch (RuntimeException $e) {
//     die("ファイル読み込みエラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
// } catch (ParseError $e) {
//     die("構文エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
// } catch (Error $e) {
//     die("致命的エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
// } catch (Exception $e) {
//     die("予期しないエラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
// }

function safe_file_path($file_name, $options = []) {
    // デフォルト設定
    $default_options = [
        'max_file_size' => 1024 * 1024,  // 1MB
        'allowed_extensions' => ['php', 'inc'],
        'check_syntax' => false,  // 事前構文チェック（重い処理）
        'log_level' => 'info'    // ログレベル
    ];
    
    $options = array_merge($default_options, $options);
    
    try {
        // 1. 入力値検証
        if (empty($file_name) || !is_string($file_name)) {
            throw new InvalidArgumentException('ファイル名が無効です');
        }
        
        if (strlen($file_name) > 255) {
            throw new InvalidArgumentException('ファイル名が長すぎます（255文字以内）');
        }
        
        // 2. 危険な文字の検出
        if (strpbrk($file_name, "\0\r\n\t") !== false) {
            throw new InvalidArgumentException('ファイル名に不正な文字が含まれています');
        }
        
        // 3. 拡張子チェック
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($file_extension, $options['allowed_extensions'], true)) {
            throw new InvalidArgumentException("許可されていない拡張子です: {$file_extension}");
        }
        
        // 4. パス構築と正規化
        $file_path = __DIR__ . DIRECTORY_SEPARATOR . $file_name;
        $real_path = realpath($file_path);
        
        if ($real_path === false) {
            throw new RuntimeException("ファイルが見つからないか、パスが無効です: {$file_name}");
        }
        
        // 5. 基本ディレクトリ内チェック（セキュリティ）
        $base_dir = realpath(__DIR__);
        if (strpos($real_path, $base_dir . DIRECTORY_SEPARATOR) !== 0) {
            throw new RuntimeException("ベースディレクトリ外のファイルです: {$real_path}");
        }
        
        // 6. ファイル存在確認（realpathでチェック済みだが明示的に）
        if (!file_exists($real_path)) {
            throw new RuntimeException("ファイルが存在しません: {$real_path}");
        }
        
        // 7. ファイル形式確認
        if (!is_file($real_path)) {
            throw new RuntimeException("指定されたパスはファイルではありません: {$real_path}");
        }
        
        // 8. 権限確認
        if (!is_readable($real_path)) {
            throw new RuntimeException("ファイルに読み取り権限がありません: {$real_path}");
        }
        
        // 9. ファイルサイズチェック
        $file_size = filesize($real_path);
        if ($file_size === false) {
            throw new RuntimeException("ファイルサイズの取得に失敗しました: {$real_path}");
        }
        
        if ($file_size > $options['max_file_size']) {
            throw new RuntimeException(
                "ファイルサイズが上限を超えています: " . 
                number_format($file_size) . " bytes (上限: " . 
                number_format($options['max_file_size']) . " bytes)"
            );
        }
        
        // 10. 重複読み込みチェック
        if (in_array($real_path, get_included_files(), true)) {            
                throw new LogicException("ファイルは既に読み込まれています: {$real_path}");        
        }
        
        // 11. 事前構文チェック（オプション）
        if ($options['check_syntax']) {
            $syntax_check = shell_exec("php -l " . escapeshellarg($real_path) . " 2>&1");
            if (strpos($syntax_check, 'No syntax errors') === false) {
                throw new RuntimeException("構文エラーが検出されました: {$real_path}");
            }
        }

        if ($options['log_level'] === 'info') {
            error_log("情報: ファイルパスの検証に成功しました: {$real_path}");
        }
        
        return $real_path; // 検証済みパスを返す
    
    } catch (InvalidArgumentException $e) {
            error_log("引数エラー - {$file_name}: " . $e->getMessage());
            throw $e; // 呼び出し元で処理
    
    } catch (LogicException $e) {            
        if ($options['log_level'] === 'info') {
            error_log("情報: ファイルは既に読み込まれています: {$file_name}");
        }
        throw $e; // 呼び出し元で処理    
    
    
    } catch (RuntimeException $e) {
        error_log("ファイル処理エラー - {$file_name}: " . $e->getMessage());
        throw $e; // 呼び出し元で処理
    } catch (Exception $e) {
        $error_msg = "予期しないエラー - {$file_name}: " . get_class($e) . " - " . 
                     $e->getMessage() . "\nTrace: " . $e->getTraceAsString();
        error_log($error_msg);
        throw new RuntimeException("予期しないエラーが発生しました: {$file_name}", 0, $e);
    }
}


$test_cases = [
        // 正常ケース
        ['file' => 'common.php', 'options' => [], 'expected' => 'success'],
        ['file' => 'common.php', 'options' => [], 'expected' => 'already_loaded'],
        
        // エラーケース
        ['file' => 'nonexistent.php', 'options' => [], 'expected' => 'file_not_found'],
        ['file' => 'syntax_error.php', 'options' => [], 'expected' => 'syntax_error'],
        
        // 設定テスト
        ['file' => 'large_file.php', 'options' => ['max_file_size' => 100], 'expected' => 'size_error'],
        ['file' => 'test.txt', 'options' => [], 'expected' => 'extension_error'],
        
        // セキュリティテスト
        ['file' => '../outside.php', 'options' => [], 'expected' => 'security_error'],
    ];


foreach ($test_cases as $i => $test) {
        echo "<h4>テスト " . ($i + 1) . ": {$test['file']}</h4>";
        
     try {
    // 1. 安全なファイルパスの取得
    $safe_path = safe_file_path("{$test['file']}", $test['options']);
    
    // 2. グローバルスコープでrequire_once実行
    require_once $safe_path;
    echo ("✅ パス検証成功: {$safe_path}<br>");

} catch (InvalidArgumentException $e) {
    echo ("引数エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
} catch (LogicException $e) {
    echo ("ℹ️ ファイルは既に読み込み済みです: " . $e->getMessage() . "<br>");

} catch (RuntimeException $e) {
    echo ("ファイル読み込みエラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
} catch (ParseError $e) {
    echo ("構文エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());

} catch (Error $e) {
    echo ("致命的エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());

} catch (Exception $e) {
    echo ("予期しないエラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
}

        echo "<br>";
        echo "<hr>";
}
