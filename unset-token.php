<?php
/**
 * unset-token.php
 * CSRFトークンをunsetする
 * tokenの使い捨て
 */
function unset_token(): void {
    //  unset token
if (isset($_SESSION['csrf_token'])){
    unset($_SESSION['csrf_token']);
}
if (isset($_SESSION['csrf_token_time'])){
    unset($_SESSION['csrf_token_time']);
}
if (isset($_SESSION['csrf_token'])){
    unset($_SESSION['csrf_token']);
}
}