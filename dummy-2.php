<?php
// ファクトリーメソッド
public static function fromCurrentRequest(array $expectedMethods, string $customMessage = null): self {
    $actualMethod = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    
    $message = $customMessage ?? sprintf(
        "期待されたメソッド: %s, 実際のメソッド: %s",
        implode(' または ', $expectedMethods),
        $actualMethod
    );
    
    $context = [
        'expected_methods' => $expectedMethods, // 配列として保存
        'actual_method' => $actualMethod,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
        'request_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time()),
        'session_id' => session_id()
    ];
    
    return new self($message, SecurityException::SEC_INVALID_REQUEST_METHOD, $context);
}

//  追加で変更が必要な部分
<?php
// ヘルパーメソッド
public function getExpectedMethods(): ?array {
    return $this->context['expected_methods'] ?? null;
}

public function getActualMethod(): ?string {
    return $this->context['actual_method'] ?? null;
}

// 単一のメソッドとの互換性のため
public function getExpectedMethod(): ?string {
    $methods = $this->getExpectedMethods();
    return $methods ? implode(' または ', $methods) : null;
}