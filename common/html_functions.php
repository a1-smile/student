
<?php
    
    // value="{$button}"と表示された 
    //  HTMLのボタンで送信される
    //  form をhtml で表示する関数
    //  function show_edit_input_common($id, $name, $grade, $old_id,$data, $button) 
    function show_edit_input_common($id, $name, $grade, $old_id, $data, $button) {
        $error = '';
        $error = get_error();
        //  GETメソッドからエラーを取得する関数 
        //  get_error()は、$_GET['error']が存在する場合にその値を返し、
        //  存在しない場合は空文字を返す関数です。
        //  この関数は、エラーメッセージを表示するために使用されます。
        //  common.phpに定義します。

        //  フォームの上部を表示
        echo <<<INPUT_TOP
        <form action="post_data.php" method="post">
        <p>学生番号</p>
        <input type="text" name="id" value="{$id}" placeholder="例) 1001">
        <p>名前</p>
        <input type="text" name="name" value="{$name}" placeholder="例) 山田太郎">
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
        echo <<<INPUT_BOTTOM
        </select>
        <p>{$error}</p>
        <input type="hidden" name="old_id" value="{$old_id}">
        <input type="hidden" name="data" value="{$data}">
        <input type="submit" name="button" value="{$button}">
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
        <!DOCTYPE html>
        <html lang="ja">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>学生リスト</title>
                <link rel="stylesheet" href="../../style/sanitize.css">
                <link rel="stylesheet" href="../../style/style.css">
            </head>
            <body>
                <h1>{$heading}</h1>
        STUDENT_LIST;
    }

    //  show_top()関数は、HTMLのヘッダーとボディの開始部分を表示します。
    //  show_bottom()関数は、HTMLのボディの終了部分をphpで表示します。
    function show_bottom($return_top = false) {
        if ($return_top===true) {
            echo <<<RETURN_TOP
            <p><a href="index.php">学生一覧に戻る</a></p>
        RETURN_TOP;
        }
        echo <<<BOTTOM
        </body>
        </html>
        BOTTOM;
    }

    //  show_input()は、新しい学生情報を入力するためのフォームを表示する関数です。
    function show_input() {
        $error = get_error();
        //  get_error()関数は、$_GET['error']が存在する場合にその値を返し、
        //  存在しない場合は空文字を返す関数です。
        //  この関数は、エラーメッセージを表示するために使用されます。
        show_edit_input_common('', '', 1, '', 'create', '登録');
    }

    //  DBManager クラスの get_student($id) メソッドを使用して
    //  学生情報を $member に連想配列で代入する。
    //  そして、$member の値を show_student($member) 関数に渡して
    //  table で学生情報を表示する
    function show_student($member) {
        echo <<<STUDENT_INFO
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
                <td>{$member['id']}</td>
                <td>{$member['name']}</td>
                <td>{$member['grade']}</td>
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
    function show_delete($member) {
        if ($member !== null) {
            show_student($member);
        }
        $error = "";
        $error = get_error();
        echo <<<DELETE_FORM
        <form action="post_data.php" method="post">
        <p>この情報を削除しますか？</p>
        <input type="hidden" name="id" value="{$member['id']}">
        <input type="hidden" name="data" value="delete">
        <input type="submit" value="削除">
        </form>
        DELETE_FORM;
        }

    //  show_update($id, $name, $grade, $old_id) 関数は、
    //  学生情報を更新するためのフォームを表示します。
    //  show_edit_input_common() 関数を使用して、
    //  学生番号、名前、学年、古い学生番号を引数として渡し、
    //  data は update に設定します。
    //  ボタンのラベルは「更新」とします。
    function show_update($id, $name, $grade, $old_id) {
        show_edit_input_common($id, $name, $grade, $old_id, 'update', '更新');
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
    function show_student_list($member) {
        echo <<<TABLE_TOP
        <table class="table">
            <thead>
                <tr>
                    <th>学生番号</th>
                    <th>名前</th>
                    <th>学年</th>
                </tr>
            </thead>
            <tbody>
    TABLE_TOP;
        
        //  $member は2次元配列で、各学生の情報が格納されています。
        //  foreach文を使用して、$memberの各行を処理します。
        foreach ($member as $row) {
            echo <<<TR
            <tr>
                <td>{$row['id']}</td>
                <td><a href="student_edit.php?id={$row["id"]}">{$row['name']}</a></td>
                <td>{$row['grade']}</td>
            </tr>
        TR;
        }
        
        //  テーブルのフッターを表示
        //  合計行数を表示するために、count()関数を使用して
        //  $memberの行数を取得し、1名と表示します。
        $total = count($member);
        
        echo <<<TABLE_BOTTOM
        </tbody>
        <tfoot>
            <tr>
                <td>学籍情報</td>
                <td colspan="2">合計{$total}名</td>
            </tr>
        </tfoot>
        </table>
        <br>
    TABLE_BOTTOM;
    }
    //  編集画面の操作の一覧の表示
    //  index.php で function show_student_list($member) を
    //  呼び出した後に、各学生の名前をクリックすると
    //  学生情報の編集画面 student_edit.php に遷移します。
    //  student_edit.php では、
    //  function show_operations($id) を呼び出して
    //  学生情報の更新と削除のリンクを表示します。
    //  
    //  引数 $id は、
    //  <a href="student_edit.php?id={$id}">mane</a>
    //  という形式で、GETメソッドで渡されます。
    //
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
    function show_operations($id) {
        echo <<<OPERATIONS
            <a href="student_update.php?id={$id}">更新</a>
            <br>
            <a href="student_delete.php?id={$id}">削除</a>
            <br>
            <br>           
        OPERATIONS;
    }

?>
<?php  //  以下テストコード
    //  エラーメッセージを取得する関数を仮に定義
function get_error() {
        $error = '';
        return $error;
    }

//  show_edit_input_common()関数はのテストコード

show_edit_input_common(1001, '山田太郎', 2, 1001, 'edit', '更新');
//  この関数は、学生情報の編集フォームを表示するために使用されます。
//  引数には、学生番号、名前、学年、古い学生番号、データの種類（例：'edit'）、
//  ボタンのラベル（例：'更新'）が含まれます。

//  function show_top() のテストコード
show_top('学生一覧');

//  function show_input() のテストコード
show_input();

//  show_student($member) 関数のテストコード
$member = [
    'id' => 1001,
    'name' => '山田太郎',
    'grade' => 2
];
show_student($member);

//  show_delete($member) 関数のテストコード
show_delete($member);

//  show_update($id, $name, $grade, $old_id) 関数のテストコード
show_update(1001, '山田太郎', 2, 1001);

//  
//
//  show_student_list($member) 関数のテストコード
$member = [
    ['id' => 1001, 'name' => '山田太郎', 'grade' => 2],
    ['id' => 1002, 'name' => '佐藤花子', 'grade' => 1],
    ['id' => 1003, 'name' => '鈴木一郎', 'grade' => 3]
];
show_student_list($member);

//  show_operations($id) 関数のテストコード
show_operations(1001);
?>