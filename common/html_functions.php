
<?php
    
    // value="{$button}"と表示された 
    //  HTMLのボタンで送信される
    //  form をhtml で表示する関数
    //  function show_edit_input_common($id, $name, $grade, $old_id,$data, $button) 
    function show_edit_input_common($id, $name, $grade, $old_id, $data, $button, $token) {
        $error = '';
        $error = get_error();
        //  sessionからエラーを取得する関数 
        //  get_error()は、$_SESSION['error']が存在する場合にその値を返し、
        //  存在しない場合は空文字を返す関数です。
        //  この関数は、エラーメッセージを表示するために使用されます。
        //  common.phpに定義します。

        //  フォームの上部を表示
        echo <<<INPUT_TOP
<!-- php での互換性を確保するために行頭から記述 -->
<form action="post_data.php" method="post" class="operation-form" id="commonForm">
<p>学生番号</p>
<input type="number" name="id" value="{$id}" id="idInput" placeholder="Id">
<p id="idError" class="error-message"></p>
<p>名前</p>
<input type="text" name="name" value="{$name}" id="nameInput" placeholder="Name">
<p id="nameError" class="error-message"></p>
<p>学年</p>
<select name="grade">
INPUT_TOP;
        for ($i = 1; $i <= 3; $i++) {
            echo "<option value=\"{$i}\"";
            //  \" は、ダブルクォートをエスケープするために使用
            //  "を文字として扱うために、バックスラッシュでエスケープ
            //  optionタグのうち、
            // $gradeと同じ値のものに selected 属性を付与
            if ($i == $grade) {
                echo " selected";
            }
            echo ">";
            echo $i;
            echo "</option>";
        }
        //  フォームの下部を表示
        //  selectタグの閉じタグを表示
        //  $error が空でない場合は、エラーメッセージ
        echo <<<INPUT_BOTTOM
<!-- php での互換性を確保するために行頭から記述 -->
</select>
<p>{$error}</p>
<input type="hidden" name="old_id" value="{$old_id}">
<input type="hidden" name="data" value="{$data}">
<!-- `<input type="submit" name="button" value="{$button}">`は、
    `name`属性が`button`で、`value`属性にボタンのラベルが設定されます。
    ただし、HTML5では、`<button>`タグを使用することが推奨されています。
    そのため、以下のように書き換えます。
<input type="submit" name="button" value="{$button}">
-->
<button type="submit" >{$button}</button>
<input type="hidden" name="csrf_token" value="{$token}">
</form>
INPUT_BOTTOM;
    }

//  <!-- `value`が空や未定義 → `placeholder`が表示される
//  `value`に値がある → その値が表示され、`placeholder`は表示されない
// **例：**
// <input type="text" value="" placeholder="例) 1001">
// ```
// → 入力欄には「例) 1001」と薄く表示されます。 

