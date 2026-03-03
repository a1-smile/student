
<?php
function decreaseScore(
        int $score, 
        int $decreased, 
        int $isNoAnomaly,
        int $recaptchaSolved
        ): int {
        // 30分以内に減算された場合は
        // さらに減算はしない
        if ($decreased === 1) {
            return $score;
        }

        if ($isNoAnomaly === 1) {
            // 10分以内に異常がない場合はスコアを1減算
            $score -= 1;
            //  ゼロ以下にならないようにする
            $score = max($score, 0);

            return $score;

            //  10分以内に異常がない場合で
            //  reCAPTCHAを解いてアクセスして来る場合は
            //  は、想定されないが、
            //  -1減算し、return するロジックにする

            }
            
        if ($recaptchaSolved === 1) {
                // 異常があった場合でreCAPTCHAを
                // 解いた場合はスコアを4減算

                // 異常があって、10分以上経過して
                // reCAPTCHAを解いてアクセス
                // して来る場合は通常ありえない。
                // つまり、異常なし 
                // かつ 
                // reCAPTCHAを解いてアクセス
                // して来る場合は想定されないアクセスであると考えられる。
                // したがって、
                // この else if ブロックで
                // 異常があって、
                // reCAPTCHAを解いてアクセスして来る場合は
                // スコアを4減算するというロジックにする
            $score -= 4;
                //  ゼロ以下にならないようにする
            $score = max($score, 0);

            return $score;

            }
            // 異常があって、
            // 直近で reCAPTCHAを解いていない場合は
            // スコアを減算しない
            return $score;
            }

// テストケース
$argument_array = [
    // 30分以内に減算された場合はさらに減算しない
    [10, 1, 0, 0],
    // 10分以内に異常がない場合はスコアを1減算
    [10, 0, 1, 0],
    // 異常があった場合でreCAPTCHAを解いた場合はスコアを4減算
    [10, 0, 0, 1],
    // 異常があって、直近で reCAPTCHAを解いていない場合はスコアを減算しない
    [10, 0, 0, 0],
    // スコアがゼロ以下にならないようにする
    [ 3, 0, 0, 1],
];

echo "Testing decreaseScore function...<br>";
echo 'expected output:' . "<br>";
echo "Test case 0: 10<br>";
echo '30分以内に減算された場合はさらに減算しない' . "<br>";
echo "Test case 1: 9<br>";
echo '10分以内に異常がない場合はスコアを1減算' . "<br>";
echo "Test case 2: 6<br>";
echo '異常があった場合でreCAPTCHAを解いた場合はスコアを4減算' . "<br>";
echo "Test case 3: 10<br>";
echo '異常があって、直近で reCAPTCHAを解いていない場合はスコアを減算しない' . "<br>";
echo "Test case 4: 0<br>";
echo 'スコアがゼロ以下にならないようにする' . "<br>";
echo "<hr> ";
for ($i = 0; $i < count($argument_array); $i++) {
    $score = $argument_array[$i][0];
    $decreased = $argument_array[$i][1];
    $isNoAnomaly = $argument_array[$i][2];
    $recaptchaSolved = $argument_array[$i][3];

    $result = decreaseScore($score, $decreased, $isNoAnomaly, $recaptchaSolved);
    echo "Test case $i: $result<br>";
    echo "-------------------------<br> ";
}