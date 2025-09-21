# IP Prefix Project

## 概要
このプロジェクトは、IPアドレスからIPプレフィックスを取得するためのクラスと関数を提供します。IPv4およびIPv6アドレスに対応しており、指定されたIPアドレスに基づいてプレフィックスを計算する機能を持っています。

## ファイル構成
- `src/IpPrefixExtractor.php`: IPアドレスからプレフィックスを取得するクラス。
- `src/functions/ip_helpers.php`: IPアドレスの検証やプレフィックス取得に関連するヘルパー関数。
- `tests/IpPrefixExtractorTest.php`: `IpPrefixExtractor` クラスのユニットテスト。
- `tests/bootstrap.php`: テスト環境をセットアップするためのブートストラップファイル。
- `examples/example.php`: `IpPrefixExtractor` クラスの使用例。
- `composer.json`: プロジェクトの依存関係やオートローディングの設定。
- `phpunit.xml`: PHPUnitの設定ファイル。

## インストール手順
1. リポジトリをクローンします。
   ```
   git clone <repository-url>
   ```
2. プロジェクトディレクトリに移動します。
   ```
   cd ip-prefix-project
   ```
3. Composerを使用して依存関係をインストールします。
   ```
   composer install
   ```

## 使用方法
`IpPrefixExtractor` クラスを使用して、IPアドレスからプレフィックスを取得することができます。以下は基本的な使用例です。

```php
use src\IpPrefixExtractor;

$extractor = new IpPrefixExtractor();
$prefix = $extractor->getPrefix('192.168.1.1');
echo $prefix; // 出力: 192.168
```

## テスト
ユニットテストはPHPUnitを使用して実行できます。以下のコマンドでテストを実行します。

```
vendor/bin/phpunit
```

## ライセンス
このプロジェクトはMITライセンスの下で提供されています。詳細はLICENSEファイルを参照してください。