<?php
/**
 * 失敗時刻とrate key 
 * @param string $key
 * @param PDO $pdo
 * 
 */
// $pdo = $dbm->get_db(); として、
// PDO オブジェクトを取得してから呼び出します。
function record_failure(PDO $pdo, string $key): void
{
    $stmt = $pdo->prepare("INSERT INTO rate_limits (key_name, failed_at) VALUES (?, ?)");
    $stmt->execute([$key, time()]);
}