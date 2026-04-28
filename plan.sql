

CREATE TABLE user_agent_logs (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  session_id     VARCHAR(128) NOT NULL,
  ip_address     VARCHAR(64)  NOT NULL,
  simple_ua      VARCHAR(128) NOT NULL,
  is_ua_mismatch TINYINT(1)   NOT NULL,
  access_time    DATETIME     NOT NULL,
  INDEX idx_sess_ip_time (session_id, ip_address, access_time)
) 

$sql = "SELECT 
                COUNT(CASE WHEN session_id = :sid THEN 1 END) as session_id_access_count,
                COUNT(CASE WHEN ip_address = :ip THEN 1 END) as ip_address_access_count
                FROM user_agent_logs 
                WHERE access_time >= (NOW() - INTERVAL 1 MINUTE)";





CREATE TABLE ua_anomaly_events (
id INT AUTO_INCREMENT PRIMARY KEY,
session_id VARCHAR(128) NOT NULL,
ip_address VARCHAR(45) NOT NULL,
is_no_ua TINYINT(1) NOT NULL DEFAULT 0,
is_ua_mismatch TINYINT(1) NOT NULL DEFAULT 0,
is_over_threshold_session TINYINT(1) NOT NULL DEFAULT 0,
is_over_threshold_ip TINYINT(1) NOT NULL DEFAULT 0,
access_time DATETIME NOT NULL,
INDEX idx_session_time (session_id, access_time),
INDEX idx_ip_time (ip_address, access_time),
INDEX idx_time (access_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

$sql = "SELECT 
            COUNT(CASE WHEN session_id = :sessionId THEN 1 END) as session_anomaly_count,
            COUNT(CASE WHEN ip_address = :ipAddress THEN 1 END) as ip_anomaly_count
            
        FROM ua_anomaly_events WHERE access_time >= (NOW() - INTERVAL 10 MINUTE)";

--  テーブル ua_anomaly_events から
-- $sql = "SELECT 
--             COUNT(CASE WHEN session_id = :sessionId THEN 1 END) as session_anomaly_count,
--             COUNT(CASE WHEN ip_address = :ipAddress THEN 1 END) as ip_anomaly_count
            
--         FROM ua_anomaly_events WHERE access_time >= (NOW() - INTERVAL 10 MINUTE)";
-- を実行して、データを取得する場合、INDEX の設定が適切ではなく、
-- クエリのパフォーマンスが低下する可能性がありますか？
-- 二つのSQL文に分けて実行するほうが、むしろパフォーマンスが向上する可能性がありますか？

-- はい、DBアクセスの回数よりも、
--インデックスの適切な利用がクエリのパフォーマンスに大きく影響します。

-- クエリ1: idx_session_time (session_id, access_time) を利用
SELECT COUNT(*) as session_anomaly_count
FROM ua_anomaly_events 
WHERE session_id = :sessionId AND access_time >= (NOW() - INTERVAL 10 MINUTE);

-- クエリ2: idx_ip_time (ip_address, access_time) を利用
SELECT COUNT(*) as ip_anomaly_count
FROM ua_anomaly_events 
WHERE ip_address = :ipAddress AND access_time >= (NOW() - INTERVAL 10 MINUTE);


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



CREATE TABLE ua_score_history (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  subject_type      ENUM('session', 'ip') NOT NULL,
  subject_key       VARCHAR(128) NOT NULL,
  is_no_ua          TINYINT(1) NOT NULL DEFAULT 0,
  is_ua_mismatch    TINYINT(1) NOT NULL DEFAULT 0,
  is_over_threshold TINYINT(1) NOT NULL DEFAULT 0,
  is_no_anomaly     TINYINT(1) NOT NULL DEFAULT 0,
  recaptcha_solved  TINYINT(1) NOT NULL DEFAULT 0,
  is_decreased      TINYINT(1) NOT NULL DEFAULT 0,
  access_time       DATETIME NOT NULL,
  INDEX idx_subject_time (subject_type, subject_key, access_time)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

  $sql = "SELECT 
            COUNT(CASE WHEN subject_key = :sessionId AND subject_type = 'session' AND is_decreased = 1 THEN 1 END) as session_decreased_count,
            COUNT(CASE WHEN subject_key = :ipAddress AND subject_type = 'ip' AND is_decreased = 1 THEN 1 END) as ip_decreased_count
            
        FROM ua_score_history WHERE access_time >= (NOW() - INTERVAL 30 MINUTE)";
