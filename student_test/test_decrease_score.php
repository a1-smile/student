
<?php
function decreaseScore(
        int $score, 
        int $isSuspicious,
        int $decreased, 
        int $isNoAnomaly,
        int $recaptchaSolved
        ): int {

        //  疑わしいアクセスの場合は、スコアを減算しない
        if ($isSuspicious === 1) {
            return $score;
        }
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
    ['score' => 10, 'isSuspicious' => 0, 'decreased' => 1, 'isNoAnomaly' => 0, 'recaptchaSolved' => 0],
    // 10分以内に異常がない場合はスコアを1減算
    ['score' => 10, 'isSuspicious' => 0, 'decreased' => 0, 'isNoAnomaly' => 1, 'recaptchaSolved' => 0],
    // 異常があった場合でreCAPTCHAを解いた場合はスコアを4減算
    ['score' => 10, 'isSuspicious' => 0, 'decreased' => 0, 'isNoAnomaly' => 0, 'recaptchaSolved' =>  1],
    // 異常があって、直近で reCAPTCHAを解いていない場合はスコアを減算しない
    ['score' => 10, 'isSuspicious' => 0, 'decreased' => 0, 'isNoAnomaly' => 0, 'recaptchaSolved' => 0],
    // スコアがゼロ以下にならないようにする
    ['score' => 3, 'isSuspicious' => 0, 'decreased' => 0, 'isNoAnomaly' => 0, 'recaptchaSolved' => 1],
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
    $score = $argument_array[$i]['score'];
    $decreased = $argument_array[$i]['decreased'];
    $isNoAnomaly = $argument_array[$i]['isNoAnomaly'];
    $recaptchaSolved = $argument_array[$i]['recaptchaSolved'];
    $isSuspicious = $argument_array[$i]['isSuspicious'];

    $result = decreaseScore($score, $isSuspicious, $decreased, $isNoAnomaly, $recaptchaSolved);
    echo "Test case $i: $result<br>";
    echo "-------------------------<br> ";
}