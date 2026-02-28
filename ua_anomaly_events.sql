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

-- 「subject_key には 
--  session_id または ip_address
-- （最大45文字程度）が入る」ので、
--  VARCHAR(128) とした意図です。

--  また異常が検知された際に記録する。

--  session id base と ip address base の
--  どちらかで  異常が検知されたときは
--  もう片方も一定程度疑わしく扱うことが適切と考えられるため、
--  一回のアクセスで異常が検知されたら、
--  session id base と ip address base 
--  の両方でレコードを挿入することとします。
--  そうしますと、subject_type ENUM('session', 'ip') NOT NULL,
--  subject_key VARCHAR(128) NOT NULL,
--  というカラムの構成ですと、
--  一回のアクセスで異常が検知されたときに、
--  session id base と ip address base の両方で
--  レコードを挿入しなけらばならないため、
--  サーバーの負荷が高くなってしまう可能性があります。
--  したがって、カラム構成を変更して、
--  subject_type ENUM('session', 'ip') NOT NULL,
--  を削除。
--  subject_key VARCHAR(128) NOT NULL,
--  を削除。
--  代わりに、
--  session_id VARCHAR(128) NOT NULL,
--  ip_address VARCHAR(45) NOT NULL,
--  とすることも考えられます。
--  この考えが、適切ならば、カラム構成を変更して、
--  CREATE TABLE のsql文を修正してください。