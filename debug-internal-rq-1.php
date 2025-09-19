<?php
/**
 * safe_file_path - path validation and normalization
 * 
 * @param string $file_name 読み込むファイル名
 * （関数を実行するディレクトリを基準にした相対パス）
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
// } catch (LogicException $e) {
//     die("ℹ️ ファイルは既に読み込み済みです: " . $e->getMessage() . "<br>");
// } catch (InvalidArgumentException $e) {
//     die("引数エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
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
            if ($options['log_level'] === 'info') {
                throw new LogicException("ファイルは既に読み込まれています: {$real_path}");
            }
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
    
    }catch (LogicException $e) {            
        if ($options['log_level'] === 'info') {
            error_log("情報: ファイルは既に読み込まれています: {$file_name}");
        }
        throw $e; // 呼び出し元で処理    
    
    } catch (InvalidArgumentException $e) {
        error_log("引数エラー - {$file_name}: " . $e->getMessage());
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

function test_safe_file_path() {
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
            $result = safe_file_path($test['file'], $test['options']);
            
            if ($result === true) {
                echo "✅ ファイル読み込み成功<br>";
            } elseif ($result === false) {
                echo "ℹ️ ファイルは既に読み込み済み<br>";
            }
            
        } catch (InvalidArgumentException $e) {
            echo "❌ 引数エラー: " . $e->getMessage() . "<br>";
            
        } catch (RuntimeException $e) {
            echo "❌ 実行時エラー: " . $e->getMessage() . "<br>";
        }
        
        echo "<br>";
    }
}
echo 'テスト開始<br>';
    test_safe_file_path();
echo 'テスト終了<br>';


/**
 * detailed_syntax_check - 詳細な構文チェック
 */
function detailed_syntax_check($file_path) {
    echo "<h2>詳細構文チェック: " . basename($file_path) . "</h2>";
    
    // 1. 基本的な構文チェック
    $syntax_result = shell_exec("php -l " . escapeshellarg($file_path) . " 2>&1");
    echo "<h3>1. PHP構文チェック結果:</h3>";
    echo "<pre style='background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px;'>";
    echo htmlspecialchars($syntax_result);
    echo "</pre>";
    
    // 2. ファイル内容の分析
    if (is_readable($file_path)) {
        $content = file_get_contents($file_path);
        echo "<h3>2. ファイル基本情報:</h3>";
        echo "ファイルサイズ: " . number_format(strlen($content)) . " bytes<br>";
        echo "行数: " . substr_count($content, "\n") . " 行<br>";
        echo "文字エンコーディング: " . mb_detect_encoding($content, 'UTF-8,Shift_JIS,EUC-JP,ASCII', true) . "<br>";
        
        // 3. 構造的問題のチェック
        echo "<h3>3. 構造チェック:</h3>";
        check_structure_issues($content);
        
        // 4. 一般的な問題のチェック
        echo "<h3>4. 一般的な問題チェック:</h3>";
        check_common_issues($content);
    } else {
        echo "<h3>❌ ファイルを読み取れません</h3>";
    }
    
    // 5. 構文エラーがある場合の詳細
    if (strpos($syntax_result, 'No syntax errors') === false) {
        echo "<h3>5. エラー詳細分析:</h3>";
        parse_syntax_error($syntax_result, $file_path);
    }
}

/**
 * check_structure_issues - 構造的問題をチェック
 */
function check_structure_issues($content) {
    $issues = [];
    
    // 括弧のバランスチェック
    $brackets = [
        '{' => '}',
        '(' => ')',
        '[' => ']'
    ];
    
    foreach ($brackets as $open => $close) {
        $open_count = substr_count($content, $open);
        $close_count = substr_count($content, $close);
        
        if ($open_count !== $close_count) {
            $issues[] = "括弧のバランス異常: '{$open}' が {$open_count}個、'{$close}' が {$close_count}個";
        }
    }
    
    // クォートのバランスチェック
    $quotes = ['"', "'"];
    foreach ($quotes as $quote) {
        $count = substr_count($content, $quote);
        if ($count % 2 !== 0) {
            $issues[] = "クォートのバランス異常: '{$quote}' が奇数個 ({$count}個)";
        }
    }
    
    // PHP開始・終了タグのチェック
    if (!preg_match('/^\s*<\?php/', $content)) {
        $issues[] = "PHPオープンタグ(<?php)が見つかりません";
    }
    
    if (empty($issues)) {
        echo "✅ 構造的問題は見つかりませんでした<br>";
    } else {
        echo "<ul>";
        foreach ($issues as $issue) {
            echo "<li>❌ {$issue}</li>";
        }
        echo "</ul>";
    }
}

/**
 * check_common_issues - 一般的な問題をチェック
 */
