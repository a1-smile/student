<?php
    function check_input($id, $name, $grade, &$error) {
        //  $error メモリリセット
        $error = '';
        //  $id と $name が空欄でないかチェック
        if ($id === '' or $name === '') {
            $error = '入力されていない項目があります。';
            return false;
        }
        //  $id が正しく入力されているかをチェック
        //  正規表現 /^[1-3][0-9]{3}$/ でチェック
        if (!preg_match('/^[1-3][0-9]{3}$/', $id)) {
            $error = 'idには1～3ではじまる4桁の整数を入力してください。';
            return false;
        }
        //  $grade の値がセットされているかチェック
        if ($grade === '' ) {
            $error = '学年が選択されていません。';
            return false;
        }
        return true;
    }
    //  この関数は、学生情報の入力値をチェックするためのものです。
    //  $id, $name, $grade の値をチェックし、
    //  エラーがあれば $error にエラーメッセージをセットします。
    //  入力値が正しい場合は true を返し、
    //  エラーがある場合は false を返します。
    //  $error は参照渡しで、
    //  エラーメッセージを格納するために使用されます。
    //  function check_input のテストコードを以下に示します。

    //  function check_input のテストコード
    // $student_data = [
    //     ['id' => '1234', 'name' => '山田太郎', 'grade' => 1],
    //     ['id' => '8456', 'name' => '鈴木一郎', 'grade' => 3],
    //     ['id' => '2345', 'name' => '', 'grade' => 2],
    //     ['id' => '2345', 'name' => '佐藤花子', 'grade' => ''],
    //     ['id' => '2345', 'name' => '佐藤花子', 'grade' => 2],
    //     ]
    // ;
    // foreach ($student_data as $data) {
    //     $error = '';
    //     $result = check_input($data['id'], $data['name'], $data['grade'], $error);
    //     if ($result) {
    //         echo "ID: {$data['id']}, Name: {$data['name']}, Grade: {$data['grade']} - 入力値は正しいです。<br>";
    //     }else {
    //         echo $error . "<br>";
    // }
    //     }
    //  上記のテストコードを実行すると、以下のような出力が得られます。
//     ID: 1234, Name: 山田太郎, Grade: 1 - 入力値は正しいです。
// idには1～3ではじまる4桁の整数を入力してください。
// 入力されていない項目があります。
// 学年が選択されていません。
// ID: 2345, Name: 佐藤花子, Grade: 2 - 入力値は正しいです。

/* 必要に応じてテストコードを実行して、
関数の動作を確認してください。
コメントアウトします。 */
?>