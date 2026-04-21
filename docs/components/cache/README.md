# Cache コンポーネント

**パッケージ:** `wppack/cache`
**名前空間:** `WPPack\Component\Cache\`
**Category:** Data

WordPress Object Cache API（`wp_cache_get()` / `wp_cache_set()`）のオブジェクト指向ラッパーです。WordPress のオブジェクトキャッシュを型安全かつモダンなインターフェースで操作できます。

## インストール

```bash
composer require wppack/cache
```

## Transient コンポーネントとの違い

| | Cache | Transient |
|---|-------|----------|
| **バックエンド** | WordPress Object Cache API | WordPress Transient API |
| **WordPress API** | `wp_cache_get()` / `wp_cache_set()` | `get_transient()` / `set_transient()` |
| **永続化** | ドロップインに依存（デフォルトはリクエスト内メモリ） | デフォルトはデータベース。ドロップインがあればオブジェクトキャッシュに自動委譲 |
| **有効期限** | 任意（デフォルト 0 = 期限なし） | 任意（0 = 期限なし。常に設定推奨） |
| **グループ** | キャッシュグループをサポート | グループなし |
| **用途** | 高頻度アクセスデータのキャッシュ | 有効期限付きの一時データ保存 |

**使い分けの指針:**
- 高頻度にアクセスするデータ → **Cache**（ドロップインで Redis 等に永続化）
- API レスポンスなど有効期限付きの一時データ → **Transient**
- ドロップインを導入済みの環境 → **Cache** を中心に使用
- ドロップインなしの環境 → **Transient**（データベースに永続化される）

> [!NOTE]
> WordPress は Object Cache ドロップインが有効な場合、Transient API の呼び出しを内部的にオブジェクトキャッシュに委譲します。つまり Redis 等のドロップインを導入すると、Cache コンポーネントも Transient コンポーネントも同じバックエンドを利用することになります。

## 基本コンセプト

### Before（従来の WordPress）

```php
// 従来の WordPress - 手動のキャッシュ管理
function get_expensive_data(int $id): mixed {
    $cache_key = 'expensive_data_' . $id;
    $group = 'my_app';
    $cached = wp_cache_get($cache_key, $group);

    if ($cached !== false) {
        return $cached;
    }

    $data = perform_expensive_operation($id);
    wp_cache_set($cache_key, $data, $group, 3600);

    return $data;
}
```

### After（WPPack）

```php
use WPPack\Component\Cache\CacheManager;

final class DataService
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    public function getExpensiveData(int $id): mixed
    {
        $key = "expensive_data_{$id}";
        $cached = $this->cache->get($key, 'my_app');

        if ($cached !== false) {
            return $cached;
        }

        $data = $this->performExpensiveOperation($id);
        $this->cache->set($key, $data, 'my_app', 3600);

        return $data;
    }
}
```

## CacheManager

`CacheManager` は WordPress Object Cache API をラップし、オブジェクト指向のインターフェースを提供します。

### メソッド一覧

| メソッド | 説明 |
|---------|------|
| `get(string $key, string $group = ''): mixed` | キャッシュ値を取得。見つからない場合は `false` |
| `set(string $key, mixed $data, string $group = '', int $expiration = 0): bool` | 値を保存（上書き） |
| `add(string $key, mixed $data, string $group = '', int $expiration = 0): bool` | 新規追加（既存キーがあれば失敗） |
| `replace(string $key, mixed $data, string $group = '', int $expiration = 0): bool` | 既存キーを置換（存在しなければ失敗） |
| `delete(string $key, string $group = ''): bool` | キャッシュ値を削除 |
| `flush(): bool` | 全キャッシュをクリア |
| `flushGroup(string $group): bool` | 指定グループのキャッシュをクリア（WP 6.1+） |
| `increment(string $key, int $offset = 1, string $group = ''): int\|false` | 数値をインクリメント |
| `decrement(string $key, int $offset = 1, string $group = ''): int\|false` | 数値をデクリメント |
| `supports(string $feature): bool` | 機能サポートの確認（例: `'flush_group'`） |

### 基本操作

```php
use WPPack\Component\Cache\CacheManager;

