<?php
/**
 * ブロック確認
 * @param PDO $pdo
 * @param string $key
 * @return bool ブロック中なら true、そうでなければ false
*/
function is_blocked(PDO $pdo, string $key): bool
{
    $stmt = $pdo->prepare("SELECT blocked_until FROM rate_blocks WHERE key_name = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($row && (int)$row['blocked_until'] > time());
}