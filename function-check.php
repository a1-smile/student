<?php


// 全ケース合計のカウンタ
$totalPass = 0;
$totalFail = 0;

// check() 関数を定義します。
function check(string $label, mixed $actual, mixed $expected): void {
    global $totalPass, $totalFail;
    if ($actual === $expected) {
        echo "  PASS: {$label}<br>";
        $totalPass++;
    } else {
        echo "  FAIL: {$label}"
            . " (actual=" . var_export($actual, true)
            . ", expected=" . var_export($expected, true) . ")<br>";
        $totalFail++;

        //  var_export($arg, true) は、
        //  引数の値を文字列として返します。
        //  例えば、var_export(123, true) は 
        //  "123" 
        //  という文字列を返します。
        //  表示はしない。
        //  var_export($arg, true) を使うことで、
        //  配列やオブジェクトの内容も
        //  文字列として表示できます。

    }
}
