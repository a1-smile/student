<?php
/**
 * ブロック記録（30分など）
 * @param PDO $pdo
 * @param string $key
 * @param int $block_duration 秒数
*/
function record_block(PDO $pdo, string $key, int $block_duration): void
{
    $blocked_until = time() + $block_duration;
    // REPLACE: 既存 key_name 行を置換
    $stmt = $pdo->prepare("REPLACE INTO rate_blocks (key_name, blocked_until) VALUES (?, ?)");
    $stmt->execute([$key, $blocked_until]);
}