$cache = new CacheManager();

// データの保存
$cache->set('user_profile_123', $userData, 'my_app', 1800);

// データの取得
$data = $cache->get('user_profile_123', 'my_app');

// 新規追加（既存キーがあれば失敗）
$cache->add('counter', 0, 'my_app');

// 既存キーの置換（存在しなければ失敗）
$cache->replace('counter', 1, 'my_app');

// 削除
$cache->delete('user_profile_123', 'my_app');

// 全キャッシュのクリア
$cache->flush();
```

### キャッシュグループ

WordPress Object Cache のグループ機能を活用できます。

```php
$cache = new CacheManager();

// グループを指定して操作
$cache->set('key', $value, 'api_responses', 3600);
$data = $cache->get('key', 'api_responses');

// グループ単位でクリア（WP 6.1+）
if ($cache->supports('flush_group')) {
    $cache->flushGroup('api_responses');
}
```

### カウンター操作

```php
$cache = new CacheManager();

$cache->set('page_views', 0, 'stats');

// インクリメント / デクリメント
$cache->increment('page_views', 1, 'stats');
$cache->decrement('page_views', 1, 'stats');
```

## Object Cache ドロップイン

WPPack は `object-cache.php` ドロップインを提供し、WordPress のオブジェクトキャッシュバックエンドを Redis / Valkey 等に差し替えることができます。`CacheManager` はドロップインの有無にかかわらず同じインターフェースで動作します。

> [!NOTE]
> Object Cache ドロップインの WordPress 内部での仕組みについては [docs/wordpress/object-cache-dropin.md](../../wordpress/object-cache-dropin.md) を参照してください。

### セットアップ

**1. Redis Bridge のインストール**

```bash
composer require wppack/redis-cache
```

**2. wp-config.php の設定**

```php
// Redis Standalone
define('CACHE_DSN', 'redis://127.0.0.1:6379');

// プレフィックス（オプション、デフォルト 'wp:'）
define('WPPACK_CACHE_PREFIX', 'wp:');

// Maximum TTL（オプション、秒単位。TTL 0 や過大な TTL を強制クランプ）
define('WPPACK_CACHE_MAX_TTL', 86400);

// alloptions Hash 格納（オプション、デフォルト false）
define('WPPACK_CACHE_HASH_ALLOPTIONS', true);

// 圧縮（オプション、'none'（デフォルト）、'zstd'、'lz4'、'lzf'）
// phpredis / Relay の OPT_COMPRESSOR で処理（Predis は非対応）
define('WPPACK_CACHE_COMPRESSION', 'zstd');

// Async Flush（オプション、デフォルト false — DEL の代わりに UNLINK を使用）
define('WPPACK_CACHE_ASYNC_FLUSH', true);

// オプション配列（オプション、DSN パラメータを上書き/補完）
define('WPPACK_CACHE_OPTIONS', [
    'timeout' => 5,
    'read_timeout' => 3,
    'persistent' => 1,
]);
```

**3. ドロップインの配置**

```bash
cp vendor/wppack/cache/drop-in/object-cache.php wp-content/object-cache.php
```

### DSN 形式

Symfony Cache 互換の DSN 形式をサポートします。

```php
// Standalone
'redis://127.0.0.1:6379'
'redis://127.0.0.1:6379/2'                     // DB index 2
'redis://secret@127.0.0.1:6379'                // パスワード認証

// TLS
'rediss://127.0.0.1:6380'

// Valkey
'valkey://127.0.0.1:6379'

// Unix ソケット
'redis:///var/run/redis.sock'

// Cluster
'redis:?host[node1:6379]&host[node2:6379]&host[node3:6379]&redis_cluster=1'

