<?php
//  dbmanager.php は DBManager クラスを定義するファイルです。

class DBManager {
    //  プロパティーの定義
    //  データベースアクセス情報
    private $access_info;
    //  データベースのユーザー名
    private $user;
    //  データベースのパスワード
    private $password;
    //  PDO インスタンス
    private $db = null;
    public function get_db() {
        return $this->db;
    }
    //  コンストラクタ
    public function __construct() {
        $this->access_info = 'mysql:host=localhost;dbname=school;charset=utf8mb4';
        $this->user = 'root';
        $this->password = 'root';
    }
    //  データベースに接続するメソッド
    public function connect() {
        try {
            $this->db = new PDO($this->access_info, $this->user, $this->password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo 'Connection failed: ' . $e->getMessage();
        }
    }
    //  データベースに接続しているか確認するメソッド
    public function is_connected() {
        //  $this->db が null でない場合は、接続されていると判断
        return $this->db !== null;  //  $this->db が null でない場合は true を返す
    }
    //  データベースから切断するメソッド
    public function disconnect() {
        $this->db = null;
    }
    //  データベースに保存されたすべての学生情報を取得するメソッド
    public function get_allstudents() {
        try {
        $this->connect();
        $stmt = $this->db->prepare("SELECT * FROM student ORDER BY id");
        $res = $stmt->execute();
        //  execute() の結果が false の場合は、例外をスローする設定になっているため、
        //  ここで例外が発生します。
        
            $member = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->disconnect();
            return $member;
        
           
        }catch (PDOException $e) {
            $this->disconnect();
            return null;
        }
    }

    //  id カラムが $id の学生情報を取得するメソッド
    public function get_student($id) {
        try {
            //  データベースに接続
            $this->connect();
            //  エラーの場合は例外をなげる設定になっている。
            //  SQL 文を準備
            $sql = 'SELECT * FROM student WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            //  プレースホルダーに値をバインド
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            //  SQL 文を実行
            $res = $stmt->execute();
            //  execute() の戻り値は、
            //  成功した場合は true、失敗した場合は false です。

            //  $member 変数に結果を格納
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            //  データベースから切断
            $this->disconnect();
            //  レコードが見つからない場合の処理fetch() は false を返すので、
            //  その場合は null を返すようにします。
            //  レコードが見つからない場合は、null を返す
            if ($member === false) {
                return null; // レコードが見つからない場合は null を返す
            }
            //  レコードが見つかった場合は、$member を連想配列の形でを返す
            return $member;

        }catch (PDOException $e) {
            $this->disconnect();
            return null;
        }
    }
    //  if_id_exists メソッドは、
    // 指定された ID の学生が
    // 存在するかどうかを確認するメソッドです。
    //  存在する場合は true を、存在しない場合は false を返します。
    public function if_id_exists($id) {
        if ($this->get_student($id) !== null) {
            return true; //  学生が存在する場合は true を返す
        }
        return false; //  学生が存在しない場合は false を返す
    }


    //  insert_student メソッドは、
    // 新しい学生情報をデータベースに挿入するメソッドです。
    public function insert_student($id, $name, $grade) {
        
        try {
            //  データベースに接続
            $this->connect();
            $sql = 'INSERT INTO student (id, name, grade) 
                    VALUES (:id, :name, :grade)';
            $stmt = $this->db->prepare($sql);
            //  プレースホルダーに値をバインド
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':grade', $grade, PDO::PARAM_INT);
            $res = $stmt->execute();
            ///  切断
            $this->disconnect();
            //  execute() の戻り値は、
            //  成功した場合は true、失敗した場合は false です。
            if ($res) {
                return true; //  挿入に成功した場合は true を返す
            }

        }catch (PDOException $e) {
            //  データベースから切断
            $this->disconnect();
            return false; //  挿入に失敗した場合は false を返す
    }
    $this->connect();
    return false; //  挿入に失敗した場合は false を返す
    }

