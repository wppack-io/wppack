# Cache コンポーネント

**パッケージ:** `wppack/cache`
**名前空間:** `WpPack\Component\Cache\`
**レイヤー:** Abstraction

WordPress Object Cache API（`wp_cache_get()` / `wp_cache_set()`）の PSR-6 / PSR-16 準拠ラッパーです。WordPress のオブジェクトキャッシュを型安全かつモダンなインターフェースで操作できます。

また、Redis / Valkey / DynamoDB 等に対応する **Object Cache ドロップイン**（`object-cache.php`）を同梱しており、`wp-content/` にコピーするだけで永続キャッシュを有効化できます。

## インストール

```bash
composer require wppack/cache
```

## Transient コンポーネントとの違い

| | Cache | Transient |
|---|-------|----------|
| **バックエンド** | WordPress Object Cache API | WordPress Transient API |
| **WordPress API** | `wp_cache_get()` / `wp_cache_set()` | `get_transient()` / `set_transient()` |
| **PSR** | PSR-6 / PSR-16 | PSR-16 (SimpleCache) |
| **永続化** | ドロップインに依存（デフォルトはリクエスト内メモリ） | データベースに保存（ドロップインがあればキャッシュに委譲） |
| **グループ** | キャッシュグループをサポート | グループなし（プレフィックスで代替） |
| **用途** | 高頻度アクセスデータのキャッシュ | 有効期限付きの一時データ保存 |

**使い分けの指針:**
- 高頻度にアクセスするデータ → **Cache**（ドロップインで Redis 等に永続化）
- API レスポンスなど有効期限付きの一時データ → **Transient**
- ドロップインを導入済みの環境 → **Cache** を中心に使用
- ドロップインなしの環境 → **Transient**（データベースに永続化される）

## 基本コンセプト

### 従来の WordPress と WpPack の比較

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

// WpPack Cache - PSR 準拠、型安全
use WpPack\Component\Cache\CacheManager;

final class DataService
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    public function getExpensiveData(int $id): array
    {
        return $this->cache->get("expensive_data_{$id}", function () use ($id) {
            return $this->performExpensiveOperation($id);
        }, 3600);
    }
}
```

## CacheManager

`CacheManager` は WordPress Object Cache API をラップし、PSR-6 / PSR-16 準拠のインターフェースを提供します。

### 基本操作

```php
use WpPack\Component\Cache\CacheManager;

$cache = new CacheManager([
    'prefix' => 'my_app_',
    'group' => 'my_app',
]);

// データの保存
$cache->set('user_profile_123', $userData, 1800);

// データの取得
$data = $cache->get('user_profile_123');

// get + コールバック（キャッシュミス時にコールバックを実行して保存）
$result = $cache->get('complex_query', function () {
    return performComplexDatabaseQuery();
}, 1800);

// 存在確認
if ($cache->has('expensive_calculation')) {
    $result = $cache->get('expensive_calculation');
}

// 削除
$cache->delete('user_profile_123');

// 全キャッシュのクリア
$cache->flush();
```

### キャッシュグループ

WordPress Object Cache のグループ機能を活用できます。

```php
$cache = new CacheManager([
    'prefix' => 'my_app_',
    'group' => 'my_app',
]);

// グループを指定して操作
$cache->set('key', $value, 3600, group: 'api_responses');
$data = $cache->get('key', group: 'api_responses');

// グループ単位でクリア
$cache->flushGroup('api_responses');
```

## Object Cache ドロップイン

Cache コンポーネントは WordPress の `object-cache.php` ドロップインを同梱しています。`wp-content/` にコピーするだけで、Redis / Valkey / DynamoDB 等の永続キャッシュバックエンドを利用できます。

### セットアップ

```bash
# ドロップインを wp-content/ にコピー
cp vendor/wppack/cache/drop-in/object-cache.php wp-content/object-cache.php
```

### バックエンドの設定

`wp-config.php` で DSN 文字列を定義してバックエンドを切り替えます。Symfony Cache と同じ DSN 形式です。

