<?php
/**
 * rate_limit_pdo()
 * データベースに接続する
 * CSRF rate limiter 用の PDO インスタンスを取得する
 * @return PDO PDOインスタンス
 */
function rate_limit_pdo(): PDO {
    // 環境に合わせて修正（MAMPのデフォルト例）
    //  pdo 接続
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=student;charset=utf8mb4',
        'root',
        'root',
        [
            // ここに必要なPDOオプションを追加
            // エラーが発生した場合に例外をスロー
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // フェッチモードを連想配列に設定
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    //  戻り値は PDO インスタンス
    return $pdo;
}


/**
 * rate_limit_get_or_init_state()
 * 
 * テーブル csrf_rate_limit_state から
 * 指定された rate_key に対応するレコード
 * block_until,rate_key を取得、
 * 存在しなければ初期化する
 * 
 * @param PDO $pdo PDOインスタンス
 * @param string $rate_key レートキー
 * @return array $rowレコードデータ
 */
function rate_limit_get_or_init_state(PDO $pdo, string $rate_key): array {
    $st = $pdo->prepare('SELECT rate_key, block_until FROM csrf_rate_limit_state WHERE rate_key = ?');
    //  execute には値を必ず配列で渡す
    $st->execute([$rate_key]);
    $row = $st->fetch();
    //  レコードが存在すればそれを返す
    if ($row) return $row;

    //  レコードが存在しなければ初期化して返す
    $st = $pdo->prepare('INSERT INTO csrf_rate_limit_state (rate_key, block_until) VALUES (?, 0)');
    $st->execute([$rate_key]);
    return ['rate_key' => $rate_key, 'block_until' => 0];
}



/** * rate_limit_insert_failure()
 * 
 * テーブル csrf_rate_limit_failures に
 * 指定された rate_key と発生時刻を挿入する
 * 
 * @param PDO $pdo PDOインスタンス
 * @param string $rate_key レートキー
 * @param int $now 発生時刻のタイムスタンプ
 * @return void
 */
function rate_limit_insert_failure(PDO $pdo, string $rate_key, int $now): void {
    $st = $pdo->prepare('INSERT INTO csrf_rate_limit_failures (rate_key, occurred_at) VALUES (?, ?)');
    $st->execute([$rate_key, $now]);
}

function rate_limit_count_failures_since(PDO $pdo, string $rate_key, int $since): int {
    $st = $pdo->prepare(
        'SELECT COUNT(*) AS c FROM csrf_rate_limit_failures WHERE rate_key = ? AND occurred_at >= ?'
    );
    $st->execute([$rate_key, $since]);
    return (int)$st->fetchColumn();
}

function rate_limit_set_block_until(PDO $pdo, string $rate_key, int $until): void {
    // ブロック延長を単発で保証（短縮しない）
    $st = $pdo->prepare(
        'INSERT INTO csrf_rate_limit_state (rate_key, block_until)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE block_until = GREATEST(block_until, VALUES(block_until))'
    );
    $st->execute([$rate_key, $until]);
}
// ...existing code...