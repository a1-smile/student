CREATE TABLE rate_limits (
    key_name VARCHAR(100) NOT NULL,
    failed_at INT UNSIGNED NOT NULL,
    INDEX (key_name),
    INDEX (failed_at)
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- INDEX は検索を高速化するためのもの
-- ENGINE=InnoDB はセキュリティー関連では推奨されるストレージエンジン
-- COLLATE=utf8mb4_bin はバイナリ比較を行う照合順序