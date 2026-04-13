# Dsn コンポーネント

**パッケージ:** `wppack/dsn`
**名前空間:** `WpPack\Component\Dsn\`
**レイヤー:** Infrastructure

Data Source Name（DSN）文字列を解析する共通コンポーネントです。Database、Cache、Mailer、Storage の各コンポーネントで共有されます。

## インストール

```bash
composer require wppack/dsn
```

## 使用方法

```php
use WpPack\Component\Dsn\Dsn;

$dsn = Dsn::fromString('mysql://user:pass@host:3306/dbname?charset=utf8mb4');

$dsn->getScheme();   // 'mysql'
$dsn->getHost();     // 'host'
$dsn->getUser();     // 'user'
$dsn->getPassword(); // 'pass'
$dsn->getPort();     // 3306
$dsn->getPath();     // '/dbname'
$dsn->getOption('charset'); // 'utf8mb4'
```

## 対応フォーマット

| フォーマット | 例 |
|------------|-----|
| 標準 URI | `mysql://user:pass@host:3306/dbname` |
| クエリパラメータ | `mysql://host/db?charset=utf8mb4` |
| Unix ソケット | `redis:///var/run/redis.sock` |
| No-host URI | `apcu://` |
| 配列パラメータ | `redis:?host[]=node1:6379&host[]=node2:6379` |
| URL エンコード | `mysql://user%40name:p%40ss@host/db` |

## API

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `Dsn::fromString(string)` | `Dsn` | DSN 文字列をパース |
| `getScheme()` | `string` | スキーム（`mysql`、`redis` 等） |
| `getHost()` | `?string` | ホスト名 |
| `getUser()` | `?string` | ユーザー名 |
| `getPassword()` | `?string` | パスワード（`#[\SensitiveParameter]`） |
| `getPort()` | `?int` | ポート番号 |
| `getPath()` | `?string` | パス |
| `getOption(string, ?string)` | `?string` | クエリパラメータ取得 |
| `getArrayOption(string)` | `list<string>` | 配列パラメータ取得 |
| `getOptions()` | `array` | 全パラメータ取得 |
