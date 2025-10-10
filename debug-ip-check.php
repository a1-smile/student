<?php
function get_ip_prefix_for_session(int $ipv4_blocks = 2, int $ipv6_blocks = 3): string {
    // 1. $_SERVER['REMOTE_ADDR']からIPを取得
    if (!isset($_SERVER['REMOTE_ADDR']) || empty($_SERVER['REMOTE_ADDR'])) {
        throw new RuntimeException('IPアドレスが取得できません');
    }
    
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // IPv4-mapped IPv6アドレスの処理（例: ::ffff:192.0.2.128）
    if (strpos($ip, '::ffff:') !== false) {
        // 最後の':'以降を取り出してIPv4として扱う
        $v4 = substr($ip, strrpos($ip, ':') + 1);
        if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = $v4;
        }
    }
    
    // 2. IPv4の場合の処理
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        // '.'で区切られたIPを配列に変換
        $parts = explode('.', $ip);
        // $takeを$ipv4_blocks、配列の要素数のどちらか小さい値に設定
        $take = min($ipv4_blocks, count($parts));
        $take = max(1, $take); // 最低1ブロックは取る
        
        return implode('.', array_slice($parts, 0, $take));
    }
    
    // IPv6の場合の処理
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // ':'で区切られたIPを配列に変換
        $parts = explode(':', $ip);
        // 空の要素を除去（::の部分対応）
        $trimmed_parts = array_filter($parts, fn($p) => $p !== '');
        // 配列のインデックスを振り直す
        $parts_filtered = array_values($trimmed_parts);
        //$parts_filtered = array_values(array_filter($parts, fn($p) => $p !== ''));
        // $takeを$ipv6_blocks、配列の要素数のどちらか小さい値に設定
        $take = min($ipv6_blocks, count($parts_filtered));
        $take = max(1, $take); // 最低1ブロックは取る
        
        return implode(':', array_slice($parts_filtered, 0, $take));
    }
    
    // IPv4でもIPv6でもない場合は例外を投げる
    throw new InvalidArgumentException('無効なIPアドレスです: ' . $ip);
}
/**
 * ip_prefix のチェック
 * @param int|null $ipv4_blocks IPv4の場合に何ブロックまで取るか（1-4）。nullの場合は環境変数またはデフォルト値を使用
 * @param int|null $ipv6_blocks IPv6の場合に何ブロックまで取るか（1-8）。nullの場合は環境変数またはデフォルト値を使用
 * @throws SessionHijackingException セッションハイジャック検出時
 */
function ip_check_for_session(int $ipv4_blocks = null, int $ipv6_blocks = null): void {
    // 環境に応じたデフォルト値
    $ipv4_blocks = $ipv4_blocks ?? ($_ENV['IP_CHECK_IPV4_BLOCKS'] ?? 2);
    $ipv6_blocks = $ipv6_blocks ?? ($_ENV['IP_CHECK_IPV6_BLOCKS'] ?? 3);

    $current_prefix = get_ip_prefix_for_session($ipv4_blocks, $ipv6_blocks);

    // 初回アクセス時の処理
    if (!isset($_SESSION['ip_prefix'])) {
        
            $_SESSION['ip_prefix'] = $current_prefix;
        
    } else {
        // 既存のセッションとの比較
        $stored_prefix = $_SESSION['ip_prefix'];
        $result = $current_prefix === $stored_prefix;
        if (!$result) {
            //  IPアドレスの先頭部分が異なる場合はコンテキストを記録して、
            //  SessionHijackingExceptionをスロー
            $context = [
                'previous_ip_prefix' => $stored_prefix,
                'current_ip_prefix' => $current_prefix,
                'session_id' => session_id(),
            ];
            throw new SessionHijackingException('IPアドレスが変更されました', SecurityException::SEC_SESSION_HIJACK, $context);
        }
    }

}
//  使用例
// //  セッションスタート
// session_start();
// try {
//     // 現在のIPアドレスの先頭部分を取得
//     ip_check_for_session();
// } catch (SessionHijackingException $e) {
//     $log_message = $e->getLogMessage();
//     error_log($log_message);
    
//     //  session 完全廃棄
//     session_unset();
//     session_destroy();
    
//     die('セッションハイジャック攻撃検出<br>
//          セキュリティ上の理由により処理を中断します。<br>
//          この攻撃は記録され、管理者に通報されました。<br>
//          攻撃検出ID: ' . uniqid() . '<br>
//          正常な操作を行う場合は、トップページから再開してください。');
// } catch (InvalidArgumentException $e) {
//     // 不正なIPアドレス形式
//     error_log("不正なIPアドレス - IP:".($_SERVER['REMOTE_ADDR'] ?? 'unknown').", エラー: " . $e->getMessage() . ", UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
    
//     session_unset();
//     session_destroy();
//     die('不正なアクセスです。サポートされていないネットワーク環境からのアクセスです。');
         
// } catch (RuntimeException $e) {
//     // システムエラー
//     error_log("IPアドレス処理エラー - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", エラー: " . $e->getMessage() . ", セッションID: " . session_id());
    
