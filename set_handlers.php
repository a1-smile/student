<?php
// 例外の最後の砦
set_exception_handler(function ($e) {
    error_log("未処理の例外: " . $e->getMessage());
    http_response_code(500);
    echo "想定していない例外が発生しました。";
    exit;
});

// エラーの最後の砦
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHPエラー [$errno]: $errstr in $errfile:$errline");
    http_response_code(500);
    echo "想定されていない不具合が発生しました。";
    exit;
});

// テスト

    //echo $undefined;              // set_error_handler が呼ばれるの処理
    //throw new Exception("テスト例外"); // set_exception_handler が呼ばれるの処理
