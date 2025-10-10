<?php
require_once 'user-agent-check.php';
require_once 'exceptions.php'; // SessionHijackingExceptionが定義されているファイル

/**
 * userAgentCheck関数のテストクラス
 */
class UserAgentCheckTest {
    
    private $originalServer;
    private $originalSession;
    
    public function setUp(): void {
    // セッションを開始（まだ開始されていない場合）
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // 元の$_SERVERと$_SESSIONを保存
    $this->originalServer = $_SERVER;
    $this->originalSession = $_SESSION ?? [];
    
    // セッションをクリア
    $_SESSION = [];
    
    // 基本的な$_SERVER設定
    $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/test';
    $_SERVER['REQUEST_TIME'] = time();
}
    
    public function tearDown(): void {
        // $_SERVERと$_SESSIONを復元
        $_SERVER = $this->originalServer;
        $_SESSION = $this->originalSession;
    }
    
    /**
     * 初回アクセス時のテスト（User-Agentが設定される）
     */
    public function testFirstVisit(): void {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        
        // 初回はセッションにUser-Agentが設定されていない
        unset($_SESSION['user_agent']);
        
        userAgentCheck();
        
        // セッションにUser-Agentが設定されることを確認
        assert(isset($_SESSION['user_agent']), "セッションにuser_agentが設定されるべき");
        assert($_SESSION['user_agent'] === $_SERVER['HTTP_USER_AGENT'], "設定されたuser_agentが正しくない");
        
        echo "✓ 初回アクセステスト: 成功\n";
    }
    
    /**
     * 正常なUser-Agent継続のテスト
     */
    public function testValidUserAgent(): void {
        $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        
        $_SESSION['user_agent'] = $user_agent;
        $_SERVER['HTTP_USER_AGENT'] = $user_agent;
        
        // 例外が投げられないことを確認
        try {
            userAgentCheck();
            echo "✓ 正常なUser-Agentテスト: 成功\n";
        } catch (Exception $e) {
            assert(false, "正常なUser-Agentで例外が投げられた: " . $e->getMessage());
        }
    }
    
