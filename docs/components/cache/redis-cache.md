# Redis Cache Bridge

**パッケージ:** `wppack/redis-cache`
**名前空間:** `WPPack\Component\Cache\Bridge\Redis\`
**レイヤー:** Abstraction

Cache コンポーネントの Redis / Valkey アダプタ実装。Symfony Cache の DSN 形式に準拠し、複数の Redis クライアントライブラリをサポートする高性能な永続キャッシュを提供します。

## インストール

```bash
composer require wppack/redis-cache
```

以下のいずれかの Redis クライアントが必要です:

```bash
# ext-redis（推奨、最も広く利用されている）
pecl install redis

# ext-relay（最高のパフォーマンス、インプロセスキャッシュ）
pecl install relay

# Predis（Pure PHP、拡張不要）
composer require predis/predis
```

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

## クライアントライブラリ

### 対応クライアント一覧

| アダプタ | クライアント | 拡張 / ライブラリ |
|---------|------------|----------------|
| `RedisAdapter` | `\Redis` | ext-redis |
| `RedisClusterAdapter` | `\RedisCluster` | ext-redis |
| `RelayAdapter` | `\Relay\Relay` | ext-relay |
| `RelayClusterAdapter` | `\Relay\Cluster` | ext-relay |
| `PredisAdapter` | `\Predis\Client` | predis/predis |

### クライアント自動検出

`RedisAdapterFactory` は以下の優先順位で利用可能なクライアントを自動検出します:

1. **ext-redis** — `extension_loaded('redis')` が `true` の場合
2. **Relay** — `extension_loaded('relay')` が `true` の場合
3. **Predis** — `class_exists(\Predis\Client::class)` が `true` の場合

### `class` オプションによるクライアント指定

Symfony 互換の `class` オプションでクライアントを明示的に指定できます:

```php
// wp-config.php — オプション配列で指定
define('WPPACK_CACHE_OPTIONS', ['class' => \Relay\Relay::class]);

// DSN クエリパラメータで指定
define('WPPACK_CACHE_DSN', 'redis://127.0.0.1:6379?class=Predis%5CClient');
```

指定可能なクラス名:

| `class` 値 | 使用されるアダプタ |
|------------|----------------|
| `Redis` | `RedisAdapter` |
| `RedisCluster` | `RedisClusterAdapter` |
| `Relay\Relay` | `RelayAdapter` |
| `Relay\Cluster` | `RelayClusterAdapter` |
| `Predis\Client` | `PredisAdapter` |
| `Predis\ClientInterface` | `PredisAdapter` |

### クライアントライブラリ比較

| | PhpRedis (ext-redis) | Relay (ext-relay) | Predis |
|---|---|---|---|
| **種類** | C 拡張 | C 拡張 | Pure PHP ライブラリ |
| **インストール** | `pecl install redis` | `pecl install relay` | `composer require predis/predis` |
| **PHP 拡張が必要** | はい | はい | いいえ |
| **パフォーマンス** | 高速（C 実装） | 最速（インプロセスキャッシュ） | 標準（Pure PHP） |
| **データ圧縮** | 対応 | 対応 | 非対応 |
| **インプロセスキャッシュ** | なし | あり | なし |
| **クラスタ対応** | `\RedisCluster` | `\Relay\Cluster` | `['cluster' => 'redis']` |
| **Sentinel 対応** | 手動実装 | 手動実装 | `['replication' => 'sentinel']` |
| **共有ホスティング** | 拡張不可の場合あり | 拡張不可の場合あり | 常に利用可能 |
| **ライセンス** | PHP License | Proprietary（無料利用可） | MIT |

### 選び方の指針

| シナリオ | 推奨クライアント | 理由 |
|---------|---------------|------|
| 本番環境（拡張インストール可能） | Relay | 最高のパフォーマンス、インプロセスキャッシュ |
| 本番環境（Relay 非対応） | PhpRedis | C 拡張の速度、データ圧縮 |
| 共有ホスティング | Predis | 拡張不要、Composer のみで利用可能 |
| 開発環境 | PhpRedis または Predis | 開発時はパフォーマンス差は軽微 |
| リモート Redis（AWS ElastiCache 等） | Relay > PhpRedis | ネットワーク I/O でのパフォーマンス差が顕著 |
| ローカル Redis（同一サーバー） | いずれも可 | ローカル接続ではパフォーマンス差は小さい |

## AWS での利用

### Amazon ElastiCache デプロイオプション

AWS で Redis / Valkey を利用する場合、Amazon ElastiCache の2つのデプロイオプションから選択します。

| | Serverless | ノードベース（Self-designed） |
|---|---|---|
| **概要** | 自動スケーリング、容量管理不要 | ノードタイプ・台数・配置を自分で制御 |
| **セットアップ** | 名前を指定して1分以内に作成 | ノードタイプ、レプリカ数、AZ 配置を設計 |
| **スケーリング** | 自動（垂直・水平） | 手動または Auto Scaling（スケジュール / メトリクスベース） |
| **可用性** | 常に Multi-AZ、3 AZ 冗長、99.99% SLA | Multi-AZ はオプション |
| **クラスタモード** | `cluster mode enabled` のみ | 有効 / 無効を選択可能 |
| **TLS** | **常に有効**（必須） | オプション |
| **保存時暗号化** | 常に有効 | オプション |
| **料金体系** | 従量課金（ストレージ GB-hours + 処理 ECPUs） | ノード時間課金（リザーブドノードで最大55%割引） |
| **最低コスト** | Valkey で月額 $6〜 | ノードタイプに依存 |
| **Valkey 割引** | Redis OSS 比 33% 低価格 | Redis OSS 比 20% 低価格 |
| **Global Datastore** | 非対応 | 対応（クロスリージョンレプリケーション） |
| **Data Tiering** | 非対応 | 対応（r6gd ノード + SSD） |
| **エンドポイント** | 単一エンドポイント（トポロジ変更は透過的） | ノード個別接続（トポロジ変更時に再検出が必要） |

### 選び方の指針

| シナリオ | 推奨オプション | 理由 |
|---------|-------------|------|
| 新規プロジェクト・トラフィック予測困難 | Serverless | 自動スケーリング、管理不要 |
| 開発・ステージング環境 | Serverless | 低コストで開始、使った分だけ課金 |
| トラフィックが予測可能な本番環境 | ノードベース | リザーブドノードでコスト最適化 |
| グローバル展開（複数リージョン） | ノードベース | Global Datastore が必要 |
| 大規模データ（メモリ + SSD） | ノードベース | Data Tiering が必要 |

### DSN 設定例

#### Serverless

ElastiCache Serverless は **TLS が常に有効**（`rediss://` または `valkeys://`）で、**cluster mode enabled のみ**です。

