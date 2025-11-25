<?php
/**
 * 古いログを削除する
 * @param PDO $pdo
 * 
 */
function clean_old_logs(PDO $pdo): void
{
    // 1時間より前のログは削除
    //  exec() は結果セットを返さないSQLを実行するメソッド
    //  DELETE, UPDATE, INSERT などに使う
    //  1時間 = 3600秒
    $pdo->exec("DELETE FROM rate_limits WHERE failed_at < " . (time() - 3600));
}