// Sentinel
'redis:?host[sentinel1:26379]&host[sentinel2:26379]&redis_sentinel=mymaster'
```

### アダプタアーキテクチャ

> [!TIP]
> アダプタの内部設計、Bridge パッケージの構成、新しい Bridge の追加手順については [adapter-architecture.md](adapter-architecture.md) を参照してください。

WPPack の Object Cache ドロップインは Mailer コンポーネントと同じ Bridge パターンを採用しています:

- **`ObjectCache`**: WP_Object_Cache エンジン。ランタイムキャッシュ、グループ管理、シリアライズ、マルチサイト対応を担当
- **`AdapterInterface`**: 純粋な永続化レイヤー（生文字列の保存/取得のみ）
- **`Adapter::fromDsn()`**: DSN からアダプタを自動検出・生成するレジストリ

`ObjectCache` は `?AdapterInterface`（nullable）を受け取り、アダプタが `null` の場合はランタイム配列のみで動作します（グレースフルデグラデーション）。

### 対応バックエンド

| バックエンド | Bridge パッケージ | スキーム | 対応クライアント |
|------------|-----------------|---------|---------------|
| Redis / Valkey | [`wppack/redis-cache`](redis-cache.md) | `redis://`, `rediss://`, `valkey://`, `valkeys://` | ext-redis, Relay, Predis |
| DynamoDB | [`wppack/dynamodb-cache`](dynamodb-cache.md) | `dynamodb://` | async-aws/dynamo-db |
| Memcached | [`wppack/memcached-cache`](memcached-cache.md) | `memcached://` | ext-memcached |
| APCu | [`wppack/apcu-cache`](apcu-cache.md) | `apcu://` | ext-apcu |

### Maximum TTL

TTL 0（無期限）や過大な TTL で `set()` されたキーに最大 TTL を強制適用し、Redis のメモリ枯渇やデータの陳腐化を防止します。

```php
// wp-config.php
define('WPPACK_CACHE_MAX_TTL', 86400); // 24 時間
```

| 設定値 | 動作 |
|-------|------|
| `WPPACK_CACHE_MAX_TTL` 未定義 / `null` | 制限なし（デフォルト） |
| `0` | 制限なし |
| `86400` 等の正の整数 | TTL 0（無期限）および `maxTtl` を超える TTL を `maxTtl` にクランプ |

- `set()`, `setMultiple()`, `add()`, `addMultiple()`, `replace()` に適用
- 負の TTL（即時削除）はクランプされず、そのままアダプタに渡される

### 圧縮

`ObjectCache` は PHP 標準の `serialize()` / `unserialize()` でデータをシリアライズします。圧縮は phpredis / Relay の `OPT_COMPRESSOR` でアダプタレベルで処理されます。

```php
// wp-config.php
define('WPPACK_CACHE_COMPRESSION', 'zstd');
```

**対応圧縮アルゴリズム:**

| 設定値 | 必要な拡張 | 備考 |
|-------|-----------|------|
| `'none'`（デフォルト） | なし | 圧縮なし |
| `'zstd'` | ext-zstd | 高速・高圧縮率。推奨 |
| `'lz4'` | ext-lz4 | 超高速。圧縮率は控えめ |
| `'lzf'` | ext-lzf | 軽量圧縮 |

> [!NOTE]
> 圧縮は phpredis（ext-redis）および Relay のみ対応しています。Predis は `OPT_COMPRESSOR` に相当する機能がないため、圧縮設定は無視されます。

### Async Flush

Redis の `DEL` コマンドは同期的に実行され、大きな値の削除時にメインスレッドをブロックします。`UNLINK` はキーをキースペースから即座に切り離し（O(1)）、実際のメモリ解放をバックグラウンドスレッドで行います。

Async Flush を有効にすると、アダプタの `delete`, `deleteMultiple`, `flush`（プレフィックス付き SCAN+DEL パス）, `hashDelete` で使用されるコマンドが `DEL` → `UNLINK` に切り替わります。

```php
// wp-config.php
define('WPPACK_CACHE_ASYNC_FLUSH', true);
```

