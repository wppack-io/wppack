# RedisCache コンポーネント

**パッケージ:** `wppack/redis-cache`
**名前空間:** `WpPack\Component\Cache\Bridge\Redis\`
**レイヤー:** Abstraction

Cache コンポーネントの Redis / Valkey アダプタ実装。Symfony Cache の DSN 形式に準拠し、ext-redis を使用した高性能な永続キャッシュを提供します。

## インストール

```bash
composer require wppack/redis-cache
```

`ext-redis` PHP 拡張が必要です。

## DSN 設定

```php
// wp-config.php

// Standalone（デフォルト）
define('WPPACK_CACHE_DSN', 'redis://127.0.0.1:6379');

// DB index を指定
define('WPPACK_CACHE_DSN', 'redis://127.0.0.1:6379/2');

// パスワード認証
define('WPPACK_CACHE_DSN', 'redis://secret@127.0.0.1:6379');

// TLS 接続
define('WPPACK_CACHE_DSN', 'rediss://127.0.0.1:6380');

// Valkey
define('WPPACK_CACHE_DSN', 'valkey://127.0.0.1:6379');

// Unix ソケット
define('WPPACK_CACHE_DSN', 'redis:///var/run/redis.sock');

// ソケット + パスワード + DB index
define('WPPACK_CACHE_DSN', 'redis://secret@/var/run/redis.sock/2');

// Cluster
define('WPPACK_CACHE_DSN', 'redis:?host[node1:6379]&host[node2:6379]&host[node3:6379]&redis_cluster=1');

// Sentinel
define('WPPACK_CACHE_DSN', 'redis:?host[sentinel1:26379]&host[sentinel2:26379]&redis_sentinel=mymaster');
```

### DSN スキーム一覧

| DSN | アダプタ | 接続方式 |
|-----|---------|---------|
| `redis://` | RedisAdapter | **TCP**: Redis Standalone 接続 |
| `rediss://` | RedisAdapter | **TLS**: Redis TLS 接続（`tls://` で接続） |
| `valkey://` | RedisAdapter | **TCP**: Valkey Standalone 接続 |
| `valkeys://` | RedisAdapter | **TLS**: Valkey TLS 接続 |
| `redis:?...&redis_cluster=1` | RedisClusterAdapter | **Cluster**: Redis Cluster 接続 |
| `redis:?...&redis_sentinel=name` | RedisAdapter | **Sentinel**: Redis Sentinel 経由で接続 |

### DSN 書式

```
redis[s]://[password@][host[:port]][/db-index][?options]
valkey[s]://[password@][host[:port]][/db-index][?options]
```

- **パスワード**: URL のユーザー部（`redis://secret@host`）または `auth` クエリパラメータで指定
- **DB index**: URL パス（`redis://host/2`）または `dbindex` クエリパラメータで指定
- **ソケット**: `redis:///var/run/redis.sock`（ホスト省略、パスでソケットファイル指定）
- **マルチホスト**: `redis:?host[node1:6379]&host[node2:6379]`（Cluster / Sentinel 用）

### DSN オプション

| オプション | 型 | デフォルト | 説明 |
|-----------|-----|-----------|------|
| `auth` | string | — | パスワード（URL のユーザー部でも指定可） |
| `dbindex` | int | `0` | DB 番号（URL パス `/N` でも指定可） |
| `timeout` | int | `30` | 接続タイムアウト(秒) |
| `read_timeout` | int | `0` | 読み取りタイムアウト(秒) |
| `persistent` | int | `0` | 持続的接続（`0` or `1`） |
| `persistent_id` | string | — | 持続的接続 ID |
| `retry_interval` | int | `0` | 再接続間隔(ミリ秒) |
| `tcp_keepalive` | int | `0` | TCP keepalive(秒) |
| `redis_cluster` | bool | `false` | Cluster モード |
| `redis_sentinel` | string | — | Sentinel サービス名 |
| `failover` | string | `none` | フェイルオーバー戦略 |
| `host[]` | string[] | — | 複数ホスト（Cluster / Sentinel 用） |

### オプション配列での上書き

DSN のクエリパラメータは `WPPACK_CACHE_OPTIONS` 定数で上書き / 補完できます。

```php
// wp-config.php
define('WPPACK_CACHE_DSN', 'redis://127.0.0.1:6379');
define('WPPACK_CACHE_OPTIONS', [
    'timeout' => 5,
    'read_timeout' => 3,
    'persistent' => 1,
    'dbindex' => 2,
]);
```

`WPPACK_CACHE_OPTIONS` の値は DSN のクエリパラメータよりも優先されます。

## 接続モード

### Standalone

最もシンプルな構成。単一の Redis / Valkey サーバーに直接接続します。

```php
define('WPPACK_CACHE_DSN', 'redis://127.0.0.1:6379');
```

### TLS

`rediss://`（s が2つ）または `valkeys://` を使用すると、TLS で暗号化された接続を使用します。

```php
define('WPPACK_CACHE_DSN', 'rediss://127.0.0.1:6380');
```

### Unix ソケット

ホストを省略してパスでソケットファイルを指定します。ローカルの Redis / Valkey に対して TCP オーバーヘッドなしで接続できます。

