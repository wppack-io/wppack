# アダプタアーキテクチャ

Cache コンポーネントの Object Cache ドロップインは、Mailer コンポーネントと同じ **Adapter / Bridge パターン** を採用しています。バックエンド（Redis, Memcached, DynamoDB 等）ごとに独立した Bridge パッケージを提供し、コアの `wppack/cache` パッケージは特定のバックエンドに依存しません。

## 全体構成

```
wppack/cache（コア）
├── ObjectCache          ← WP_Object_Cache エンジン
├── CacheManager         ← WordPress Object Cache API ラッパー
├── Adapter/
│   ├── AdapterInterface      ← 永続化コントラクト
│   ├── AbstractAdapter       ← テンプレートメソッド基底クラス
│   ├── AdapterFactoryInterface ← ファクトリコントラクト
│   ├── Adapter               ← ファクトリレジストリ（DSN → アダプタ）
│   └── Dsn                   ← DSN パーサー
└── Exception/

wppack/redis-cache（Bridge）
└── Adapter/
    ├── RedisAdapterFactory   ← AdapterFactoryInterface 実装
    ├── RedisAdapter          ← ext-redis Standalone
    ├── RedisClusterAdapter   ← ext-redis Cluster
    ├── RelayAdapter          ← ext-relay Standalone
    ├── RelayClusterAdapter   ← ext-relay Cluster
    └── PredisAdapter         ← Predis
```

## コアクラス

### `AdapterInterface`

永続化レイヤーのコントラクト。生文字列の保存・取得のみを担当します。シリアライズ、グループ管理、ランタイムキャッシュは `ObjectCache` が処理するため、アダプタは純粋な key-value ストアとして振る舞います。

```php
interface AdapterInterface
{
    public function getName(): string;

    // 単一操作
    public function get(string $key): string|false;
    public function set(string $key, string $value, int $ttl = 0): bool;
    public function add(string $key, string $value, int $ttl = 0): bool;
    public function delete(string $key): bool;

    // バッチ操作
    /** @return array<string, string|false> */
    public function getMultiple(array $keys): array;
    /** @return array<string, bool> */
    public function setMultiple(array $values, int $ttl = 0): array;
    /** @return array<string, bool> */
    public function deleteMultiple(array $keys): array;

    // カウンター
    public function increment(string $key, int $offset = 1): int|false;
    public function decrement(string $key, int $offset = 1): int|false;

    // フラッシュ（プレフィックス指定でグループ単位削除可）
    public function flush(string $prefix = ''): bool;

    // ライフサイクル
    public function isAvailable(): bool;
    public function close(): void;
}
```

### `AbstractAdapter`

テンプレートメソッドパターンの基底クラス。`execute()` ラッパーですべての例外を `AdapterException` に変換します。

```php
abstract class AbstractAdapter implements AdapterInterface
{
    // サブクラスはこれらを実装
    abstract protected function doGet(string $key): string|false;
    abstract protected function doSet(string $key, string $value, int $ttl = 0): bool;
    abstract protected function doAdd(string $key, string $value, int $ttl = 0): bool;
    abstract protected function doDelete(string $key): bool;
    // ... 他の do* メソッド

    // 公開メソッドは execute() 経由で例外を統一
    public function get(string $key): string|false
    {
        return $this->execute(fn() => $this->doGet($key));
    }

    protected function execute(\Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (AdapterException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new AdapterException($e->getMessage(), 0, $e);
        }
    }
}
```

### `AdapterFactoryInterface`

DSN からアダプタを生成するファクトリのコントラクト。

```php
interface AdapterFactoryInterface
{
    /** DSN をサポートするか判定 */
    public function supports(Dsn $dsn): bool;

    /** DSN からアダプタを生成 */
    public function create(Dsn $dsn, array $options = []): AdapterInterface;
}
```

### `Adapter`（レジストリ）

ファクトリのレジストリ。登録された `AdapterFactoryInterface` 実装を順に試し、DSN に対応するアダプタを生成します。