    /**
     * User-Agentが変更された場合のテスト
     */
    public function testChangedUserAgent(): void {
        $_SESSION['user_agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36';
        
        try {
            userAgentCheck();
            assert(false, "User-Agent変更で例外が投げられるべき");
        } catch (SessionHijackingException $e) {
            assert($e->getMessage() === 'セッションハイジャックが疑われます', "期待されるエラーメッセージ");
            
            // コンテキストの確認
            $context = $e->getContext();
            assert(isset($context['stored_user_agent']), "stored_user_agentがコンテキストに含まれるべき");
            assert(isset($context['current_user_agent']), "current_user_agentがコンテキストに含まれるべき");
            assert(isset($context['ip_address']), "ip_addressがコンテキストに含まれるべき");
            
            echo "✓ User-Agent変更テスト: 成功\n";
        }
    }
    
    /**
     * User-Agentが空の場合のテスト
     */
    public function testEmptyUserAgent(): void {
        $_SESSION['user_agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $_SERVER['HTTP_USER_AGENT'] = '';
        
        try {
            userAgentCheck();
            assert(false, "空のUser-Agentで例外が投げられるべき");
        } catch (SessionHijackingException $e) {
            assert($e->getMessage() === 'User-Agentが空です', "期待されるエラーメッセージ");
            echo "✓ 空のUser-Agentテスト: 成功\n";
        }
    }
    
    /**
     * HTTP_USER_AGENTが存在しない場合のテスト
     */
    public function testMissingUserAgent(): void {
        $_SESSION['user_agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        unset($_SERVER['HTTP_USER_AGENT']);
        
        try {
            userAgentCheck();
            assert(false, "HTTP_USER_AGENTなしで例外が投げられるべき");
        } catch (SessionHijackingException $e) {
            assert($e->getMessage() === 'User-Agentが空です', "期待されるエラーメッセージ");
            echo "✓ HTTP_USER_AGENT未設定テスト: 成功\n";
        }
    }
    
    /**
     * 初回アクセス時にHTTP_USER_AGENTが空の場合のテスト
     */
    public function testFirstVisitWithEmptyUserAgent(): void {
        unset($_SESSION['user_agent']);
        $_SERVER['HTTP_USER_AGENT'] = '';
        
        // 初回は設定されるだけなので例外は投げられない
        userAgentCheck();
        
        assert($_SESSION['user_agent'] === '', "空のUser-Agentが設定されるべき");
        echo "✓ 初回アクセス時空のUser-Agentテスト: 成功\n";
    }
    
    /**
     * セッションIDなしの場合のテスト
     */

/**
 * セッションIDの確認テスト
 */
public function testWithoutSessionId(): void {
    $_SESSION['user_agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
    $_SERVER['HTTP_USER_AGENT'] = 'Different User Agent';
    
    try {
        userAgentCheck();
        assert(false, "例外が投げられるべき");
    } catch (SessionHijackingException $e) {
        $context = $e->getContext();
        
        // セッションIDがコンテキストに含まれることを確認
        assert(isset($context['session_id']), "session_idがコンテキストに含まれるべき");
        
        // セッションが開始されていれば実際のセッションIDが設定される
        // セッションが開始されていなければ'unknown'が設定される
        $actual_session_id = session_id();
        if (!empty($actual_session_id)) {
            assert($context['session_id'] === $actual_session_id, "session_idが一致するべき");
        } else {
            assert($context['session_id'] === 'unknown', "session_idが'unknown'であるべき");
        }
        
        echo "✓ セッションIDテスト: 成功 (session_id: " . $context['session_id'] . ")\n";
    }
}
    
    /**
     * 特殊文字を含むUser-Agentのテスト
     */
    public function testSpecialCharactersInUserAgent(): void {
        $special_ua = 'Mozilla/5.0 (Linux; Android 9; "Mobile") AppleWebKit/537.36 <script>alert("xss")</script>';
        
        $_SESSION['user_agent'] = $special_ua;
        $_SERVER['HTTP_USER_AGENT'] = $special_ua;
        
        try {
            userAgentCheck();
            echo "✓ 特殊文字User-Agentテスト: 成功\n";
        } catch (Exception $e) {
            assert(false, "特殊文字User-Agentで予期しない例外: " . $e->getMessage());
        }
    }

/**
 * セッション破棄後のテスト
 */
/**
 * セッションデータ削除後のテスト
 */
/**
 * セッションデータ削除後のテスト
 */
public function testAfterSessionDataDestroy(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['user_agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
    $_SERVER['HTTP_USER_AGENT'] = 'Different User Agent';
    
    // セッションデータのみ削除（IDは残る）
    session_destroy();
    
    try {
        userAgentCheck();
        assert(false, "例外が投げられるべき");
    } catch (SessionHijackingException $e) {
        $context = $e->getContext();
        
        // PHPの仕様通り、セッションIDは残る
        $current_session_id = session_id();
        assert($context['session_id'] === $current_session_id, 
               "session_idが現在のセッションIDと一致するべき");
        
        echo "✓ セッションデータ削除後テスト: 成功\n";
    }
    
    session_start();
}
    /**
     * 全テストを実行
     */

public function runAllTests(): void {
    echo "<pre>=== User-Agent Check テスト開始 ===\n";
    
    try {
        $this->setUp();
        $this->testFirstVisit();
        
        $this->setUp();
        $this->testValidUserAgent();
        
        $this->setUp();
        $this->testChangedUserAgent();
        
        $this->setUp();
        $this->testEmptyUserAgent();
        
        $this->setUp();
        $this->testMissingUserAgent();
        
        $this->setUp();
        $this->testFirstVisitWithEmptyUserAgent();
        
        $this->setUp();
        $this->testWithoutSessionId();
        
        $this->setUp();
        $this->testSpecialCharactersInUserAgent();
        
        // 新しいテストを追加
        $this->setUp();
        $this->testAfterSessionDataDestroy();
        
        echo "\n=== 全テスト成功！ ===\n";
    } finally {
        $this->tearDown();
    }
    echo "</pre>";
}
}

// SessionHijackingExceptionクラスのモック（実際のクラスがない場合）
if (!class_exists('SessionHijackingException')) {
    class SessionHijackingException extends Exception {
        private $context;
        
        public function __construct($message, $code = 0, $context = [], $previous = null) {
            parent::__construct($message, $code, $previous);
            $this->context = $context;
        }
        
        public function getContext() {
            return $this->context;
        }
    }
}

if (!class_exists('SecurityException')) {
    class SecurityException {
        const SEC_SESSION_HIJACK = 1001;
    }
}

// テスト実行
$test = new UserAgentCheckTest();
$test->runAllTests();