    //  delete_student メソッドは、$idで指定された学生情報を
    //  データベースから削除するメソッドです。
    public function delete_student($id) {
        try {
            //  データベースに接続
            $this->connect();
            //  SQL 文を準備
            $sql = 'DELETE FROM student WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            //  プレースホルダーに値をバインド
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            //  SQL 文を実行
            $res = $stmt->execute();
            //  execute() の戻り値は、
            //  成功した場合は true、失敗した場合は false です。
            //  $res が true の場合は、削除に成功したことを意味します。
            if ($res) {
                //  データベースから切断
                $this->disconnect();
                return true; //  削除に成功した場合は true を返す
            }
    }catch (PDOException $e) {
            //  データベースから切断
            $this->disconnect();
            return false; //  削除に失敗した場合は false を返す
        }
        $this->disconnect();
        return false; //  削除に失敗した場合は false を返す
    }
    //  update_student メソッドは、$old_id で指定された学生情報を
    //  $new_id, $new_name, $new_grade 
    // で指定された新しい情報に更新するメソッドです。
    public function update_student($new_id, $new_name, $new_grade, $old_id) {
        try {
            //  データベースに接続
            $this->connect();
            //  SQL 文を準備
            $sql = 'UPDATE student
                    SET 
                        id = :new_id, 
                        name = :new_name,
                        grade = :new_grade
                    WHERE id = :old_id';
            $stmt = $this->db->prepare($sql);
            //  プレースホルダーに値をバインド
            $stmt->bindValue(':new_id', $new_id, PDO::PARAM_INT);
            $stmt->bindValue(':new_name', $new_name, PDO::PARAM_STR);
            $stmt->bindValue(':new_grade', $new_grade, PDO::PARAM_INT);
            $stmt->bindValue(':old_id', $old_id, PDO::PARAM_INT);
            //  SQL 文を実行
            $res = $stmt->execute();
            //  execute() の戻り値は、
            //  成功した場合は true、失敗した場合は false です。
            //  $res が true の場合は、更新に成功したことを意味します。
            if ($res) {
                //  データベースから切断
                $this->disconnect();
                return true; //  更新に成功した場合は true を返す
            }
}catch (PDOException $e) {
            //  データベースから切断
            $this->disconnect();
            return false; //  更新に失敗した場合は false を返す
        }
    

// この部分（try-catchの外側）は、**例外（PDOException）としてcatchできなかった場合**や、  
// `$stmt->execute()`がfalseを返したけれど例外が発生しなかった場合など、  
// **catchブロックに入らなかったエラーや失敗時の処理**と考えて問題ありません。

// ---



// - try内でSQL実行が失敗しても例外が発生しなかった場合
// - catchで捕まえられなかったその他の異常時

// このような場合に、`$this->disconnect(); return false;`が実行されます。  
// **「catchできなかったエラーや失敗時の保険的な処理」**です。

        $this->disconnect();
        return false; //  更新に失敗した場合は false を返す
    }
}

?>

<?php
//  以下は、DBManager クラスのテストコードです。
//  DBManager クラスのインスタンスを作成
$db_manager = new DBManager();
//  データベースに接続
$db_manager->connect();
//  データベースに接続できたか確認
if ($db_manager->is_connected()) {
    echo "データベースに接続しました。<br>";
    //  データベースから切断します。
    $db_manager->disconnect(); 
        if ($db_manager->get_db()===null) {
        //  データベースから切断できたか確認
        echo "データベースから切断しました。<br>";
    }
    }else {
    echo "データベースに接続できませんでした。<br>";
}
//  データベースからすべての学生情報を取得
$students = $db_manager->get_allstudents();
     if ($students !== null) {
        
foreach ($students as $student) {
    echo "ID: " . htmlspecialchars($student['id'], ENT_QUOTES, 'UTF-8')
        . ", 名前: " . htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8')
        . ", 学年:" . htmlspecialchars($student['grade'], ENT_QUOTES, 'UTF-8') . "<br>";
}
    /*foreach ($students as $student) {
        echo "ID: " . $student['id'] . ", 名前: " . $student['name'] .", 学年:".$student[ 'grade' ]."<br>";  }
} */
}else {
    echo "学生情報が取得できませんでした。<br>";
}

//  特定の学生情報を取得
$student_id = 1001; // 取得したい学生の ID
//  $student_id の値は、データベースに存在する学生の ID に置き換えてください。
$student = $db_manager->get_student($student_id);
//  学生情報が連想配列として取得できた場合
if ($student !== null) {
    echo "get_student メソッドを使用して、学生情報を取得しました。<br>";
    echo "ID: " . htmlspecialchars($student['id'], ENT_QUOTES, 'UTF-8')
        . ", 名前: " . htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8')
        . ", 学年: " . htmlspecialchars($student['grade'], ENT_QUOTES, 'UTF-8') . "<br>";
}else {
    echo "ID: $student_id の学生情報は存在しません。<br>";
}