```php
define('WPPACK_CACHE_DSN', 'redis:///var/run/redis.sock');
```

### Cluster

`redis_cluster=1` を指定すると `RedisClusterAdapter` が使用されます。複数ノードにデータを分散し、高可用性とスケーラビリティを提供します。

```php
define('WPPACK_CACHE_DSN', 'redis:?host[node1:6379]&host[node2:6379]&host[node3:6379]&redis_cluster=1');
```

#### フェイルオーバー戦略

| 値 | 説明 |
|----|------|
| `none` | フェイルオーバーなし（デフォルト） |
| `error` | エラー時にスレーブにフォールバック |
| `distribute` | リードをスレーブに分散 |
| `slaves` | リードを優先的にスレーブに分散 |

```php
define('WPPACK_CACHE_DSN', 'redis:?host[node1:6379]&host[node2:6379]&redis_cluster=1&failover=distribute');
```

### Sentinel

`redis_sentinel=<service-name>` を指定すると、Sentinel 経由でマスターを自動検出し接続します。マスターフェイルオーバー時に自動で新しいマスターに切り替わります。

```php
define('WPPACK_CACHE_DSN', 'redis:?host[sentinel1:26379]&host[sentinel2:26379]&redis_sentinel=mymaster');
```

マスターへのパスワード認証が必要な場合:

```php
define('WPPACK_CACHE_DSN', 'redis://master-pass@?host[sentinel1:26379]&host[sentinel2:26379]&redis_sentinel=mymaster');
```

## アダプタクラス

### RedisAdapter

`AbstractAdapter` を継承。ext-redis の `\Redis` クラスを使用し、Standalone / Unix ソケット / Sentinel 接続をカバーします。遅延接続（初回アクセス時に接続）、パイプラインによるバッチ最適化（`mGet`, `pipeline`）、SCAN ベースのプレフィックス削除をサポートします。

```php
final class RedisAdapter extends AbstractAdapter
{
    /** @param array<string, mixed> $connectionParams */
    public function __construct(
        private readonly array $connectionParams,
    ) {}

    public function getName(): string { return 'redis'; }
}
```

### RedisClusterAdapter

`AbstractAdapter` を継承。ext-redis の `\RedisCluster` クラスを使用し、Redis Cluster 接続をカバーします。クラスタノードを跨いだ SCAN によるプレフィックス削除、ノード単位の flush をサポートします。

```php
final class RedisClusterAdapter extends AbstractAdapter
{
    /** @param array<string, mixed> $connectionParams */
    public function __construct(
        private readonly array $connectionParams,
    ) {}

    public function getName(): string { return 'redis-cluster'; }
}
```

### RedisAdapterFactory

DSN から適切な Redis アダプタを生成するファクトリ。`redis_cluster` パラメータの有無で `RedisClusterAdapter` / `RedisAdapter` を分岐します。`redis_sentinel` が指定された場合は `RedisAdapter` に Sentinel 設定を渡します。

```php
// wppack/redis-cache がインストールされていれば Adapter::fromDsn() で自動検出
$adapter = Adapter::fromDsn('redis://127.0.0.1:6379');
```

## クイックスタート

```php
// 1. wp-config.php で DSN を設定
define('WPPACK_CACHE_DSN', 'redis://127.0.0.1:6379');
define('WPPACK_CACHE_PREFIX', 'wp:');

// 2. ドロップインを配置
// cp vendor/wppack/cache/drop-in/object-cache.php wp-content/object-cache.php

// 3. WordPress の Object Cache が自動的に Redis を使用
// CacheManager は透過的に動作
use WpPack\Component\Cache\CacheManager;

$cache = new CacheManager();
$cache->set('key', 'value', 'my_app', 3600);
$data = $cache->get('key', 'my_app');
```

### プログラマティックな使用

ドロップインを使わず、直接 `ObjectCache` を使用することもできます。

```php
use WpPack\Component\Cache\Adapter\Adapter;
use WpPack\Component\Cache\ObjectCache;

$adapter = Adapter::fromDsn('redis://127.0.0.1:6379');
$cache = new ObjectCache($adapter, 'wp:');

$cache->set('key', 'value', 'my_group', 3600);
$data = $cache->get('key', 'my_group');
```

## Docker での開発環境

`docker-compose.yml` に Valkey（Redis 互換）サービスが含まれています。

```bash
# Valkey 起動
docker compose up -d valkey --wait

# テスト実行
vendor/bin/phpunit src/Component/Cache/Bridge/Redis/tests/

# Valkey 停止
docker compose down valkey
```

## クラス一覧

| クラス | 説明 |
|-------|------|
| `Adapter\RedisAdapter` | Redis Standalone / Sentinel アダプタ（`redis://`, `rediss://`, `valkey://`, `valkeys://`） |
| `Adapter\RedisClusterAdapter` | Redis Cluster アダプタ（`redis_cluster=1`） |
| `Adapter\RedisAdapterFactory` | DSN ファクトリ |

## 依存関係

### 必須
- **wppack/cache** -- アダプタ基盤（`AdapterInterface`, `AbstractAdapter`, `Dsn`）
- **ext-redis** -- Redis PHP 拡張
