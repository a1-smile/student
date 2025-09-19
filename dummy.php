<?php
function safe_require_once($file_name) {
    try {
        //  __DIR__ は、現在のスクリプトが存在するディレクトリのパスを取得します。
        $common_file = __DIR__ . "/{$file_name}";
        
        // セキュリティチェック：ディレクトリトラバーサル対策
        //  realpath() は、指定されたパスの正規化を行い、
        //  シンボリックリンクを解決し、相対パスを絶対パスに変換します。
        $real_common_path = realpath($common_file);
        $real_base_path = realpath(__DIR__);
        
        if ($real_common_path === false) {
            throw new RuntimeException("{$file_name}のパスが無効です");
        }
        //  strpos() は、文字列内での位置を検索します。
        //  real_common_path が real_base_path のサブパスであることを確認します。
        //  これにより、ディレクトリトラバーサル攻撃を防ぎます。
        // strpos(検索対象の文字列, 検索する文字列);
    // 戻り値：見つかった位置（0から始まる）、見つからない場合はfalse

    // echo strpos("Hello World", "World"); 6 (6文字目から"World"が始まる)
    // echo strpos("Hello World", "Hello"); 0 (0文字目から"Hello"が始まる)
    // echo strpos("Hello World", "xyz");   false (見つからない)

    //  DIRECTORY_SEPARATOR は、OSに依存しないディレクトリ区切り文字を提供します。
    //  これを付け加えることで、現在のディレトリーに
    //  存在するファイルであることをより厳密にチェックします。
    //  例えば、real_base_path が /var/www/html/student であれば、
    //  /var/www/html/student_common.php のようなファイルが
    //  存在してもマッチしないようにします。
        if (strpos($real_common_path, $real_base_path. DIRECTORY_SEPARATOR) !== 0) {
        // セキュリティ攻撃の詳細ログ
        $security_context = [
            'attack_type' => 'DIRECTORY_TRAVERSAL',
            'attempted_path' => $common_file,
            'real_common_path' => $real_common_path,
            'real_base_path' => $real_base_path,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s'),
            'session_id' => session_id()
        ];
        
        // 重要なセキュリティアラートとしてログ記録
        error_log("🚨 SECURITY ALERT - DIRECTORY TRAVERSAL: " . json_encode($security_context, JSON_UNESCAPED_UNICODE));
        
        // セッションを破棄（攻撃者のセッションを無効化）
        session_unset();
        session_destroy();
                // 攻撃検出IDを生成
        $attack_id = uniqid('ATTACK_', true);
        
        // 即座に処理を終了
        die("セキュリティ攻撃が検出されました。<br>
             この操作は記録され、管理者に通報されました。<br>
             攻撃検出ID: {$attack_id}<br>
             アクセスをブロックします。");
        }
        
        
        // ファイル存在・権限チェック
        if (!file_exists($real_common_path)) {
            throw new RuntimeException('common.phpが見つかりません: ' . $real_common_path.'<br><br>');
        }
        
        if (!is_readable($real_common_path)) {
            throw new RuntimeException('common.phpの読み込み権限がありません: ' . $real_common_path);
        }
        
        if (!is_file($real_common_path)) {
            throw new RuntimeException('common.phpが通常ファイルではありません: ' . $real_common_path);
        }
        
        // ファイルサイズチェック（異常に大きなファイルの検出）
        $file_size = filesize($real_common_path);
        if ($file_size === false) {
            throw new RuntimeException('common.phpのファイルサイズを取得できません');
        }
        
        if ($file_size > 1024 * 1024) { // 1MB以上
            throw new RuntimeException('common.phpのファイルサイズが異常です: ' . $file_size . ' bytes');
        }
        
        if ($file_size === 0) {
            throw new RuntimeException('common.phpが空ファイルです');
        }
        
        // ファイル読み込み
        require_once $real_common_path;
            
    } catch (ParseError $e) {
        // PHP構文エラー
        error_log("構文エラー - common.php - ファイル: " . $e->getFile() . ", 行: " . $e->getLine() . ", メッセージ: " . $e->getMessage());
        die('設定ファイルに構文エラーがあります。管理者にお問い合わせください。<br>
            エラーID: ' . uniqid());
            
    } catch (Error $e) {
        // Fatal Error
        error_log("致命的エラー - common.php - メッセージ: " . $e->getMessage() . ", ファイル: " . $e->getFile() . ", 行: " . $e->getLine());
        die('システムの初期化でエラーが発生しました。管理者にお問い合わせください。<br>
            エラーID: ' . uniqid());
            
    } catch (RuntimeException $e) {
        // ファイル関連・初期化エラー
        error_log("初期化エラー - common.php - メッセージ: " . $e->getMessage() . ", 現在ディレクトリ: " . __DIR__);
        die('システムファイルの読み込みまたは初期化に失敗しました。<br>
            管理者にお問い合わせください。<br>
            エラーID: ' . uniqid());
            
    } catch (Exception $e) {
        // その他の予期しないエラー
        error_log("予期しないエラー - common.php読み込み - メッセージ: " . $e->getMessage() . ", トレース: " . $e->getTraceAsString());
        die('システムの初期化で予期しないエラーが発生しました。管理者にお問い合わせください。<br>
            エラーID: ' . uniqid());
    }
}

function safe_require_once_debug($file_name) {
    echo "<h3>ファイル読み込みテスト: {$file_name}</h3>";
    
    try {
        $common_file = __DIR__ . "/{$file_name}";
        echo "1. ファイルパス: {$common_file}<br>";
        
        $real_common_path = realpath($common_file);
        $real_base_path = realpath(__DIR__);
        
        echo "2. 正規化パス: {$real_common_path}<br>";
        echo "3. ベースパス: {$real_base_path}<br>";
        
        if ($real_common_path === false) {
            echo "❌ ファイルが存在しません<br>";
            throw new RuntimeException("{$file_name}のパスが無効です");
        }
        
        echo "4. ディレクトリトラバーサルチェック...<br>";
        if (strpos($real_common_path, $real_base_path. DIRECTORY_SEPARATOR) !== 0) {
            echo "❌ ディレクトリトラバーサル攻撃検出<br>";
            die("セキュリティ攻撃が検出されました");
        }
        
        echo "5. ファイル存在チェック...<br>";
        if (!file_exists($real_common_path)) {
            echo "❌ ファイルが存在しません<br>";
            throw new RuntimeException('ファイルが見つかりません: ' . $real_common_path);
        }
        
        echo "6. ファイルサイズチェック...<br>";
        $file_size = filesize($real_common_path);
        echo "ファイルサイズ: {$file_size} bytes<br>";
        
        echo "7. ファイル読み込み開始...<br>";
        require_once $real_common_path;
        echo "✅ ファイル読み込み成功<br>";
        
    } catch (ParseError $e) {
        echo "❌ PHP構文エラー<br>";
        echo "ファイル: " . $e->getFile() . "<br>";
        echo "行: " . $e->getLine() . "<br>";
        echo "メッセージ: " . $e->getMessage() . "<br>";
        
    } catch (Error $e) {
        echo "❌ 致命的エラー<br>";
        echo "メッセージ: " . $e->getMessage() . "<br>";
        echo "ファイル: " . $e->getFile() . "<br>";
        echo "行: " . $e->getLine() . "<br>";
        
    } catch (RuntimeException $e) {
        echo "❌ 実行時エラー<br>";
        echo "メッセージ: " . $e->getMessage() . "<br>";
        
    } catch (Exception $e) {
        echo "❌ その他のエラー<br>";
        echo "タイプ: " . get_class($e) . "<br>";
        echo "メッセージ: " . $e->getMessage() . "<br>";
    }
    
    echo "<hr>";
}


function create_test_files() {
    // 構文エラーファイル作成
    file_put_contents('syntax_error.php', '<?php echo "syntax error"'); // セミコロンなし
    
    // 大きなファイル作成
    if (!file_exists('large_file.php')) {
        $large_content = "<?php\n" . str_repeat("// " . str_repeat("Large file content. ", 100) . "\n", 1000);
        file_put_contents('large_file.php', $large_content);
    }
    
    // テストディレクトリ作成
    if (!is_dir('test_directory')) {
        mkdir('test_directory');
    }
    
    // 親ディレクトリのテストファイル
    if (!file_exists('../outside.php')) {
        file_put_contents('../outside.php', '<?php echo "Outside file accessed!";');
    }
    
    echo "テストファイルを作成しました。<br><hr>";
}


function comprehensive_file_test() {
    $test_cases = [
        // 正常ケース
        ['file' => 'common.php', 'expected' => 'success', 'description' => '正常なファイル'],
        
        // エラーケース
        ['file' => 'nonexistent.php', 'expected' => 'runtime_error', 'description' => '存在しないファイル'],
        ['file' => 'syntax_error.php', 'expected' => 'parse_error', 'description' => '構文エラーファイル'],
        ['file' => 'large_file.php', 'expected' => 'runtime_error', 'description' => '大きなファイル'],
        ['file' => '', 'expected' => 'runtime_error', 'description' => '空文字列'],
        
        // セキュリティテスト
        ['file' => '../outside.php', 'expected' => 'security_alert', 'description' => 'ディレクトリトラバーサル'],
        ['file' => '../../etc/passwd', 'expected' => 'security_alert', 'description' => 'システムファイル'],
        ['file' => '..\\..\\Windows\\System32\\drivers\\etc\\hosts', 'expected' => 'security_alert', 'description' => 'Windows形式'],
        
        // 特殊ケース
        ['file' => 'test_directory', 'expected' => 'runtime_error', 'description' => 'ディレクトリ指定'],
        ['file' => 'null_byte_file.php' . "\0" . '.txt', 'expected' => 'runtime_error', 'description' => 'ヌルバイト攻撃'],
    ];
    
    echo "<h2>包括的ファイル読み込みテスト</h2>";
    
    foreach ($test_cases as $i => $test) {
        echo "<h3>テスト " . ($i + 1) . ": {$test['description']}</h3>";
        echo "ファイル: {$test['file']}<br>";
        echo "期待結果: {$test['expected']}<br>";
        
        // テスト実行をtry-catchで囲んで結果を記録
        try {
            ob_start(); // 出力バッファ開始
            safe_require_once_debug($test['file']);
            $output = ob_get_clean(); // 出力を取得
            
            echo $output;
            echo "<span style='color: green;'>✅ テスト完了</span><br>";
            
        } catch (Exception $e) {
            $output = ob_get_clean();
            echo $output;
            echo "<span style='color: red;'>❌ 予期しない例外: " . $e->getMessage() . "</span><br>";
        }
        
        echo "<hr>";
    }
}


function security_test_cases() {
    $attack_patterns = [
        '../common.php',           // 基本的なディレクトリトラバーサル
        '../../outside.php',       // 2階層上
        '../../../etc/passwd',     // システムファイル
        '..\\..\\Windows\\system.ini', // Windows形式
        '....//....//etc/passwd',  // 二重エンコード
        '%2e%2e%2f%2e%2e%2fpasswd', // URLエンコード
        '..%2fcommon.php',         // 部分URLエンコード
        '..%5c..%5cWindows%5csystem.ini', // バックスラッシュURLエンコード
        '/etc/passwd',             // 絶対パス
        'C:\\Windows\\system.ini', // Windows絶対パス
        '\\\\server\\share\\file', // UNC パス
        'file:///etc/passwd',      // ファイルURL
        'common.php\0.txt',        // ヌルバイト
        'common.php%00.txt',       // ヌルバイトURLエンコード
    ];
    
    echo "<h2>セキュリティテスト - ディレクトリトラバーサル攻撃</h2>";
    
    foreach ($attack_patterns as $i => $pattern) {
        echo "<h4>攻撃パターン " . ($i + 1) . ": " . htmlspecialchars($pattern) . "</h4>";
        
        try {
            ob_start();
            safe_require_once_debug($pattern);
            $output = ob_get_clean();
            echo $output;
            echo "<span style='color: red;'>🚨 警告: この攻撃がブロックされませんでした！</span><br>";
        } catch (Exception $e) {
            ob_get_clean();
            echo "<span style='color: green;'>✅ 攻撃が適切にブロックされました</span><br>";
        }
        
        echo "<hr>";
    }
}


function performance_test() {
    echo "<h2>パフォーマンステスト</h2>";
    
    $test_files = ['common.php', 'nonexistent.php', '../outside.php'];
    
    foreach ($test_files as $file) {
        echo "<h4>ファイル: {$file}</h4>";
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        try {
            ob_start();
            safe_require_once_debug($file);
            ob_end_clean();
        } catch (Exception $e) {
            ob_end_clean();
        }
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage();
        
        $execution_time = ($end_time - $start_time) * 1000; // ミリ秒
        $memory_used = $end_memory - $start_memory;
        
        echo "実行時間: " . number_format($execution_time, 2) . " ms<br>";
        echo "メモリ使用量: " . number_format($memory_used) . " bytes<br>";
        echo "<hr>";
    }
}

// 1. テストファイルの作成
create_test_files();

// 2. 基本機能テスト
safe_require_once_debug('common.php');
safe_require_once_debug('nonexistent.php');
safe_require_once_debug('syntax_error.php');  // 新しい構文エラーファイル
safe_require_once_debug('../outside.php');
// 3. セキュリティテスト
//  コメントアウト  security_test_cases();

// 4. 包括的テスト
//  コメントアウト  comprehensive_file_test();

// 5. パフォーマンステスト
//  コメントアウト  performance_test();
?>
<?php
// 内部用：セキュリティチェックなし
function internal_require_once($file_name) {
    try {
        $file_path = __DIR__ . "/{$file_name}";
        $real_path = realpath($file_path);
        
        if ($real_path === false) {
            throw new RuntimeException("{$file_name}のパスが無効です");
        }
        
        if (!file_exists($real_path)) {
            throw new RuntimeException('ファイルが見つかりません: ' . $real_path);
        }
        
        require_once $real_path;
        
    } catch (Exception $e) {
        error_log("ファイル読み込みエラー: " . $e->getMessage());
        die('システムファイルの読み込みに失敗しました。');
    }
}

// 外部入力用：セキュリティチェックあり
function safe_require_once($file_name) {
    // 完全なセキュリティチェック付き
    // ... 元のコード ...
}

// 使用例
internal_require_once('common.php');      // 内部用
safe_require_once($_GET['module']);       // 外部入力用（将来）