<?php
// ユーザーエージェントログの古いレコードを削除するバッチ
// 1日より前の access_time の行を削除します。

require_once __DIR__ . '/common/dbmanager.php';

//  CLI実行かどうかを判定
$is_cli = (PHP_SAPI === 'cli');
if (!$is_cli) {
    // http経由は拒否
    http_response_code(403);  // Forbidden アクセス拒否
    exit;
    
}


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

    // CLIやcronから実行したときの簡単な出力
    $count = ($deleted === false) ? 0 : (int)$deleted;
    header('Content-Type: text/plain; charset=UTF-8');
    echo sprintf(
        "[%s] ua-clean-old-logs: deleted %d rows.\n",
        date('Y-m-d H:i:s'),
        $count
    );

    exit(0);
} catch (Throwable $e) {
    // エラー時はログに残し、終了コード1で終了
    error_log('ua-clean-old-logs error: ' . $e->getMessage());
    if (PHP_SAPI === 'cli') {
        // CLI実行なら標準エラー出力にも出す
        fwrite(STDERR, "ua-clean-old-logs error: " . $e->getMessage() . PHP_EOL);
    }
    exit(1);
}