<?php
/**
 * アクセス元のＵＲＬのドメインが
 * 現在のサイトのドメインと一致するかを確認します。
 * 不一致の場合は false を返します。
 * 一致する場合は true を返します。
 * @return bool 一致する場合は true、不一致または空の場合は false
 */

function referer_check(): bool {
    $referer = $_SERVER['HTTP_REFERER'] ?? ''; //  アクセス元のURL
    $host = $_SERVER['HTTP_HOST'] ?? '';       //  アクセス先のドメイン
    
    //  strpos() は、部分文字列の位置を検索します。
    //  文字列の一致を探す関数。
    //  見つからない場合は false を返します。
    //  strpos($a, $B) は、$a の中に $b が含まれているかを調べます。
    //  ある場合は、その位置を返します。
    //  例えば、$referer が 'http://example.com/page' で、
    //  $host が 'example.com' の場合、
    //  strpos($referer, $host) は 7 を返します（0から始まる位置）。
    //  もし、$referer が空文字列の場合や、
    //  $host が $referer に含まれていない場合は false を返します。
    //  つまり、リファラーが空、またはホストがリファラーに含まれていない場合に
    //  条件が成立します。
    if (empty($referer) || strpos($referer, $host) === false) {
        // 警告レベル（ブロックはしない）
        // 補助的な監視に留めるため、処理を止めない。
        return false;
    }else {
        return true;
    }
}