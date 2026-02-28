<?php
// ユーザーエージェントログの古いレコードを削除するバッチ
// 1日より前の access_time の行を削除します。

// CLI 以外（Web など）からの実行は拒否
if (PHP_SAPI !== 'cli') {
    // Web 経由で誤って実行された場合でも 403 を返してすぐ終了
    if (function_exists('http_response_code')) {
        http_response_code(403);  // Forbidden 権限がない
    }
    exit(1);  // (1)はエラー終了
}

require_once __DIR__ . '/common/dbmanager.php';

try {
    // DB接続取得
    $dbm = new DBManager();
    $dbm->connect();
    $pdo = $dbm->get_db();

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('PDOインスタンスの取得に失敗しました。');
    }

    // 1日より前のログを削除
    $sql = 'DELETE FROM user_agent_logs WHERE access_time < (NOW() - INTERVAL 1 DAY)';
    $deleted = $pdo->exec($sql);
    // $deleted は削除された行数が返る、
    // 失敗した場合は false

    // CLIやcronから実行したときの簡単な出力（標準出力）
    $count = ($deleted === false) ? 0 : (int)$deleted;
     //  $deleted が 環境によって方が異なる場合があるため、
     // 明示的に int にキャスト
    echo sprintf(
        "[%s] ua-clean-old-logs: deleted %d rows.\n",
        date('Y-m-d H:i:s'),
        $count
    );

    exit(0);  // (0)は正常終了
} catch (Throwable $e) {
    //  Throwable でキャッチすることで
    //  Exception だけでなく
    // エラーもキャッチ可能にする

    // エラー時はログに残し、終了コード1で終了
    error_log('ua-clean-old-logs error: ' . $e->getMessage());

    // CLI実行なら標準エラー出力にも出す
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "ua-clean-old-logs error: " . $e->getMessage() . PHP_EOL);
    }

    exit(1);
}