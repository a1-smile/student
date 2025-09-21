<?php
require_once '../src/IpPrefixExtractor.php';

// IPアドレスの例
$ipAddresses = [
    '192.168.1.1',
    '2001:db8:85a3:0000:0000:8a2e:0370:7334',
    '10.0.0.5',
    '::ffff:192.0.2.128',
];

// IpPrefixExtractorのインスタンスを作成
$extractor = new IpPrefixExtractor();

// 各IPアドレスに対してプレフィックスを取得
foreach ($ipAddresses as $ip) {
    $prefix = $extractor->getPrefix($ip);
    echo "IPアドレス: $ip, プレフィックス: $prefix\n";
}
?>