```php
// wp-config.php

// Serverless Valkey（TLS 必須、クラスタモード）
define('WPPACK_CACHE_DSN', 'valkeys://my-cache-xxxxx.serverless.apne1.cache.amazonaws.com:6379?redis_cluster=1');

// 認証トークンを使用する場合
define('WPPACK_CACHE_DSN', 'valkeys://my-auth-token@my-cache-xxxxx.serverless.apne1.cache.amazonaws.com:6379?redis_cluster=1');
```

> [!IMPORTANT]
> Serverless は cluster mode enabled のみのため、`redis_cluster=1` が必須です。クライアントは Redis Cluster プロトコルをサポートする必要があります（ext-redis の `\RedisCluster`、ext-relay の `\Relay\Cluster`、Predis の cluster オプション）。

#### ノードベース — Standalone（cluster mode disabled）

```php
// wp-config.php

// プライマリエンドポイント（TLS なし）
define('WPPACK_CACHE_DSN', 'redis://my-cluster.xxxxx.0001.apne1.cache.amazonaws.com:6379');

// TLS 有効
define('WPPACK_CACHE_DSN', 'rediss://my-cluster.xxxxx.0001.apne1.cache.amazonaws.com:6379');

// 認証トークン + TLS
define('WPPACK_CACHE_DSN', 'rediss://my-auth-token@my-cluster.xxxxx.0001.apne1.cache.amazonaws.com:6379');
```

#### ノードベース — Cluster（cluster mode enabled）

```php
// wp-config.php

// Configuration Endpoint を使用
define('WPPACK_CACHE_DSN', 'redis:?host[my-cluster.xxxxx.clustercfg.apne1.cache.amazonaws.com:6379]&redis_cluster=1');

// TLS + 認証
define('WPPACK_CACHE_DSN', 'rediss:?host[my-cluster.xxxxx.clustercfg.apne1.cache.amazonaws.com:6379]&redis_cluster=1&auth=my-auth-token');

// フェイルオーバー戦略（リードレプリカにリード分散）
define('WPPACK_CACHE_DSN', 'redis:?host[my-cluster.xxxxx.clustercfg.apne1.cache.amazonaws.com:6379]&redis_cluster=1&failover=distribute');
```

### IAM 認証

ElastiCache の IAM 認証を使用する場合は、`wppack/elasticache-auth` パッケージをインストールしてください。IAM 認証では静的パスワードの代わりに、SigV4 署名付きトークンが接続ごとに動的に生成されます。

```bash
composer require wppack/elasticache-auth
```

```php
// wp-config.php
define('WPPACK_CACHE_DSN', 'rediss://my-cluster.xxxxx.apne1.cache.amazonaws.com:6379');
define('WPPACK_CACHE_OPTIONS', [
    'iam_auth' => true,
    'iam_region' => 'ap-northeast-1',
    'iam_user_id' => 'my-iam-user',
]);
```

> [!TIP]
> IAM 認証の詳細な設定方法、AWS IAM ポリシー、トラブルシューティングについては [elasticache-auth.md](elasticache-auth.md) を参照してください。