//     session_unset();
//     session_destroy();
//     die('システムエラーが発生しました。ネットワーク環境を確認してください。<br>
//          エラーID: ' . uniqid() . '<br>
//          <a href="index.php">学生一覧に戻る</a>');
         
// } catch (Exception $e) {
//     // その他の予期しないエラー
//     error_log("予期しないエラー - IPアドレス処理 - IP:" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ", エラー: " . $e->getMessage());
    
//     session_unset();
//     session_destroy();
//     die('システムエラーが発生しました。管理者にお問い合わせください。<br>
//          エラーID: ' . uniqid() . '<br>
//          <a href="index.php">学生一覧に戻る</a>');
// }



require_once 'exceptions.php'; // SessionHijackingExceptionが定義されているファイル

/**
 * ip_check_for_session関数のテストクラス
 */
class IpCheckSessionTest {
    
    private $originalServer;
    private $originalSession;
    private $originalEnv;
    
    public function setUp(): void {
        // セッションを開始（まだ開始されていない場合）
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 元の値を保存
        $this->originalServer = $_SERVER;
        $this->originalSession = $_SESSION ?? [];
        $this->originalEnv = $_ENV ?? [];
        
        // セッションをクリア
        $_SESSION = [];
        
        // 基本的な$_SERVER設定
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_TIME'] = time();
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
    }
    
    public function tearDown(): void {
        // 元の値を復元
        $_SERVER = $this->originalServer;
        $_SESSION = $this->originalSession;
        $_ENV = $this->originalEnv;
    }
    
    /**
     * 初回アクセス時のテスト（IP prefixが設定される）
     */
    public function testFirstVisit(): void {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        // 初回はセッションにIP prefixが設定されていない
        unset($_SESSION['ip_prefix']);
        
        ip_check_for_session();
        
        // セッションにIP prefixが設定されることを確認
        assert(isset($_SESSION['ip_prefix']), "セッションにip_prefixが設定されるべき");
        assert($_SESSION['ip_prefix'] === '192.168', "設定されたip_prefixが正しくない");
        
        echo "✓ 初回アクセステスト: 成功\n";
    }
    
    /**
     * 正常なIP継続のテスト
     */
    public function testValidIpContinuation(): void {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SESSION['ip_prefix'] = '192.168';
        
        // 例外が投げられないことを確認
        try {
            ip_check_for_session();
            echo "✓ 正常なIP継続テスト: 成功\n";
        } catch (Exception $e) {
            assert(false, "正常なIPで例外が投げられた: " . $e->getMessage());
        }
    }
    
    /**
     * 同じプレフィックス内でのIP変更テスト
     */
    public function testSamePrefixIpChange(): void {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.200'; // 末尾のみ変更
        $_SESSION['ip_prefix'] = '192.168';
        
        try {
            ip_check_for_session();
            echo "✓ 同一プレフィックス内IP変更テスト: 成功\n";
        } catch (Exception $e) {
            assert(false, "同一プレフィックス内で例外が投げられた: " . $e->getMessage());
        }
    }
    
    /**
     * IP prefixが変更された場合のテスト
     */
    public function testIpPrefixChange(): void {
        $_SERVER['REMOTE_ADDR'] = '10.0.1.100'; // 完全に異なるIP
        $_SESSION['ip_prefix'] = '192.168';
        
        try {
            ip_check_for_session();
            assert(false, "IP prefix変更で例外が投げられるべき");
        } catch (SessionHijackingException $e) {
            assert($e->getMessage() === 'IPアドレスが変更されました', "期待されるエラーメッセージ");
            
            // コンテキストの確認
            $context = $e->getContext();
            assert(isset($context['previous_ip_prefix']), "previous_ip_prefixがコンテキストに含まれるべき");
            assert(isset($context['current_ip_prefix']), "current_ip_prefixがコンテキストに含まれるべき");
            assert($context['previous_ip_prefix'] === '192.168', "previous_ip_prefixが正しくない");
            assert($context['current_ip_prefix'] === '10.0', "current_ip_prefixが正しくない");
            
            echo "✓ IP prefix変更テスト: 成功\n";
        }
    }
    
    /**
     * IPv6アドレスのテスト
     */
    public function testIpv6Address(): void {
        $_SERVER['REMOTE_ADDR'] = '2001:db8:85a3::8a2e:370:7334';
        
        // 初回設定
        ip_check_for_session();
        assert($_SESSION['ip_prefix'] === '2001:db8:85a3', "IPv6 prefixが正しくない");
        
        // 同じprefixでの継続
        ip_check_for_session();
        
        echo "✓ IPv6アドレステスト: 成功\n";
    }
    
    /**
     * IPv4-mapped IPv6アドレスのテスト
     */
    public function testIpv4MappedIpv6(): void {
        $_SERVER['REMOTE_ADDR'] = '::ffff:192.0.2.128';
        
        ip_check_for_session();
        
        // IPv4として処理されるはず
        assert($_SESSION['ip_prefix'] === '192.0', "IPv4-mapped IPv6が正しく処理されない");
        
        echo "✓ IPv4-mapped IPv6テスト: 成功\n";
    }
    
