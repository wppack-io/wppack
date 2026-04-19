# Dsn コンポーネント

**パッケージ:** `wppack/dsn`
**名前空間:** `WPPack\Component\Dsn\`
**レイヤー:** Infrastructure

Data Source Name (DSN) 文字列を解析する共通コンポーネント。Database / Cache / Mailer / Storage / Monitoring など、DSN を扱う全てのコンポーネントが canonical なパーサーとしてこれを利用します。

WPPack では **DSN 文字列のパースは必ず `WPPack\Component\Dsn\Dsn` を通す** のがルールです。`parse_url` / `parse_str` を使った独自実装は禁止です。

## スコープ

**対応する**:

- URI 形式の接続文字列 (`scheme://[user:pass@]host[:port][/path][?query]`)
- Unix ソケット / SQLite のようなパス型 (`scheme:///path`)
- ホストを持たないスキーム (`scheme:?query`、cluster / sentinel 構成など)
- クエリパラメータの配列展開 (`key[]=v1&key[]=v2` および `key[v1]&key[v2]`)
- URL エンコード済み credentials (`user%40name:p%40ss@host` など)

**対応しない**:

- PDO 形式の DSN (`mysql:host=...;dbname=...`) — 独自形式のため外部
- HTTP URL 全般 — `parse_url` を使ってください
- JDBC 形式 (`jdbc:mysql://...`) — スキーム欄に `jdbc` のみ入り、内側のパースは別途必要

## インストール

```bash
composer require wppack/dsn
```

## 基本的な使用方法

```php
use WPPack\Component\Dsn\Dsn;

$dsn = Dsn::fromString('mysql://user:pass@host:3306/dbname?charset=utf8mb4');

$dsn->getScheme();          // 'mysql'
$dsn->getHost();            // 'host'
$dsn->getUser();            // 'user'
$dsn->getPassword();        // 'pass'
$dsn->getPort();            // 3306
$dsn->getPath();            // '/dbname'
$dsn->getOption('charset'); // 'utf8mb4'
```

パース失敗時は `WPPack\Component\Dsn\Exception\InvalidDsnException` (PHP 標準の `InvalidArgumentException` 継承) を throw します。

## グラマー

```
DSN       = scheme ":" ( authority-form | query-form | path-form )

scheme    = 1*( ALPHA / DIGIT / "+" / "-" / "." )

authority-form = "//" [ userinfo "@" ] host [ ":" port ] [ path ] [ "?" query ]
query-form     = "?" query
path-form      = "///" path [ "?" query ]

userinfo  = user [ ":" password ]       ; URL エンコード可
host      = IP-literal / reg-name       ; IPv6 は `[::1]` 形式
port      = 1*DIGIT
path      = "/" segment *( "/" segment )
query     = pair *( "&" pair )
pair      = key [ "=" value ] / array-pair
array-pair = key "[" [ index ] "]" [ "=" value ]
```

## 対応フォーマット

| 用途 | 例 |
|---|---|
| Database (MySQL) | `mysql://user:pass@host:3306/dbname` |
| Database (SQLite) | `sqlite:///path/to/database.db` |
| Database (PostgreSQL) | `pgsql://user:pass@host:5432/dbname?search_path=tenant` |
| Cache (Redis) | `redis://127.0.0.1:6379?dbindex=2` |
| Cache (Redis Cluster) | `redis:?host[]=node1:6379&host[]=node2:6379&redis_cluster=1` |
| Cache (APCu) | `apcu://` |
| Mailer (SES) | `ses+https://default?region=us-east-1` |
| Storage (S3) | `s3://access:secret@bucket?region=ap-northeast-1` |

## API リファレンス

### `Dsn::fromString(string $dsn): self`

DSN 文字列をパースして `Dsn` インスタンスを返します。無効な入力で `InvalidDsnException` を throw。

### `getScheme(): string`

スキーム (`mysql`、`redis`、`s3` など)。常に非 null、パース成功時は空文字列にならないことが保証されます。

### `getHost(): ?string`

ホスト名。`scheme:?query` 形式や `scheme:///path` 形式では `null`。

### `getUser(): ?string`

ユーザー名 (URL デコード済み)。未指定時は `null`。

### `getPassword(): ?string`

パスワード (URL デコード済み)。`#[\SensitiveParameter]` が適用されているため例外スタックトレースには露出しません。

### `getPort(): ?int`

ポート番号。数値でない場合や省略時は `null`。

### `getPath(): ?string`

パス (先頭 `/` を含む)。Unix ソケット / SQLite DB ファイルパスを取得する用途。

### `getOption(string $key, ?string $default = null): ?string`

クエリパラメータの値 (単一値)。配列として指定されたキーには `$default` を返します。

### `getArrayOption(string $key): list<string>`

配列として指定されたクエリパラメータの全要素。`key[]=a&key[]=b` / `key[a]&key[b]` の両方に対応。

### `getOptions(): array<string, string|list<string>>`

全クエリパラメータの連想配列。

## 他コンポーネントとの連携

### Database

```php
use WPPack\Component\Database\Driver\Driver;

$driver = Driver::fromDsn('pgsql://user:pass@host:5432/dbname?search_path=tenant');
```

`Driver::fromDsn()` は内部で `Dsn::fromString` を呼び、スキームに応じた `PostgreSQLDriver` / `MySQLDriver` / `SqliteDriver` 等をディスパッチします。

### Cache

```php
use WPPack\Component\Cache\Adapter\Adapter;

$adapter = Adapter::fromDsn('redis://127.0.0.1:6379');
```

### Storage

```php
use WPPack\Component\Storage\Adapter\Storage;

$adapter = Storage::fromDsn('s3://bucket?region=us-east-1');
```

### Mailer

```php
use WPPack\Component\Mailer\Transport\Transport;

$transport = Transport::fromDsn('ses+https://default?region=us-east-1');
```

## Sensitive parameter

`$password` は `#[\SensitiveParameter]` で保護されています。例外スタックトレースには値が出力されません。ログや表示のために DSN 全体を使う場合は、呼び出し側でパスワードをマスクしてください:

```php
$masked = $dsn->getScheme() . '://';
$user = $dsn->getUser();
if ($user !== null) {
    $masked .= $user;
    if ($dsn->getPassword() !== null) {
        $masked .= ':***';
    }
    $masked .= '@';
}
$masked .= $dsn->getHost();
```

## エラーケース

`InvalidDsnException` が throw される条件:

- コロン (`:`) が含まれない — `DATABASE_DSN=foo` のような誤設定
- スキームが空文字列 — `://`、`:?query` など
- `scheme:` の後ろが `//`、`///`、`?` のいずれでも始まらない — `mysql:garbage` など

パース成功しても意味的に誤っている DSN (ホストがない、ポートがないなど) は Dsn 層では拒否しません。**意味的な検証は各コンポーネント側** (Transport / Driver / Adapter など) で行います。
