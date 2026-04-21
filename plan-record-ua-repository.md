# Plan for Record Access Data of UA to DB

- CREATE TABLE user_agent_logs (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  session_id     VARCHAR(128) NOT NULL,
  ip_address     VARCHAR(64)  NOT NULL,
  simple_ua      VARCHAR(128) NOT NULL,
  is_ua_mismatch TINYINT(1)   NOT NULL,
  access_time    DATETIME     NOT NULL,
  INDEX idx_sess_ip_time (session_id, ip_address, access_time)
) 

user_agent_logs にデータを記録する処理をかんがえます。

session_id を取得します。
$request_content = new RequestContentImplementation();

もしくは、RequestContent を
引数にとるクラスを作成して、
そこに RequestContentImplementation
のインスタンスを渡す方法も考えられます。
{private RequestContent $requestContent;
   construct(RequestContent $request_content) {
        $this->requestContent = $request_content;
}
}

$session_id = $request_content->getSessionId();
ip_address を取得します。
$ip_address = $request_content->getIpAddress();
simple_ua を取得します。
$simple_ua = $request_content->getCurrentSimpleUa();
is_ua_mismatch を取得します。
$is_ua_mismatch = $request_content->getIsUaMismatch();

テーブル ua_anomaly_events  にデータを記録する処理をかんがえます。
is_no_ua を取得します。
$is_no_ua = $request_content->getIsNoUa();