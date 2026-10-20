# DB への接続情報を`student\.env` から dotenv を使用して読み込んで `common\dbmanager.php` に定義された DBManager に渡す方法

## student\common に get-db-env.php

student\common に `get-db-env.php` を設定して、
```php
<?php
//  Composer がインストールしたクラスを
//  使えるようにする記述
// 1階層上に上がって vendor フォルダを読み込む
require_once dirname(__DIR__) . '/vendor/autoload.php';

//  Dotenv\Dotenv は Dotenv という名前空間
//  にある Dotenv というクラスという意味。
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Dotenv\Exception\InvalidPathException;
use Dotenv\Exception\ValidationException;

// 1つ上の階層の .env を読み込む
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
try {
    $dotenv->load();
} catch (InvalidPathException $e) {
    //exit('.env ファイルが見つかりません');
    // 再スロー
    throw $e;

} catch (InvalidFileException $e) {
    // exit('.env ファイルの形式が正しくありません。');
    // 再スロー
    throw $e; 
}

//  必須の設定値を確認する場合は、以下のように記述します。
try {
    $dotenv->required([
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
    ])->notEmpty();
} catch (ValidationException $e) {
    error_log($e->getMessage());
    //exit('必須の環境変数が設定されていません: ');
    // 再スロー
    throw $e;
}
```

## `student\common.php` で `common\get-db-env.php` を `require_once`

`student\common.php` で


```php

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Dotenv\Exception\InvalidPathException;
use Dotenv\Exception\ValidationException;

try {
    require_once __DIR__ . `/common/get-db-env.php`
} catch (InvalidFileException $e
){
    error_log($e->getMessage());
    exit('.env ファイルの形式が正しくありません。');
} catch (InvalidPathException $e){
    error_log($e->getMessage());
    exit('.env ファイルが見つかりません');
} catch (ValidationException $e){
    error_log($e->getMessage());
    exit('必須の環境変数が設定されていません: ');
}
```

## student\common\dbmanager.php では DBManager クラスにコンストラクタで接続情報を渡す。
すでに、
dbmanager.php を書き換えました。


## student\common.php で DBManager をインスタンス化する

```php
// 環境変数を取得
$host =$_ENV['DB_HOST'];
$name = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$password = $_ENV['DB_PASSWORD'];

//  インスタンス化 しかしまだ PDO 接続はしていない
    $dbm = new DBManager(
        $host,
        $name,
        $user,
        $password
    );

// 一度 pdo 接続
    try {
        $dbm -> connect();
    } catch (PDOException $e){
        errorlog($e->getmessage);
        exit(データベース接続に失敗しました)；
    }

    $pdo_connect = $dbm->get_db();

// 切断
    $dbm->disconnect();

    $pdo_disconnect =$dbm->get_db();
```