| 設定値 | 動作 |
|-------|------|
| `WPPACK_CACHE_ASYNC_FLUSH` 未定義 / `false` | `DEL`（デフォルト、同期削除） |
| `true` | `UNLINK`（非同期削除） |

- `flushdb()`（プレフィックスなしの全削除）は `FLUSHDB` コマンドを使用するため対象外
- `HDEL`（Hash フィールド削除）は `UNLINK` に相当するコマンドがないため対象外
- Redis 4.0+ / Valkey が必要（`UNLINK` コマンドのサポート）

### `wp_cache_supports()` 機能テーブル

| 機能 | 条件 |
|------|------|
| `add_multiple` | 常に `true` |
| `set_multiple` | 常に `true` |
| `get_multiple` | 常に `true` |
| `delete_multiple` | 常に `true` |
| `flush_runtime` | 常に `true` |
| `flush_group` | 常に `true` |
| `hash_alloptions` | `hashStrategies` が設定済み かつ アダプタが `HashableAdapterInterface` |

### alloptions Hash 格納

#### 概要

WordPress は `alloptions` キーに全自動読み込みオプションをシリアライズした単一の blob として保存します。この設計には以下の問題があります:

- **Race condition**: 複数リクエストが同時にオプションを更新すると、一方の変更が上書きされる（lost update）
- **帯域幅の浪費**: 1 つのオプションを変更するだけで、数十〜数百 KB の blob 全体を再書き込みする

alloptions Hash 格納は、この blob を Redis Hash に変換し、各オプションを個別のフィールドとして保存します。これにより、個別オプションの更新が `HSET` / `HDEL` で完結し、race condition と帯域幅の問題を解決します。

#### 有効化

`wp-config.php` に以下を追加します:

```php
define('WPPACK_CACHE_HASH_ALLOPTIONS', true);
```

有効化すると、以下の 4 つの Hash 戦略が自動的に登録されます:

| 戦略クラス | 対象キー | 対象グループ |
|-----------|---------|------------|
| `AllOptionsHashStrategy` | `alloptions` | `options` |
| `NotOptionsHashStrategy` | `notoptions` | `options` |
| `SiteOptionsHashStrategy` | `*:all` | `site-options` |
| `SiteNotOptionsHashStrategy` | `*:notoptions` | `site-options` |

#### 対応バックエンド

Hash 格納にはアダプタが `HashableAdapterInterface` を実装している必要があります。

| バックエンド | Hash 格納対応 | 備考 |
|------------|:-----------:|------|
| Redis / Valkey（ext-redis） | 対応 | Standalone / Cluster 両対応 |
| Redis / Valkey（Relay） | 対応 | Standalone / Cluster 両対応 |
| Redis / Valkey（Predis） | 対応 | |
| Memcached | 非対応 | blob フォールバック（従来動作） |
| APCu | 非対応 | blob フォールバック（従来動作） |
| DynamoDB | 非対応 | blob フォールバック（従来動作） |

非対応バックエンドでは `WPPACK_CACHE_HASH_ALLOPTIONS` を `true` に設定しても、アダプタが `HashableAdapterInterface` を実装していないため自動的に blob フォールバックになります。

#### マイグレーション注意事項

- **WRONGTYPE エラーの自動リカバリ**: Hash 格納を有効化すると、既存の blob 形式のキーに対して Hash コマンドが実行され Redis から `WRONGTYPE` エラーが返されます。`ObjectCache` はこのエラーを検知して既存キーを自動的に削除し、次回アクセス時に Hash 形式で再作成します
- **全サーバーの統一**: ロードバランサー配下の全アプリケーションサーバーで同時に設定を切り替えてください。一部のサーバーだけ有効化すると、blob 形式と Hash 形式が混在し、繰り返しリカバリが発生します

### 動作確認

```bash
# WP-CLI でキャッシュタイプを確認
wp cache type

# キャッシュのテスト
wp cache set test_key test_value
wp cache get test_key
# => test_value
```

## WordPress イベントとの連携

コンテンツ変更時にキャッシュを自動的に無効化する例です。