```php
final class Adapter
{
    // 登録済みファクトリクラス（Bridge パッケージのインストール有無で自動判定）
    private const FACTORY_CLASSES = [
        RedisAdapterFactory::class,
        // MemcachedAdapterFactory::class,  ← 将来の Bridge
        // DynamoDbAdapterFactory::class,   ← 将来の Bridge
    ];

    // 静的メソッド: DSN 文字列からアダプタを生成
    public static function fromDsn(string $dsn, array $options = []): AdapterInterface;

    // インスタンスメソッド: カスタムファクトリを注入可能
    public function __construct(iterable $factories) {}
    public function create(Dsn $dsn, array $options = []): AdapterInterface;
}
```

**2つの利用方法:**

```php
// 1. 静的メソッド（FACTORY_CLASSES から自動検出）
$adapter = Adapter::fromDsn('redis://127.0.0.1:6379');

// 2. コンストラクタ注入（カスタムファクトリを追加可能）
$adapter = new Adapter([
    new RedisAdapterFactory(),
    new MyCustomAdapterFactory(),
]);
$result = $adapter->fromString('custom://localhost');
```

`FACTORY_CLASSES` の各エントリは `class_exists()` でガードされており、Bridge パッケージが未インストールの場合は自動的にスキップされます。

### `Dsn`

DSN 文字列のパーサー。Redis / Valkey の特殊な DSN 形式（マルチホスト、Unix ソケット等）に対応します。

```php
$dsn = Dsn::fromString('redis://secret@127.0.0.1:6379/2?timeout=5');

$dsn->getScheme();              // 'redis'
$dsn->getHost();                // '127.0.0.1'
$dsn->getPort();                // 6379
$dsn->getUser();                // 'secret'
$dsn->getPath();                // '/2'
$dsn->getOption('timeout');     // '5'
$dsn->getArrayOption('host');   // [] (単一ホストの場合は空)

// マルチホスト DSN
$dsn = Dsn::fromString('redis:?host[node1:6379]&host[node2:6379]&redis_cluster=1');
$dsn->getHost();                // null
$dsn->getArrayOption('host');   // ['node1:6379', 'node2:6379']
$dsn->getOption('redis_cluster'); // '1'
```

## Bridge パッケージの構成

### ディレクトリ構成

```
src/Component/Cache/Bridge/{Name}/
├── composer.json
├── README.md
├── src/
│   └── Adapter/
│       ├── {Name}AdapterFactory.php    ← AdapterFactoryInterface 実装
│       ├── {Name}Adapter.php           ← AbstractAdapter 実装
│       └── ...                         ← 追加アダプタ
└── tests/
    └── Adapter/
        ├── {Name}AdapterFactoryTest.php
        └── {Name}AdapterTest.php
```

### 名前空間

```
WpPack\Component\Cache\Bridge\{Name}\Adapter\
```

### composer.json

```json
{
    "name": "wppack/{name}-cache",
    "require": {
        "php": "^8.2",
        "wppack/cache": "^1.0"
    }
}
```

## 新しい Bridge パッケージの追加手順

例として `wppack/memcached-cache` を追加する場合:

### 1. ファクトリを実装

```php
namespace WpPack\Component\Cache\Bridge\Memcached\Adapter;

use WpPack\Component\Cache\Adapter\AdapterFactoryInterface;
use WpPack\Component\Cache\Adapter\AdapterInterface;
use WpPack\Component\Cache\Adapter\Dsn;

final class MemcachedAdapterFactory implements AdapterFactoryInterface
{
    private const SUPPORTED_SCHEMES = ['memcached'];

    public function supports(Dsn $dsn): bool
    {
        return \in_array($dsn->getScheme(), self::SUPPORTED_SCHEMES, true)
            && \extension_loaded('memcached');
    }

    public function create(Dsn $dsn, array $options = []): AdapterInterface
    {
        // DSN パース → 接続パラメータ構築 → アダプタ生成
        $params = $this->buildConnectionParams($dsn, $options);

        return new MemcachedAdapter($params);
    }
}
```

### 2. アダプタを実装

