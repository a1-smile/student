<?php
// ...existing code...

// グローバルスコープでDBManagerを作成（1箇所のみ）
try {
    $dbm = new DBManager();
} catch (Exception $e) {
    error_log('DBManager のインスタンス作成に失敗: ' . $e->getMessage());
    die('DBManager のインスタンス作成に失敗しました');
}

// 関数呼び出し時に引数として渡す
if ($method === 'POST'){
    update_post($dbm);
}elseif ($method === 'GET') {
    update_get($dbm);
}else {
    // ...existing code...
}

function update_post($dbm) {
    // ...existing code...
    
    // 関数内のDBManager作成部分を削除
    // 不要なcommon.php再読み込み部分も削除
    
    $member = $dbm->get_student($old_id);
    
    // ...existing code...
}

function update_get($dbm) {
    // ...existing code...
    
    // 不要なcommon.php再読み込み部分を削除
    
    // ...existing code...
}