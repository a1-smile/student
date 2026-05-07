<?php
// db-error-page.php
// 最小構成のエラーページ例
// 500 Internal Server Error を返す例
http_response_code(500);
echo 'システムエラーが発生しました。しばらく時間をおいてから、
      トップページよりやり直してください。<br>
      <a href="index.php">トップページに戻る</a>';