//  存在しない学生 ID を指定して、get_student メソッドを呼び出す
$non_existent_id = 9999; // 存在しない学生の ID
$non_existent_student = $db_manager->get_student($non_existent_id);
if ($non_existent_student !== null) {
    echo "get_student メソッドを使用して、学生情報を取得しました。<br>";
    echo "ID: " . htmlspecialchars($student['id'], ENT_QUOTES, 'UTF-8')
        . ", 名前: " . htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8')
        . ", 学年: " . htmlspecialchars($student['grade'], ENT_QUOTES, 'UTF-8') . "<br>";
}else {
    echo "ID: $non_existent_id の学生情報は存在しません。<br>";
}

//  存在する学生 ID を指定して、if_id_exists メソッドを呼び出す
$existing_id = 1001; // 存在する学生の ID
if ($db_manager->if_id_exists($existing_id)) {
    echo "ID: $existing_id の学生は存在します。<br>";
}else {
    echo "ID: $existing_id の学生は存在しません。<br>";
}

//  存在しない学生 ID を指定して、if_id_exists メソッドを呼び出す
$non_existing_id = 9999; // 存在しない学生の ID
if ($db_manager->if_id_exists($non_existing_id)) {
    echo "ID: $non_existing_id の学生は存在します。<br>";
}else {
    echo "ID: $non_existing_id の学生は存在しません。<br>";
}

//  新しい学生情報を挿入
//  注意：リロードするたびに id を変える必要があります。
//  すでに存在する ID を使用すると、挿入に失敗します。
$new_id = 1009; // 新しい学生の ID
$new_name = "新しい学生";
$new_grade = 1; // 新しい学生の学年
if ($db_manager->insert_student($new_id, $new_name, $new_grade)) {
    echo "新しい学生情報を挿入しました。<br>";
}

//  挿入した学生情報を再度取得して確認
$inserted_student = $db_manager->get_student($new_id);
if ($inserted_student !== null) {
    echo "挿入した学生情報を確認しました。<br>";
    echo "ID: " . htmlspecialchars($inserted_student['id'], ENT_QUOTES, 'UTF-8')
        . ", 名前: " . htmlspecialchars($inserted_student['name'], ENT_QUOTES, 'UTF-8')
        . ", 学年: " . htmlspecialchars($inserted_student['grade'], ENT_QUOTES, 'UTF-8') . "<br>";
}

//  挿入した学生情報を削除
if ($db_manager->delete_student($new_id)) {
    echo "ID: $new_id の学生情報を削除しました。<br>";

//  データベースからすべての学生情報を取得
$students = $db_manager->get_allstudents();
     if ($students !== null) {
        
foreach ($students as $student) {
    echo "ID: " . htmlspecialchars($student['id'], ENT_QUOTES, 'UTF-8')
        . ", 名前: " . htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8')
        . ", 学年:" . htmlspecialchars($student['grade'], ENT_QUOTES, 'UTF-8') . "<br>";
}
    /*foreach ($students as $student) {
        echo "ID: " . $student['id'] . ", 名前: " . $student['name'] .", 学年:".$student[ 'grade' ]."<br>";  }
} */
}
}else {
    echo "学生情報が取得できませんでした。<br>";
}
//  update_student($new_id, $new_name, $new_grade, $old_id)
//  をテストする。
//  新しい学生情報
$new_id = 1011; // 新しい学生の ID
$new_name = "更新された学生";
$new_grade = 2; // 新しい学生の学年
$old_id = 1001; // 更新前の学生の ID

//  学生情報を更新に成功した場合の処理
if ($db_manager->update_student($new_id, $new_name, $new_grade, $old_id)) {
    echo "ID: $old_id の学生情報を更新しました。<br>";
    //  データベースからすべての学生情報を取得
$students = $db_manager->get_allstudents();
     if ($students !== null) {
        
foreach ($students as $student) {
    echo "ID: " . htmlspecialchars($student['id'], ENT_QUOTES, 'UTF-8')
        . ", 名前: " . htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8')
        . ", 学年:" . htmlspecialchars($student['grade'], ENT_QUOTES, 'UTF-8') . "<br>";
}
    /*foreach ($students as $student) {
        echo "ID: " . $student['id'] . ", 名前: " . $student['name'] .", 学年:".$student[ 'grade' ]."<br>";  }
} */
}
}else {
    echo "学生情報が取得できませんでした。<br>";
}
