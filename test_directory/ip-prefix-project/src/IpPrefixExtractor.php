<?php

class IpPrefixExtractor {
    /**
     * IPアドレスからプレフィックスを取得する
     *
     * @param string $ip IPアドレス文字列
     * @param int $ipv4_blocks IPv4の場合に何ブロックまで取るか
     * @param int $ipv6_blocks IPv6の場合に何ブロックまで取るか
     * @return string プレフィックス文字列
     */
    public function getPrefix(string $ip, int $ipv4_blocks = 2, int $ipv6_blocks = 3): string {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->getIpv4Prefix($ip, $ipv4_blocks);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->getIpv6Prefix($ip, $ipv6_blocks);
        }
        return '';
    }

    private function getIpv4Prefix(string $ip, int $blocks): string {
        $parts = explode('.', $ip);
        $take = max(1, min(count($parts), $blocks));
        return implode('.', array_slice($parts, 0, $take));
    }

    private function getIpv6Prefix(string $ip, int $blocks): string {
        $parts = explode(':', $ip);
        $parts_filtered = array_values(array_filter($parts, fn($p) => $p !== ''));
        $take = max(1, min(count($parts_filtered), $blocks));
        return implode(':', array_slice($parts_filtered, 0, $take));
    }
}