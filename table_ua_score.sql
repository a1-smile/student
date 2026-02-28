
-- CREATE TABLE ua_scores_session (
--   session_id VARCHAR(128) NOT NULL,
--   score INT UNSIGNED NOT NULL DEFAULT 0,
--   updated_at DATETIME NOT NULL
--     DEFAULT CURRENT_TIMESTAMP
--     ON UPDATE CURRENT_TIMESTAMP,
--   PRIMARY KEY (session_id)
-- ) ENGINE=InnoDB
--   DEFAULT CHARSET=utf8mb4
--   COLLATE=utf8mb4_unicode_ci;

-- セキュリティ学習や実運用を考えるなら、
-- 基本的に ENGINE=InnoDB 
-- を選んでおけば問題ありません。
-- ci … case-insensitive（大文字小文字を区別しない）
-- unicode_ci
-- Webアプリの一般的な日本語・英数字混在の用途なら、
-- この組み合わせは無難な選択です。



--   CREATE TABLE ua_scores_ip (
--   ip_address VARCHAR(45) NOT NULL,
--   score INT UNSIGNED NOT NULL DEFAULT 0,
--   updated_at DATETIME NOT NULL
--     DEFAULT CURRENT_TIMESTAMP
--     ON UPDATE CURRENT_TIMESTAMP,
--   PRIMARY KEY (ip_address)
-- ) ENGINE=InnoDB
--   DEFAULT CHARSET=utf8mb4
--   COLLATE=utf8mb4_unicode_ci;

--   テーブル例: ua_scores
-- subject_type ENUM('session', 'ip') NOT NULL
-- subject_key VARCHAR(128) NOT NULL
-- session_id も ip_address もここに入れる（IP は45文字以内なので128で十分）
-- score INT UNSIGNED NOT NULL DEFAULT 0
-- updated_at DATETIME NOT NULL   DEFAULT CURRENT_TIMESTAMP   ON UPDATE CURRENT_TIMESTAMP
-- PRIMARY KEY (subject_type, subject_key)

-- session_id と ip_address で本来の長さ制約が違うので、subject_key は「長い方（session_id）」に合わせておく必要があります（128文字など）。
-- インデックスは PRIMARY KEY (subject_type, subject_key) にしておけば、
-- WHERE subject_type = 'session' AND subject_key = :id のような検索は問題なく高速になります。
-- アプリ側では、
-- セッション用: subject_type = 'session', subject_key = session_id
-- IP用: subject_type = 'ip', subject_key = ip_address
-- という対応だけを意識すればよいです。


CREATE TABLE ua_scores (
  subject_type ENUM('session', 'ip') NOT NULL,
  subject_key  VARCHAR(128) NOT NULL,
  score        INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at   DATETIME NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (subject_type, subject_key)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- DEFAULT CURRENT_TIMESTAMP

-- 「INSERT でこのカラムを指定しなかったとき、
-- 自動的にその時点の現在時刻を入れる」という意味です。
-- ON UPDATE CURRENT_TIMESTAMP
 
-- 「UPDATE でその行が更新されるたびに、
-- 自動的にこのカラムを現在時刻で更新する」という意味です
-- （ただし、updated_at を明示的に別の値に更新した場合はその値になります）

-- 挿入OK:

-- (subject_type='session', subject_key='ABC')
-- (subject_type='ip', subject_key='ABC')
-- → subject_key は同じ 'ABC' ですが、subject_type が違うので、組み合わせとしては別物でOK。
-- 挿入NG（重複エラー）:

-- すでに (subject_type='session', subject_key='ABC') がある状態で、
-- もう一度 (subject_type='session', subject_key='ABC') を入れようとする
-- → subject_type も subject_key も同じ組み合わせなので、PRIMARY KEY の一意制約に違反します。