function check_common_issues($content) {
    $lines = explode("\n", $content);
    $issues = [];
    
    foreach ($lines as $line_num => $line) {
        $line_number = $line_num + 1;
        $trimmed = trim($line);
        
        // 空行やコメント行はスキップ
        if (empty($trimmed) || strpos($trimmed, '//') === 0 || strpos($trimmed, '#') === 0) {
            continue;
        }
        
        // よくある問題のパターン
        $patterns = [
            '/[^\s;]\s*$/' => 'セミコロンが不足している可能性',
            '/\$[a-zA-Z_]\w*\s*[^=;,\s)]/' => '変数の使用方法に問題がある可能性',
            '/function\s+\w+\s*\([^)]*[^)]$/' => '関数定義の括弧が不完全',
            '/if\s*[^(]/' => 'if文の構文エラー',
            '/for\s*[^(]/' => 'for文の構文エラー',
            '/while\s*[^(]/' => 'while文の構文エラー',
        ];
        
        foreach ($patterns as $pattern => $description) {
            if (preg_match($pattern, $trimmed)) {
                $issues[] = "行 {$line_number}: {$description}";
                break;
            }
        }
    }
    
    if (empty($issues)) {
        echo "✅ 一般的な問題は見つかりませんでした<br>";
    } else {
        echo "<ul>";
        foreach ($issues as $issue) {
            echo "<li>⚠️ {$issue}</li>";
        }
        echo "</ul>";
    }
}

/**
 * parse_syntax_error - 構文エラーを解析
 */
function parse_syntax_error($syntax_result, $file_path) {
    // エラーメッセージから詳細情報を抽出
    $patterns = [
        '/syntax error, unexpected (.*?) in (.*?) on line (\d+)/' => ['type' => 'unexpected', 'token' => 1, 'file' => 2, 'line' => 3],
        '/Parse error: (.*?) in (.*?) on line (\d+)/' => ['type' => 'parse', 'error' => 1, 'file' => 2, 'line' => 3],
        '/Fatal error: (.*?) in (.*?) on line (\d+)/' => ['type' => 'fatal', 'error' => 1, 'file' => 2, 'line' => 3],
    ];
    
    foreach ($patterns as $pattern => $groups) {
        if (preg_match($pattern, $syntax_result, $matches)) {
            echo "<h4>エラー詳細:</h4>";
            echo "エラータイプ: " . strtoupper($groups['type']) . "<br>";
            if (isset($groups['token'])) {
                echo "予期しないトークン: " . $matches[$groups['token']] . "<br>";
            }
            if (isset($groups['error'])) {
                echo "エラー内容: " . $matches[$groups['error']] . "<br>";
            }
            echo "エラー行: " . $matches[$groups['line']] . "<br>";
            
            // エラー行周辺を表示
            show_code_context($file_path, $matches[$groups['line']]);
            
            break;
        }
    }
}

function convert_encoding_to_utf8($text) {
    if (empty($text)) {
        return $text;
    }
    
    // 文字エンコーディングの検出
    $detected_encoding = mb_detect_encoding($text, ['UTF-8', 'Shift_JIS', 'CP932', 'EUC-JP', 'ASCII'], true);
    
    // UTF-8でない場合は変換
    if ($detected_encoding && $detected_encoding !== 'UTF-8') {
        $converted = mb_convert_encoding($text, 'UTF-8', $detected_encoding);
        return $converted;
    }
    
    return $text;
}


/**
 * get_mamp_php_path - MAMPのPHP実行ファイルパスを取得
 */
function get_mamp_php_path() {
    // 現在のPHP実行ファイルのパス（最も確実）
    $current_php = PHP_BINARY;
    
    if (file_exists($current_php) && is_executable($current_php)) {
        return $current_php;
    }
    
    // MAMP標準パス候補
    $mamp_php_paths = [
        'C:\MAMP\bin\php\php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION . '\php.exe',
        'C:\MAMP\bin\php\php8.2.0\php.exe',
        'C:\MAMP\bin\php\php8.1.13\php.exe',
        'C:\MAMP\bin\php\php8.0.28\php.exe',
        'C:\MAMP\bin\php\php7.4.33\php.exe',
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php.exe',
    ];
    
    foreach ($mamp_php_paths as $path) {
        if (file_exists($path) && is_executable($path)) {
            return $path;
        }
    }
    
    return null;
}

/**
 * safe_syntax_check - 改良版構文チェック
 */
