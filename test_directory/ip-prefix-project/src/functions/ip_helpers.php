<?php
/**
 * IPアドレスの検証やプレフィックス取得に関連するヘルパー関数
 */

/**
 * IPアドレスからプレフィックスを取得する
 *
 * @param string|null $ip IPアドレス文字列（nullなら $_SERVER から取得）
 * @param int $ipv4_blocks IPv4の場合に何ブロックまで取るか（例: 192.168 -> 2）
 * @param int $ipv6_blocks IPv6の場合に何ブロックまで取るか（例: 2001:db8:85a3 -> 3）
 * @return string プレフィックス文字列、取得できなければ空文字
 */
function get_ip_prefix(?string $ip = null, int $ipv4_blocks = 2, int $ipv6_blocks = 3): string {
    if ($ip === null) {
        $server = $_SERVER;
        if (isset($server['REMOTE_ADDR'])) {
            $ip = $server['REMOTE_ADDR'];
        } else {
            return '';
        }
    }

    if (strpos($ip, '::ffff:') !== false) {
        $v4 = substr($ip, strrpos($ip, ':') + 1);
        if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = $v4;
        }
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $take = max(1, min(count($parts), $ipv4_blocks));
        return implode('.', array_slice($parts, 0, $take));
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        $parts_filtered = array_values(array_filter($parts, fn($p) => $p !== ''));
        $take = max(1, min(count($parts_filtered), $ipv6_blocks));
        return implode(':', array_slice($parts_filtered, 0, $take));
    }

    return '';
}