<?php
//  Composer がインストールしたクラスを
//  使えるようにする記述
require_once dirname(__DIR__) . '/vendor/autoload.php';

//  Dotenv\Dotenv は Dotenv という名前空間
//  にある Dotenv というクラスという意味。
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Dotenv\Exception\InvalidPathException;
use Dotenv\Exception\ValidationException;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
try {
    $dotenv->load();
} catch (InvalidPathException $e) {
    //  .env ファイルが見つからない場合の処理
    throw $e;  //  例外を再スロー
} catch (InvalidFileException $e) {
    //  .env ファイルの形式が正しくない場合の処理
    throw $e;  //  例外を再スロー
}

//  必須の設定値を確認する。
try {
    $dotenv->required([
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
    ])->notEmpty();
} catch (ValidationException $e) {
    //  必須の環境変数が設定されていない場合の処理
    throw $e;  //  例外を再スロー
}