```php
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Cache\CacheManager;

class CacheInvalidator
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    #[Action('save_post', priority: 10)]
    public function onPostUpdate(int $postId): void
    {
        $this->cache->delete("post_data_{$postId}", 'my_app');
    }
}
```

## 利用シーン

**Cache コンポーネントが適しているケース:**
- WordPress Object Cache API のモダンなラッパーが必要な場合
- Redis / Valkey / DynamoDB で永続キャッシュを導入したい場合
- リクエスト内キャッシュとして利用する場合
- キャッシュグループによる管理が必要な場合

**Transient コンポーネントの方が適しているケース:**
- 有効期限付きの一時データを保存したい場合
- ドロップインを導入しない環境（データベースに自動永続化される）
- `get_transient()` / `set_transient()` のラッパーが必要な場合

## 主要クラス

| クラス | 説明 |
|-------|------|
| `CacheManager` | WordPress Object Cache API のラッパー |
| `ObjectCache` | WP_Object_Cache エンジン（ドロップイン用） |
| `ObjectCacheConfig` | ObjectCache 設定 VO |
| `ObjectCacheMetrics` | キャッシュヒット/ミス統計（readonly VO） |
| `Adapter\AdapterInterface` | 永続化アダプタのコントラクト |
| `Adapter\HashableAdapterInterface` | Redis Hash 操作コントラクト（`AdapterInterface` 拡張） |
| `Adapter\AbstractHashableAdapter` | Hash 操作のテンプレートメソッド基底クラス |
| `Adapter\Adapter` | DSN からアダプタを自動検出するレジストリ |
| `Adapter\Dsn` | DSN パーサー |
| `Strategy\HashStrategyInterface` | Hash 格納戦略コントラクト |
| `Strategy\AllOptionsHashStrategy` | `alloptions` を Redis Hash に格納 |
| `Strategy\NotOptionsHashStrategy` | `notoptions` を Redis Hash に格納 |
| `Strategy\SiteOptionsHashStrategy` | マルチサイト `site-options` を Redis Hash に格納 |
| `Strategy\SiteNotOptionsHashStrategy` | マルチサイト `site-notoptions` を Redis Hash に格納 |

## 依存関係

### 必須
- なし（WordPress ネイティブの Object Cache API を使用）

### ドロップイン利用時
- Redis / Valkey: `wppack/redis-cache`（ext-redis, ext-relay, または predis/predis のいずれかが必要）
- DynamoDB: `wppack/dynamodb-cache`（`async-aws/dynamo-db` が必要。[詳細](dynamodb-cache.md)）
- Memcached: `wppack/memcached-cache`（`ext-memcached` が必要。[詳細](memcached-cache.md)）
- APCu: `wppack/apcu-cache`（`ext-apcu` が必要。[詳細](apcu-cache.md)）

### オプション
- AWS ElastiCache IAM 認証: `wppack/elasticache-auth`（`async-aws/core` が必要。[詳細](elasticache-auth.md)）

## バックエンド比較

| 項目 | APCu | Redis | Memcached | DynamoDB |
|------|------|-------|-----------|----------|
| レイテンシ | 最低（共有メモリ） | 低い（ネットワーク） | 低い（ネットワーク） | 中程度（HTTP） |
| サーバー間共有 | 不可 | 可能 | 可能 | 可能 |
| 永続化 | なし | RDB/AOF | なし | テーブル |
| 外部サーバー | 不要 | 必要 | 必要 | 不要（サーバーレス） |
| スケーラビリティ | 単一サーバー | クラスター対応 | 分散可能 | 自動スケール |
| AWS マネージドサービス | — | ElastiCache, MemoryDB | ElastiCache | DynamoDB |
| コスト | 無料 | サーバーコスト | サーバーコスト | 従量課金 |
| TTL の精度 | GC 依存 | 正確 | 正確 | DynamoDB TTL |
| プレフィックス削除 | APCUIterator | SCAN | 制限あり | クエリ |