### 推奨オプション

```php
// wp-config.php

define('WPPACK_CACHE_OPTIONS', [
    'timeout' => 5,         // 接続タイムアウト（VPC 内なので短めに）
    'read_timeout' => 3,    // 読み取りタイムアウト
    'persistent' => 1,      // 持続的接続（PHP-FPM 環境で推奨）
    'tcp_keepalive' => 60,  // TCP keepalive（NAT/ELB タイムアウト対策）
]);
```

### Valkey と Redis OSS の選択

AWS は ElastiCache で **Valkey** と **Redis OSS** の両方をサポートしています。

| | Valkey | Redis OSS |
|---|---|---|
| **ライセンス** | BSD-3-Clause（完全オープンソース） | SSPL / RSAL（Redis 7.4+） |
| **AWS 料金** | Serverless: 33% 低価格 / ノードベース: 20% 低価格 | 基準価格 |
| **互換性** | Redis 7.2 互換 | Redis 7.1+ |
| **AWS の推奨** | 新規キャッシュのデフォルト | 既存環境の互換性維持 |

新規プロジェクトでは **Valkey** を推奨します。WPPack は `valkey://` / `valkeys://` スキームで Valkey に対応しており、DSN のスキームを変更するだけで切り替え可能です。

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

### RelayAdapter

`AbstractAdapter` を継承。ext-relay の `\Relay\Relay` クラスを使用します。`RedisAdapter` と同構造で、API 互換のドロップインリプレースメントです。

```php
final class RelayAdapter extends AbstractAdapter
{
    /** @param array<string, mixed> $connectionParams */
    public function __construct(
        private readonly array $connectionParams,
    ) {}

    public function getName(): string { return 'relay'; }
}
```

### RelayClusterAdapter

`AbstractAdapter` を継承。ext-relay の `\Relay\Cluster` クラスを使用します。`RedisClusterAdapter` と同構造です。

```php
final class RelayClusterAdapter extends AbstractAdapter
{
    /** @param array<string, mixed> $connectionParams */
    public function __construct(
        private readonly array $connectionParams,
    ) {}

    public function getName(): string { return 'relay-cluster'; }
}
```

### PredisAdapter

`AbstractAdapter` を継承。`predis/predis` パッケージの `\Predis\Client` を使用します。Predis 固有の API 差異（`null` → `false` 変換、パイプラインのコールバック構文、SCAN の引数形式）を内部で吸収します。クラスタ / Sentinel は `\Predis\Client` のオプションで自動処理されるため、専用クラスタアダプタは不要です。

```php
final class PredisAdapter extends AbstractAdapter
{
    /** @param array<string, mixed> $connectionParams */
    public function __construct(
        private readonly array $connectionParams,
    ) {}

    public function getName(): string { return 'predis'; }
}
```

### RedisAdapterFactory

DSN から適切な Redis アダプタを生成するファクトリ。利用可能なクライアントライブラリを自動検出し、`class` オプションでクライアントを明示的に指定することも可能です。`redis_cluster` パラメータの有無でクラスタアダプタに分岐し、`redis_sentinel` が指定された場合は Sentinel 設定を渡します。

```php
// 自動検出（ext-redis → Relay → Predis）
$adapter = Adapter::fromDsn('redis://127.0.0.1:6379');

// クライアント指定
$adapter = Adapter::fromDsn('redis://127.0.0.1:6379', ['class' => \Relay\Relay::class]);
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
use WPPack\Component\Cache\CacheManager;

$cache = new CacheManager();
$cache->set('key', 'value', 'my_app', 3600);
$data = $cache->get('key', 'my_app');
```

### プログラマティックな使用

ドロップインを使わず、直接 `ObjectCache` を使用することもできます。

```php
use WPPack\Component\Cache\Adapter\Adapter;
use WPPack\Component\Cache\ObjectCache;

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
| `Adapter\RedisAdapter` | ext-redis Standalone / Sentinel アダプタ |
| `Adapter\RedisClusterAdapter` | ext-redis Cluster アダプタ |
| `Adapter\RelayAdapter` | Relay Standalone / Sentinel アダプタ |
| `Adapter\RelayClusterAdapter` | Relay Cluster アダプタ |
| `Adapter\PredisAdapter` | Predis アダプタ（Standalone / Cluster / Sentinel） |
| `Adapter\RedisAdapterFactory` | DSN ファクトリ（自動検出 + `class` オプション） |

## 依存関係

### 必須
- **wppack/cache** -- アダプタ基盤（`AdapterInterface`, `AbstractAdapter`, `Dsn`）

### いずれか1つが必要
- **ext-redis** -- Redis PHP 拡張（推奨）
- **ext-relay** -- Relay PHP 拡張（最高性能）
- **predis/predis** -- Pure PHP Redis クライアント（拡張不要）