//  function show_top($heading="学生一覧") でhtml のhead body h1 を
//  php で表示する関数を定義
    function show_top($heading = "学生一覧") {
        echo <<<STUDENT_LIST
<!-- php での互換性を確保するために行頭から記述 -->
<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>学生リスト</title>
        <link rel="stylesheet" href="sanitize.css">
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <h1>{$heading}</h1>
STUDENT_LIST;

        $error = get_error();
        if ($error !== '') {
            //  エラーメッセージがある場合は、表示します。
            //  htmlspecialchars() を使用して、XSS 対策を行います。
            $error = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
            echo "<p class='error-message'>エラー: {$error}</p>";
        }   

        if (isset($_SESSION['timeout_info'])) {
    $timeout_info = $_SESSION['timeout_info'];
    echo "<div class='timeout-message'>";
    echo "<h2>セッション終了のお知らせ</h2>";
    echo "<p>{$timeout_info['message']}</p>";
    echo "<p>非アクティブ時間: {$timeout_info['inactive_minutes']}分</p>";
    echo "<p>終了時刻: {$timeout_info['timestamp']}</p>";
    echo "</div>";
    
    // メッセージを表示したら削除
    unset($_SESSION['timeout_info']);
}
    }

    //  show_top()関数は、HTMLのヘッダーとボディの開始部分を表示します。
    //  show_bottom()関数は、HTMLのボディの終了部分をphpで表示します。
    function show_bottom($return_top = false) {
        if ($return_top===true) {
            echo <<<RETURN_TOP
<!-- php での互換性を確保するために行頭から記述 -->
<p><a href="index.php">学生一覧に戻る</a></p>
RETURN_TOP;
        }
        echo <<<BOTTOM
<!-- php での互換性を確保するために行頭から記述 -->
<!--javascriptを読み込みます-->
<script src="script.js"></script>
</body>
</html>
BOTTOM;
    }

    //  show_input()は、新しい学生情報を入力するためのフォームを表示する関数です。
    function show_input($token) {
        
        show_edit_input_common('', '', 1, '', 'create', '登録', $token);
    }

    //  DBManager クラスの get_student($id) メソッドを使用して
    //  学生情報を $member に連想配列で代入する。
    //  そして、$member の値を show_student($member) 関数に渡して
    //  table で学生情報を表示する
    function show_student($member) {
        //  XSS 対策として htmlspecialchars() を使用して
        //  特殊文字をエスケープします。
        $id    = htmlspecialchars($member['id'], ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8');
        $grade = htmlspecialchars($member['grade'], ENT_QUOTES, 'UTF-8');
        echo <<<STUDENT_INFO
<!-- php での互換性を確保するために行頭から記述 -->
<table class="table">
    <thead>
        <tr>
            <th>学生番号</th>
            <th>名前</th>
            <th>学年</th>
        </tr>
    </thead>

    <tbody>
        <tr>
            <td>{$id}</td>
            <td>{$name}</td>
            <td>{$grade}</td>
        </tr>        
    </tbody>

    <tfoot>
        <tr><td>合計</td><td colspan="2">1名</td></tr>   
    </tfoot>
</table>
STUDENT_INFO;
    }

    //  show_delete($member) 関数は、
    //  学生情報を削除するためのフォームを表示します。
    //  DBManager クラスの get_student($id) メソッドを使用して
    //  学生情報を $member に連想配列で代入する。
    //  $id に対応する学生情報が存在しない場合は、
    //  $member に null が代入されて返ってきます。
    function show_delete($member,$token) {
        if ($member !== null) {
            show_student($member);
        }else {
            die('$member が null です。学生情報が存在しません。');
        }
        $error = "";
        $error = get_error();
        $error = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
        $id = htmlspecialchars($member['id'], ENT_QUOTES, 'UTF-8');
        
        echo <<<DELETE_FORM
<!-- php での互換性を確保するために行頭から記述 -->
<form action="post_data.php" method="post" class="operation-form">
<p>この情報を削除しますか？</p>
<p>{$error}</p>
<input type="hidden" name="id" value="{$id}">
<input type="hidden" name="data" value="delete">
<input type="hidden" name="csrf_token" value="{$token}">
<button type="submit">削除</button>
</form>
DELETE_FORM;
    }

    //  show_update($id, $name, $grade, $old_id) 関数は、
    //  学生情報を更新するためのフォームを表示します。
    //  show_edit_input_common() 関数を使用して、
    //  学生番号、名前、学年、古い学生番号を引数として渡し、
    //  data は update に設定します。
    //  ボタンのラベルは「更新」とします。
    function show_update($id, $name, $grade, $old_id, $token) {
        show_edit_input_common($id, $name, $grade, $old_id, 'update', '更新', $token);
    }

    //  DBManager クラスの get_allstudents() は
    //  学生情報を全て取得するメソッドです。
    //  戻り値として、
    //  $member に2次元配列でデータベースの値が代入されます。
    //  execute() メソッドは、戻り値として
    //  成功した場合は true を返し、
    //  失敗した場合は false を返します。
    //  
    //  function get_allstudents() で
    //  データ取得に失敗した場合は、
    //  戻り値として null を返します。
    //  function show_student_list($member) は、
    //  テーブルに$member の値を表示する関数です。
    function show_student_list($members) {
        echo <<<TABLE_TOP
<!-- php での互換性を確保するために行頭から記述 -->
<table class="table">
    <thead>
        <tr>
            <th>学生番号</th>
            <th>名前</th>
            <th>学年</th>
            <th>編集操作</th>
            <th>削除操作</th>
        </tr>
    </thead>
    <tbody>
TABLE_TOP;
        
        //  $member は2次元配列で、各学生の情報が格納されています。
        //  foreach文を使用して、$memberの各行を処理します。
        foreach ($members as $row) {
            $id = htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8');
            $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
            $grade = htmlspecialchars($row['grade'], ENT_QUOTES, 'UTF-8');
            $token = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
            //  htmlspecialchars() 関数は、HTML特殊文字をエスケープします。
            //  ENT_QUOTES は、シングルクォートとダブルクォートの両方をエスケープします。
            //  'UTF-8' は、文字エンコーディングを指定します。
    echo <<<TR
<!-- php での互換性を確保するために行頭から記述 -->
<tr>
    <td>{$id}</td>
    <td>{$name}</td>
    <td>{$grade}</td>
    <td>
        <form action="student_edit.php" method="post" class="table-form">
            <input type="hidden" name="id" value="{$id}">
            <input type="hidden" name="data" value="update">
            <input type="hidden" name="csrf_token" value="{$token}">
            <button type="submit">編集確認へ...</button>
        </form>
    </td>
    <!--  削除操作のフォーム -->
    <td>
        <form action="student_edit.php" method="post" class="table-form">
            <input type="hidden" name="id" value="{$id}">
            <input type="hidden" name="data" value="delete">
            <input type="hidden" name="csrf_token" value="{$token}">
            <button type="submit">削除確認へ...</button>
        </form>
    </td>
</tr>
TR;
    }
        
        //  テーブルのフッターを表示
        //  合計行数を表示するために、count()関数を使用して
        //  $membersの行数を取得し、人数を表示します。
        $total = count($members);
        
        echo <<<TABLE_BOTTOM
<!-- php での互換性を確保するために行頭から記述 -->
</tbody>
<tfoot>
    <tr>
        <td>学籍情報</td>
        <td colspan="4">合計{$total}名</td>
    </tr>
</tfoot>
</table>
<br>
TABLE_BOTTOM;
    }
    
    //  更新ボタンをクリックすると、
    //  student_update.php に遷移し、
    //  
    //  削除ボタンをクリックすると、
    //  student_delete.php に遷移します。
    //
    //  student_update.php では
    //  show_update() 関数を呼び出して
    //  学生情報の更新フォームを表示します。
    //  
    //  student_delete.php では
    //  show_delete() 関数を呼び出して
    //  学生情報の削除フォームを表示します。
    //  それぞれ、
    //  更新ボタンと削除ボタンをクリックすると
    //  post_data.php にデータが送信されます。
    //  post_data.php では、$_POST['data']の値に応じて
    //  データベースの更新や削除を行います。
    //  data => 'update' の場合は
    //  学生情報の更新を行い、
    //  data => 'delete' の場合は
    //  学生情報の削除を行います。
    //  
    function show_operations($id,$data,$operation, $post_file, $token) {
        echo <<<OPERATIONS
<!-- php での互換性を確保するために行頭から記述 -->
<form action="{$post_file}" method="post" class="operation-form">
    <input type="hidden" name="id" value="{$id}">
    <input type="hidden" name="data" value="{$data}">
    <input type="hidden" name="csrf_token" value="{$token}">
    <button type="submit">{$operation}</button>
</form>
OPERATIONS;
    }

?>

<?php  //  以下テストコード
       //  コメントアウトしてありますので、
       //  必要に応じてコメントを外して実行してください。
    //  エラーメッセージを取得する関数を仮に定義
    //  本番環境では、get_error()関数は
    //  common.php に定義されているので
    //  コメントアウトします。
        // function get_error() {
        // $error = '';
        // return $error;
        // }

//  show_edit_input_common()関数のテストコード

#show_edit_input_common(1001, '山田太郎', 2, 1001, 'edit', '更新');
//  この関数は、学生情報の編集フォームを表示するために使用されます。
//  引数には、学生番号、名前、学年、古い学生番号、データの種類（例：'edit'）、
//  ボタンのラベル（例：'更新'）が含まれます。

//  function show_top() のテストコード
#show_top('学生一覧');

//  function show_input() のテストコード
#show_input();

//  show_student($member) 関数のテストコード
// $member = [
//     'id' => 1001,
//     'name' => '山田太郎',
//     'grade' => 2
// ];
// show_student($member);

// //  show_delete($member) 関数のテストコード
// show_delete($member);

//  show_update($id, $name, $grade, $old_id) 関数のテストコード
#show_update(1001, '山田太郎', 2, 1001);

//  
//
//  show_student_list($member) 関数のテストコード
// $member = [
//     ['id' => 1001, 'name' => '山田太郎', 'grade' => 2],
//     ['id' => 1002, 'name' => '佐藤花子', 'grade' => 1],
//     ['id' => 1003, 'name' => '鈴木一郎', 'grade' => 3]
// ];
// show_student_list($member);

//  show_operations($id) 関数のテストコード
#show_operations(1001);
?>