    /**
     * カスタムブロック数のテスト
     */
    public function testCustomBlocks(): void {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        // IPv4で3ブロック指定
        ip_check_for_session(3, 3);
        assert($_SESSION['ip_prefix'] === '192.168.1', "カスタムブロック数が正しくない");
        
        echo "✓ カスタムブロック数テスト: 成功\n";
    }
    
    /**
     * 環境変数によるデフォルト値のテスト
     */
    public function testEnvironmentVariables(): void {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_ENV['IP_CHECK_IPV4_BLOCKS'] = 3;
        $_ENV['IP_CHECK_IPV6_BLOCKS'] = 4;
        
        ip_check_for_session(); // nullを渡して環境変数を使用
        
        // 3ブロック取得されるはず
        assert($_SESSION['ip_prefix'] === '192.168.1', "環境変数が正しく使用されない");
        
        echo "✓ 環境変数テスト: 成功\n";
    }
    
    /**
     * REMOTE_ADDRが存在しない場合のテスト
     */
    public function testNoRemoteAddr(): void {
        unset($_SERVER['REMOTE_ADDR']);
        
        try {
            ip_check_for_session();
            assert(false, "例外が投げられるべき");
        } catch (RuntimeException $e) {
            assert($e->getMessage() === 'IPアドレスが取得できません', "期待されるエラーメッセージ");
            echo "✓ REMOTE_ADDR未設定テスト: 成功\n";
        }
    }
    
    /**
     * 無効なIPアドレスのテスト
     */
    public function testInvalidIpAddress(): void {
        $_SERVER['REMOTE_ADDR'] = 'invalid-ip-address';
        
        try {
            ip_check_for_session();
            assert(false, "例外が投げられるべき");
        } catch (InvalidArgumentException $e) {
            assert(strpos($e->getMessage(), '無効なIPアドレスです') !== false, "期待されるエラーメッセージ");
            echo "✓ 無効IPアドレステスト: 成功\n";
        }
    }
    
    /**
     * セッションハイジャック検出後の状態テスト
     */
    public function testSessionHijackDetection(): void {
        // 初期設定
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        ip_check_for_session();
        $original_session_id = session_id();
        
        // 攻撃者のIPに変更
        $_SERVER['REMOTE_ADDR'] = '10.0.0.100';
        
        try {
            ip_check_for_session();
            assert(false, "セッションハイジャック検出で例外が投げられるべき");
        } catch (SessionHijackingException $e) {
            $context = $e->getContext();
            
            // セッションIDが記録されていることを確認
            assert(isset($context['session_id']), "session_idがコンテキストに含まれるべき");
            assert($context['session_id'] === $original_session_id, "session_idが一致しない");
            
            echo "✓ セッションハイジャック検出テスト: 成功\n";
        }
    }
    
    /**
     * 境界値テスト
     */
    public function testBoundaryValues(): void {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        // 最小ブロック数（1）
        ip_check_for_session(1, 1);
        assert($_SESSION['ip_prefix'] === '192', "最小ブロック数が正しくない");
        
        $this->setUp(); // リセット
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        // 最大ブロック数（4）
        ip_check_for_session(4, 8);
        assert($_SESSION['ip_prefix'] === '192.168.1.100', "最大ブロック数が正しくない");
        
        echo "✓ 境界値テスト: 成功\n";
    }
    
    /**
     * 全テストを実行
     */
    public function runAllTests(): void {
        echo "<pre>=== IP Check Session テスト開始 ===\n";
        
        try {
            $this->setUp();
            $this->testFirstVisit();
            
            $this->setUp();
            $this->testValidIpContinuation();
            
            $this->setUp();
            $this->testSamePrefixIpChange();
            
            $this->setUp();
            $this->testIpPrefixChange();
            
            $this->setUp();
            $this->testIpv6Address();
            
            $this->setUp();
            $this->testIpv4MappedIpv6();
            
            $this->setUp();
            $this->testCustomBlocks();
            
            $this->setUp();
            $this->testEnvironmentVariables();
            
            $this->setUp();
            $this->testNoRemoteAddr();
            
            $this->setUp();
            $this->testInvalidIpAddress();
            
            $this->setUp();
            $this->testSessionHijackDetection();
            
            $this->setUp();
            $this->testBoundaryValues();
            
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
        private $securityCode;
        
        public function __construct($message, $securityCode = 0, $context = [], $previous = null) {
            parent::__construct($message, $securityCode, $previous);
            $this->context = $context;
            $this->securityCode = $securityCode;
        }
        
        public function getContext() {
            return $this->context;
        }
        
        public function getSecurityCode() {
            return $this->securityCode;
        }
    }
}

if (!class_exists('SecurityException')) {
    class SecurityException {
        const SEC_SESSION_HIJACK = 1001;
    }
}

// テスト実行
$test = new IpCheckSessionTest();
$test->runAllTests();