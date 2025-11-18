<?php
//  example.php を読み込む
$file_name = 'example.php';
try {
    $real_path = safe_file_path($file_name);
    require_once $real_path;
} catch (InvalidArgumentException $e) {
    die("引数エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
} catch (LogicException $e) {
    die("ℹ️ ファイルは既に読み込み済みです: " . $e->getMessage() . "<br>");    
} catch (RuntimeException $e) {
    die("ファイル読み込みエラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
} catch (ParseError $e) {
    die("構文エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
} catch (Error $e) {
    die("致命的エラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
    
} catch (Exception $e) {
    die("予期しないエラー: " . $e->getMessage() . "<br>エラーID: " . uniqid());
}