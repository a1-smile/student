<?php
//  共通ファイルを読み込みます。
$file_name = 'example.php';
try{
        $file_path = __DIR__ .DIRECTORY_SEPARATOR.$file_name;
        $real_path = realpath($file_path);
        
        if ($real_path === false) {
            throw new RuntimeException("{$file_name}のパスが無効です");
        }
        
        if (!file_exists($real_path)) {
            throw new RuntimeException('ファイルが見つかりません: ' . $real_path);
        }
        
        require_once $real_path;
        
    } catch (RuntimeException $e) {
        error_log("ファイル読み込みエラー: " . $e->getMessage());
        die('システムファイルの読み込みに失敗しました。');
    } 