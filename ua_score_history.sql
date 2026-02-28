CREATE TABLE ua_score_history (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  subject_type      ENUM('session', 'ip') NOT NULL,
  subject_key       VARCHAR(128) NOT NULL,
  is_no_ua          TINYINT(1) NOT NULL DEFAULT 0,
  is_ua_mismatch    TINYINT(1) NOT NULL DEFAULT 0,
  is_over_threshold_session TINYINT(1) NOT NULL DEFAULT 0,
  is_over_threshold_ip TINYINT(1) NOT NULL DEFAULT 0,
  is_no_anomaly     TINYINT(1) NOT NULL DEFAULT 0,
  recaptcha_solved  TINYINT(1) NOT NULL DEFAULT 0,
  is_decreased      TINYINT(1) NOT NULL DEFAULT 0,
  access_time       DATETIME NOT NULL,
  INDEX idx_subject_time (subject_type, subject_key, access_time)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;