```php
// wp-config.php

// Redis
define('WPPACK_CACHE_DSN', 'redis://localhost:6379');

// パスワード付き Redis
define('WPPACK_CACHE_DSN', 'redis://secret@localhost:6379/0');

// Valkey（Redis 互換）
define('WPPACK_CACHE_DSN', 'valkey://localhost:6379');

// Redis Sentinel
define('WPPACK_CACHE_DSN', 'redis://localhost:26379?redis_sentinel=mymaster');

// Redis Cluster
define('WPPACK_CACHE_DSN', 'redis://node1:6379,redis://node2:6379');

// DynamoDB
define('WPPACK_CACHE_DSN', 'dynamodb://ap-northeast-1/wp-cache');

// APCu
define('WPPACK_CACHE_DSN', 'apcu://');

// キープレフィックス（オプション）
define('WPPACK_CACHE_PREFIX', 'wp_');
```

### 対応バックエンドと DSN 形式

| スキーム | DSN 例 | 追加パッケージ |
|---------|--------|--------------|
| `redis://` | `redis://secret@host:6379/0` | `ext-redis` または `predis/predis` |
| `valkey://` | `valkey://host:6379` | `ext-redis` または `predis/predis` |
| `dynamodb://` | `dynamodb://region/table` | `async-aws/dynamo-db` |
| `apcu://` | `apcu://` | `ext-apcu` |

ドロップインを配置しない、または `WPPACK_CACHE_DSN` が未定義の場合、WordPress デフォルトのリクエスト内メモリキャッシュが使用されます。

### 動作確認

WP-CLI でドロップインの状態を確認できます。

```bash
# ドロップインの状態確認
wp cache type
# => WpPack Object Cache (Redis)

# 接続テスト
wp cache set test_key test_value
wp cache get test_key
# => test_value
```

## `#[Cache]` Attribute

メソッドの戻り値を自動的にキャッシュするアトリビュートです。

```php
use WpPack\Component\Cache\Attribute\Cache;

class PopularPostsService
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    #[Cache(
        duration: 3600,
        key: 'popular_posts_{limit}_{category}',
    )]
    public function getPopularPosts(int $limit = 10, ?string $category = null): array
    {
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => 'post_views',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
        ];

        if ($category) {
            $args['category_name'] = $category;
        }

        $query = new \WP_Query($args);

        return array_map(fn(\WP_Post $post) => [
            'id' => $post->ID,
            'title' => $post->post_title,
            'permalink' => get_permalink($post),
            'views' => get_post_meta($post->ID, 'post_views', true),
        ], $query->posts);
    }
}
```

## WordPress イベントとの連携

コンテンツ変更時にキャッシュを自動的に無効化する例です。

```php
use WpPack\Component\Hook\Attribute\Action;

class CacheInvalidator
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    #[Action('save_post', priority: 10)]
    public function onPostUpdate(int $postId): void
    {
        $this->cache->delete("post_data_{$postId}");
    }

    #[Action('update_user_meta', priority: 10)]
    public function onUserMetaUpdate(int $metaId, int $userId, string $metaKey): void
    {
        if (in_array($metaKey, ['preferred_theme', 'language', 'timezone'], true)) {
            $this->cache->delete("user_preferences_{$userId}");
        }
    }
}
```

## 利用シーン

**Cache コンポーネントが適しているケース:**
- WordPress Object Cache API のモダンなラッパーが必要な場合
- PSR-6 / PSR-16 準拠のインターフェースが必要な場合
- Redis / Valkey / DynamoDB で永続キャッシュを導入したい場合
- リクエスト内キャッシュとして利用する場合

**Transient コンポーネントの方が適しているケース:**
- 有効期限付きの一時データを保存したい場合
- ドロップインを導入しない環境（データベースに自動永続化される）
- `get_transient()` / `set_transient()` のラッパーが必要な場合

## 主要クラス

| クラス | 説明 |
|-------|------|
| `CacheManager` | PSR-6 / PSR-16 準拠のキャッシュマネージャー |
| `CacheInterface` | キャッシュインターフェース |
| `Attribute\Cache` | メソッドキャッシュ用アトリビュート |
| `Adapter\RedisAdapter` | Redis / Valkey アダプター |
| `Adapter\DynamoDbAdapter` | DynamoDB アダプター |
| `Adapter\ApcuAdapter` | APCu アダプター |

## 依存関係

### 必須
- なし（WordPress ネイティブの Object Cache API を使用）

### ドロップイン利用時
- Redis / Valkey: `ext-redis` または `predis/predis`
- DynamoDB: `async-aws/dynamo-db`
- APCu: `ext-apcu`

### 推奨
- **Config コンポーネント** — キャッシュ設定の管理
