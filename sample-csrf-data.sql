-- 失敗イベント（スライディングウィンドウ集計用）
CREATE TABLE csrf_rate_limit_failures (
  -- 正の整数の一意ID
  -- BIGINT は大規模システムの通し番号用
  -- PRIMARY KEY は行を識別するための id を設定する。
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  -- レート制限キーVARCHAR(128)は128文字以下の
  -- 任意の文字列
  rate_key VARCHAR(128) NOT NULL,     -- 例: 'csrf_attack_' . sha256(session_id)
  occurred_at INT UNSIGNED NOT NULL,  -- time()
  -- INDEX は検索を高速化するためのもの
  INDEX idx_ratekey_time (rate_key, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
-- ENGINE=InnoDB はセキュリティー関連では推奨されるストレージエンジン
-- COLLATE=utf8mb4_bin はバイナリ比較を行う照合順序 



-- 現在のブロック状態
CREATE TABLE csrf_rate_limit_state (
  rate_key VARCHAR(128) NOT NULL PRIMARY KEY,
  block_until INT UNSIGNED NOT NULL DEFAULT 0,
  -- 最終更新日時
  -- TIMESTAMP:日時を保存する型
  -- DEFAULT CURRENT_TIMESTAMP:行作成時に現在日時を自動設定
  -- ON UPDATE CURRENT_TIMESTAMP:行更新時に現在日時を自動設定
  -- 作成日時は自動で入り、更新されたら自動で変わる
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;


-- データの保存・参照タイミング
-- リクエスト開始時
-- state参照: SELECT block_until FROM csrf_rate_limit_state WHERE rate_key=?; 無ければ初期行をINSERT。
-- ブロック中判定: now < block_until なら「ブロック中」。このアクセス自体も失敗として failures にINSERT。
-- 失敗が発生した時点（例: トークン不一致や検証失敗時）
-- failuresにINSERT(occurred_at=now)。
-- 直近time_window(例:5分)の件数 COUNT(*) を参照。
-- high閾値以上なら stateをUPDATE（block_until = now + block_time）し、例外。
-- medium閾値ならセキュリティレベルMEDIUMとして例外。
-- 成功時
-- 何も保存不要（必要なら成功イベントテーブルを別途）。
-- メンテナンス
-- 古いfailuresを定期削除: DELETE FROM csrf_rate_limit_failures WHERE occurred_at < (UNIX_TIMESTAMP() - 86400) など（Windowsタスクスケジューラで1時間/日次）。
-- 利用方法（最小の実装例）