function safe_syntax_check($real_path) {
    echo "<h3>構文チェック開始: " . basename($real_path) . "</h3>";
    
    // PHP実行ファイルのパスを取得
    $php_executable = get_mamp_php_path();
    
    if (!$php_executable) {
        echo "❌ PHP実行ファイルが見つかりません<br>";
        echo "現在のPHP_BINARY: " . PHP_BINARY . "<br>";
        echo "⚠️ 構文チェックをスキップします<br>";
        return true; // エラーではなく、スキップとして扱う
    }
    
    echo "✅ 使用するPHP: {$php_executable}<br>";
    
    // コマンド実行（フルパス使用）
    $command = '"' . $php_executable . '" -l "' . $real_path . '" 2>&1';
    echo "実行コマンド: {$command}<br>";
    
    $syntax_check = shell_exec($command);
    
    if ($syntax_check === null) {
        echo "❌ コマンド実行に失敗しました<br>";
        return false;
    }
    
    // 文字エンコーディング変換
    $syntax_check_utf8 = convert_encoding_to_utf8($syntax_check);
    
    echo "<pre style='background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px;'>";
    echo htmlspecialchars($syntax_check_utf8);
    echo "</pre>";
    
    // 成功判定
    if (strpos($syntax_check_utf8, 'No syntax errors') !== false) {
        echo "<h4>✅ 構文チェック通過</h4>";
        return true;
    } else {
        echo "<h4>❌ 構文エラーが検出されました</h4>";
        
        // エラー行の抽出
        if (preg_match('/on line (\d+)/', $syntax_check_utf8, $matches)) {
            $error_line = $matches[1];
            echo "エラー行: {$error_line}<br>";
            
            // エラー行周辺のコードを表示（後で実装）
            if (function_exists('show_code_context')) {
                show_code_context($real_path, $error_line);
            }
        }
        
        return false;
    }
}
/**
 * internal_require_once - アプリ内のファイルを安全に読み込む関数
 * 
 * @param string $file_name 読み込むファイル名（関数が存在するディレクトリを基準）
 * @param array $options オプション設定
 * @throws InvalidArgumentException 引数が無効な場合
 * @throws RuntimeException ファイル操作エラーの場合
 * @return bool 読み込み成功時true、既に読み込み済みの場合false
 */
function internal_require_once($file_name, $options = []) {
    // デフォルト設定
    $default_options = [
        'max_file_size' => 1024 * 1024,  // 1MB
        'allowed_extensions' => ['php', 'inc'],
        'check_syntax' => false,  // 事前構文チェック（重い処理）
        'log_level' => 'error'    // ログレベル
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
            if ($options['log_level'] === 'debug') {
                error_log("情報: ファイルは既に読み込まれています: {$real_path}");
            }
            return false; // 既に読み込み済み
        }
        
        // 11. 事前構文チェック（オプション）
        if ($options['check_syntax']) {
            $syntax_check = shell_exec("php -l " . escapeshellarg($real_path) . " 2>&1");
            if (strpos($syntax_check, 'No syntax errors') === false) {
                throw new RuntimeException("構文エラーが検出されました: {$real_path}");
            }
        }
        
        // 12. ファイル読み込み実行
        require_once $real_path;
        
        // 13. 読み込み後の検証
        $last_error = error_get_last();
        if ($last_error && strpos($last_error['file'], $real_path) !== false) {
            throw new RuntimeException("ファイル読み込み中にエラーが発生しました: " . $last_error['message']);
        }
        
        if ($options['log_level'] === 'info') {
            error_log("情報: ファイルの読み込みに成功しました: {$real_path}");
        }
        
        return true; // 読み込み成功
        
    } catch (InvalidArgumentException $e) {
        error_log("引数エラー - {$file_name}: " . $e->getMessage());
        throw $e; // 呼び出し元で処理
        
    } catch (RuntimeException $e) {
        error_log("ファイル処理エラー - {$file_name}: " . $e->getMessage());
        throw $e; // 呼び出し元で処理
        
    } catch (ParseError $e) {
        $error_msg = "構文エラー - {$file_name}: " . $e->getMessage() . 
                     " at line " . $e->getLine() . " in " . $e->getFile();
        error_log($error_msg);
        throw new RuntimeException("ファイルに構文エラーがあります: {$file_name}", 0, $e);
        
    } catch (Error $e) {
        $error_msg = "致命的エラー - {$file_name}: " . $e->getMessage() . 
                     " in " . $e->getFile() . " at line " . $e->getLine();
        error_log($error_msg);
        throw new RuntimeException("ファイル読み込み中に致命的エラーが発生しました: {$file_name}", 0, $e);
        
    } catch (Exception $e) {
        $error_msg = "予期しないエラー - {$file_name}: " . get_class($e) . " - " . 
                     $e->getMessage() . "\nTrace: " . $e->getTraceAsString();
        error_log($error_msg);
        throw new RuntimeException("予期しないエラーが発生しました: {$file_name}", 0, $e);
    }
}



