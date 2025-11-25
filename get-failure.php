<?php
/**
 * 失敗時刻の配列を取得する
 * @param PDO $pdo
 * @param string $key
 * @param int $window 秒数
 * @return array 失敗時刻の配列
 */
// $pdo = $dbm->get_db(); として、
// PDO オブジェクトを取得してから呼び出します。

function get_failures(PDO $pdo, string $key, int $window): array
{
    $stmt = $pdo->prepare("
        SELECT failed_at 
        FROM rate_limits 
        WHERE key_name = ?
        AND failed_at > ?
    ");
    
    $stmt->execute([$key, time() - $window]);


    //  fetchAll() は全ての行のデータを取得
    //  配列で返す。
    //  つまり、失敗時刻の配列が返る
    //  PDO::FETCH_COLUMN は1列だけ取得するオプション
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}