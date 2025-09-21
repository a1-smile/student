<?php
require_once 'ip-check-sessionhijack.php';

/**
 * get_ip_prefix_for_session関数のテストクラス
 */
class IpPrefixSessionTest {
    
    private $originalServer;
    
    public function setUp(): void {
        // 元の$_SERVERを保存
        $this->originalServer = $_SERVER;
    }
    
    public function tearDown(): void {
        // $_SERVERを復元
        $_SERVER = $this->originalServer;
    }
    
    /**
     * IPv4アドレスのテスト
     */
    public function testIpv4Address(): void {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        // デフォルト（2ブロック）
        $result = get_ip_prefix_for_session();
        assert($result === '192.168', "Expected '192.168', got '$result'");
        
        // 1ブロック
        $result = get_ip_prefix_for_session(1);
        assert($result === '192', "Expected '192', got '$result'");
        
        // 3ブロック
        $result = get_ip_prefix_for_session(3);
        assert($result === '192.168.1', "Expected '192.168.1', got '$result'");
        
        // 4ブロック（全て）
        $result = get_ip_prefix_for_session(4);
        assert($result === '192.168.1.100', "Expected '192.168.1.100', got '$result'");
        
        echo "✓ IPv4アドレステスト: 成功\n";
    }
    
    /**
     * IPv6アドレスのテスト
     */
    public function testIpv6Address(): void {
        $_SERVER['REMOTE_ADDR'] = '2001:db8:85a3::8a2e:370:7334';
        
        // デフォルト（3ブロック）
        $result = get_ip_prefix_for_session(2, 3);
        assert($result === '2001:db8:85a3', "Expected '2001:db8:85a3', got '$result'");
        
        // 2ブロック
        $result = get_ip_prefix_for_session(2, 2);
        assert($result === '2001:db8', "Expected '2001:db8', got '$result'");
        
        // 1ブロック
        $result = get_ip_prefix_for_session(2, 1);
        assert($result === '2001', "Expected '2001', got '$result'");
        
        echo "✓ IPv6アドレステスト: 成功\n";
    }
    
    /**
     * IPv4-mapped IPv6アドレスのテスト
     */
    public function testIpv4MappedIpv6(): void {
        $_SERVER['REMOTE_ADDR'] = '::ffff:192.0.2.128';
        
        // IPv4として処理されるはず
        $result = get_ip_prefix_for_session(2);
        assert($result === '192.0', "Expected '192.0', got '$result'");
        
        $result = get_ip_prefix_for_session(3);
        assert($result === '192.0.2', "Expected '192.0.2', got '$result'");
        
        echo "✓ IPv4-mapped IPv6テスト: 成功\n";
    }
    
    /**
     * REMOTE_ADDRが存在しない場合のテスト
     */
    public function testNoRemoteAddr(): void {
        unset($_SERVER['REMOTE_ADDR']);
        
        try {
            get_ip_prefix_for_session();
            assert(false, "例外が投げられるべき");
        } catch (RuntimeException $e) {
            assert($e->getMessage() === 'IPアドレスが取得できません', "Expected error message");
            echo "✓ REMOTE_ADDR未設定テスト: 成功\n";
        }
    }
    
    /**
     * 空のREMOTE_ADDRのテスト
     */
    public function testEmptyRemoteAddr(): void {
        $_SERVER['REMOTE_ADDR'] = '';
        
        try {
            get_ip_prefix_for_session();
            assert(false, "例外が投げられるべき");
        } catch (RuntimeException $e) {
            assert($e->getMessage() === 'IPアドレスが取得できません', "Expected error message");
            echo "✓ REMOTE_ADDR空文字テスト: 成功\n";
        }
    }
    
    /**
     * 無効なIPアドレスのテスト
     */
    public function testInvalidIpAddress(): void {
        $_SERVER['REMOTE_ADDR'] = 'invalid-ip-address';
        
        try {
            get_ip_prefix_for_session();
            assert(false, "例外が投げられるべき");
        } catch (InvalidArgumentException $e) {
            assert(strpos($e->getMessage(), '無効なIPアドレスです') !== false, "Expected error message");
            echo "✓ 無効IPアドレステスト: 成功\n";
        }
    }
    
    /**
     * エッジケースのテスト
     */
    public function testEdgeCases(): void {
        // IPv6の短縮形
        $_SERVER['REMOTE_ADDR'] = '::1';
        $result = get_ip_prefix_for_session(2, 1);
        assert($result === '1', "Expected '1', got '$result'");
        
        // IPv4のローカルホスト
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $result = get_ip_prefix_for_session(2);
        assert($result === '127.0', "Expected '127.0', got '$result'");
        
        echo "✓ エッジケーステスト: 成功\n";
    }
    
    /**
     * 全テストを実行
     */
    public function runAllTests(): void {
        echo "=== IP Prefix Session テスト開始 ===\n";
        
        $this->setUp();
        
        try {
            $this->testIpv4Address();
            $this->setUp();
            
            $this->testIpv6Address();
            $this->setUp();
            
            $this->testIpv4MappedIpv6();
            $this->setUp();
            
            $this->testNoRemoteAddr();
            $this->setUp();
            
            $this->testEmptyRemoteAddr();
            $this->setUp();
            
            $this->testInvalidIpAddress();
            $this->setUp();
            
            $this->testEdgeCases();
            
            echo "\n=== 全テスト成功！ ===\n";
        } finally {
            $this->tearDown();
        }
    }
}

// テスト実行
$test = new IpPrefixSessionTest();
$test->runAllTests();