```php
namespace WpPack\Component\Cache\Bridge\Memcached\Adapter;

use WpPack\Component\Cache\Adapter\AbstractAdapter;

final class MemcachedAdapter extends AbstractAdapter
{
    private ?\Memcached $client = null;

    public function __construct(
        private readonly array $connectionParams,
    ) {}

    public function getName(): string
    {
        return 'memcached';
    }

    protected function doGet(string $key): string|false
    {
        $result = $this->getConnection()->get($key);

        return $result === false ? false : (string) $result;
    }

    // ... 他の do* メソッドを実装
}
```

### 3. レジストリに登録

`Adapter::FACTORY_CLASSES` にファクトリクラスを追加:

```php
private const FACTORY_CLASSES = [
    RedisAdapterFactory::class,
    MemcachedAdapterFactory::class,  // 追加
];
```

`class_exists()` ガードにより、`wppack/memcached-cache` が未インストールの場合は自動的にスキップされます。

### 4. モノレポ設定

ルート `composer.json` に追加:

```json
{
    "autoload": {
        "psr-4": {
            "WpPack\\Component\\Cache\\Bridge\\Memcached\\": "src/Component/Cache/Bridge/Memcached/src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "WpPack\\Component\\Cache\\Bridge\\Memcached\\Tests\\": "src/Component/Cache/Bridge/Memcached/tests/"
        }
    },
    "replace": {
        "wppack/memcached-cache": "self.version"
    }
}
```

## データフロー

```
WordPress                    WpPack
─────────                    ──────
wp_cache_set('key', $data)
    │
    ▼
object-cache.php
    │
    ▼
ObjectCache::set()
    ├── シリアライズ ($data → string)
    ├── キー構築 (prefix + blogId:group:key)
    ├── ランタイムキャッシュに保存
    └── AdapterInterface::set($fullKey, $serialized, $ttl)
            │
            ▼
        RedisAdapter::doSet()
            │
            ▼
        \Redis::setex($key, $ttl, $value)
```

## Object Cache ドロップインでの利用

`object-cache.php` は `Adapter::fromDsn()` を使って DSN からアダプタを自動生成します:

```php
// object-cache.php (simplified)
function wp_cache_init(): void
{
    $adapter = null;

    if (defined('WPPACK_CACHE_DSN') && WPPACK_CACHE_DSN !== '') {
        $options = defined('WPPACK_CACHE_OPTIONS') ? WPPACK_CACHE_OPTIONS : [];
        $adapter = Adapter::fromDsn(WPPACK_CACHE_DSN, $options);

        if (!$adapter->isAvailable()) {
            $adapter = null;  // グレースフルデグラデーション
        }
    }

    $prefix = defined('WPPACK_CACHE_PREFIX') ? WPPACK_CACHE_PREFIX : 'wp:';
    $GLOBALS['wp_object_cache'] = new ObjectCache($adapter, $prefix);
}
```

`ObjectCache` は `?AdapterInterface`（nullable）を受け取り、アダプタが `null` の場合はランタイム配列のみで動作します。

## 主要クラス一覧

| クラス | パッケージ | 説明 |
|-------|-----------|------|
| `Adapter\AdapterInterface` | wppack/cache | 永続化コントラクト |
| `Adapter\AbstractAdapter` | wppack/cache | テンプレートメソッド基底（例外統一） |
| `Adapter\AdapterFactoryInterface` | wppack/cache | ファクトリコントラクト |
| `Adapter\Adapter` | wppack/cache | ファクトリレジストリ（DSN → アダプタ） |
| `Adapter\Dsn` | wppack/cache | DSN パーサー |
| `ObjectCache` | wppack/cache | WP_Object_Cache エンジン |
| `Bridge\Redis\Adapter\RedisAdapterFactory` | wppack/redis-cache | Redis ファクトリ |
| `Bridge\Redis\Adapter\RedisAdapter` | wppack/redis-cache | ext-redis Standalone |
| `Bridge\Redis\Adapter\RedisClusterAdapter` | wppack/redis-cache | ext-redis Cluster |
| `Bridge\Redis\Adapter\RelayAdapter` | wppack/redis-cache | Relay Standalone |
| `Bridge\Redis\Adapter\RelayClusterAdapter` | wppack/redis-cache | Relay Cluster |
| `Bridge\Redis\Adapter\PredisAdapter` | wppack/redis-cache | Predis |
