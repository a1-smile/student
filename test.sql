-- レート制限テスト用リセットスクリプト
-- rate_limits / rate_blocks の内容を全削除してカウンタを初期化する
-- 依存関係や外部参照が無い前提で TRUNCATE を使用（高速）。
-- 外部キー制約が存在する場合は DELETE に切り替えてください。

-- (1) 失敗履歴テーブル初期化
TRUNCATE TABLE rate_limits;

-- (2) ブロックテーブル初期化
TRUNCATE TABLE rate_blocks;

-- 代替案（外部キー制約がある場合）
-- DELETE FROM rate_limits;
-- DELETE FROM rate_blocks;

-- 実行確認用（任意）
-- SELECT COUNT(*) AS cnt_limits FROM rate_limits;
-- SELECT COUNT(*) AS cnt_blocks FROM rate_blocks;
