<?php
// tests/bootstrap.php

require_once __DIR__ . '/../src/IpPrefixExtractor.php';
require_once __DIR__ . '/../src/functions/ip_helpers.php';

// PHPUnitの自動ローディングを設定
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// テスト環境の初期化処理をここに追加できます
// 例: ini_set('display_errors', 1); error_reporting(E_ALL);
?>