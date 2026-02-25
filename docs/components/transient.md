# Transient Component

**Package:** `wppack/transient`
**Namespace:** `WpPack\Component\Transient\`
**Layer:** Infrastructure

WordPress Transient API (`get_transient()` / `set_transient()`) の型安全なラッパーです。PSR-16 (SimpleCache) 準拠のインターフェースを提供します。

> **重要:** このコンポーネントは WordPress Transient API のみをバックエンドとして使用します。Redis・Memcached・APCu 等のカスタムバックエンドは提供しません。オブジェクトキャッシュを利用したい場合は [Cache コンポーネント](cache.md) を使用してください。

## インストール

```bash
composer require wppack/transient
```

## 基本コンセプト

### 従来の WordPress と WpPack の比較

```php
// 従来の WordPress - 型安全でない、PSR 非準拠
$data = get_transient('api_response');
if ($data === false) {
    $data = wp_remote_get('https://api.example.com/data');
    $data = wp_remote_retrieve_body($data);
    set_transient('api_response', $data, HOUR_IN_SECONDS);
}

// WpPack Transient - PSR-16 準拠、型安全
use WpPack\Component\Transient\CacheInterface;

final class ApiClient
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function getData(): array
    {
        return $this->cache->get('api_response', function () {
            $response = wp_remote_get('https://api.example.com/data');
            return json_decode(wp_remote_retrieve_body($response), true);
        }, ttl: 3600);
    }
}
```

## TransientAdapter

WordPress Transient API をバックエンドとして使用するアダプターです。

```php
use WpPack\Component\Transient\Adapter\TransientAdapter;

$cache = new TransientAdapter(
    prefix: 'myapp_',  // オプション: キーのプレフィックス
);

$cache->set('key', 'value', 3600);
$value = $cache->get('key');
$cache->delete('key');
```

## AbstractTransient クラス

構造化されたキャッシュのために型付きトランジェントクラスを定義できます。

```php
use WpPack\Component\Transient\AbstractTransient;
use WpPack\Component\Transient\Attribute\TransientConfig;

#[TransientConfig(
    name: 'api_products',
    ttl: 3600,
    prefix: 'myshop_',
)]
final class ProductCache extends AbstractTransient
{
    public function get(): ?array
    {
        return $this->read();
    }

    public function refresh(): array
    {
        $products = $this->apiClient->fetchProducts();
        $this->write($products);
        return $products;
    }
}
```

### API キャッシュの例

```php
#[TransientConfig(name: 'github_repos', ttl: 1800)]
final class GitHubRepoCache extends AbstractTransient
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public function getRepos(string $username): array
    {
        $cached = $this->read();
        if ($cached !== null) {
            return $cached;
        }

        $repos = $this->http->get("https://api.github.com/users/{$username}/repos");
        $this->write($repos);

        return $repos;
    }
}
```

### ユーザー固有のキャッシュ

```php
#[TransientConfig(name: 'user_dashboard', ttl: 900)]
final class UserDashboardCache extends AbstractTransient
{
    protected function getCacheKey(): string
    {
        return parent::getCacheKey() . '_' . get_current_user_id();
    }

    public function getDashboardData(): array
    {
        return $this->read() ?? $this->rebuild();
    }

    private function rebuild(): array
    {
        $data = [
            'recent_orders' => $this->getRecentOrders(),
            'notifications' => $this->getNotifications(),
            'stats' => $this->getUserStats(),
        ];

        $this->write($data);
        return $data;
    }
}
```

## Named Hook Attributes

Transient コンポーネントは WordPress のトランジェントフックに対応する Named Hook Attributes を提供します。

### トランジェント読み取りフック

#### #[PreTransientFilter]

データベースから読み取る前にトランジェント値をインターセプトします。

**WordPress Hook:** `pre_transient_{$transient}`

```php
use WpPack\Component\Transient\Attribute\PreTransientFilter;

class TransientInterceptor
{
    #[PreTransientFilter('external_api_data', priority: 10)]
    public function interceptApiData(mixed $preValue): mixed
    {
        // インメモリキャッシュを先にチェック
        if (isset($this->memoryCache['external_api_data'])) {
            return $this->memoryCache['external_api_data'];
        }

        // false を返すと通常のトランジェント取得を継続
        return false;
    }
}
```

#### #[TransientFilter]

データベースから読み取った後にトランジェント値をフィルタリングします。

**WordPress Hook:** `transient_{$transient}`

```php
use WpPack\Component\Transient\Attribute\TransientFilter;

class TransientProcessor
{
    #[TransientFilter('cached_products', priority: 10)]
    public function processProducts(mixed $value): mixed
    {
        if (is_array($value)) {
            // 販売終了した商品を除外
            return array_filter($value, fn($p) => $p['status'] !== 'discontinued');
        }

        return $value;
    }
}
```

### トランジェント書き込みフック

#### #[PreSetTransientFilter]

トランジェント値を保存する前にバリデーションや変更を行います。

**WordPress Hook:** `pre_set_transient_{$transient}`

```php
use WpPack\Component\Transient\Attribute\PreSetTransientFilter;

class TransientValidator
{
    #[PreSetTransientFilter('api_cache', priority: 10)]
    public function validateBeforeSave(mixed $value, int $expiration, string $transient): mixed
    {
        // 必須の構造を保証
        if (is_array($value) && !isset($value['cached_at'])) {
            $value['cached_at'] = time();
        }

        return $value;
    }
}
```

#### #[TransientTimeoutFilter]

トランジェントの有効期限を変更します。

**WordPress Hook:** `expiration_of_transient_{$transient}`

```php
use WpPack\Component\Transient\Attribute\TransientTimeoutFilter;

class TransientTimeoutManager
{
    #[TransientTimeoutFilter('heavy_computation', priority: 10)]
    public function adjustTimeout(int $expiration, mixed $value, string $transient): int
    {
        // オフピーク時はキャッシュ時間を延長
        $hour = (int) date('G');
        if ($hour >= 0 && $hour <= 6) {
            return $expiration * 2;
        }

        return $expiration;
    }
}
```

#### #[SetTransientAction]

トランジェントが保存された後にアクションを実行します。

**WordPress Hook:** `set_transient_{$transient}`

```php
use WpPack\Component\Transient\Attribute\SetTransientAction;

class TransientMonitor
{
    #[SetTransientAction('api_cache', priority: 10)]
    public function onApiCacheSet(mixed $value, int $expiration, string $transient): void
    {
        $this->logger->debug('API cache updated', [
            'transient' => $transient,
            'expiration' => $expiration,
            'size' => strlen(maybe_serialize($value)),
        ]);
    }
}
```

#### #[DeletedTransientAction]

トランジェントが削除された後にアクションを実行します。

**WordPress Hook:** `deleted_transient`

```php
use WpPack\Component\Transient\Attribute\DeletedTransientAction;

class TransientCleanupHandler
{
    #[DeletedTransientAction(priority: 10)]
    public function onTransientDeleted(string $transient): void
    {
        $this->logger->info('Transient deleted', [
            'transient' => $transient,
        ]);
    }
}
```

### サイトトランジェントフック（マルチサイト）

```php
use WpPack\Component\Transient\Attribute\PreSiteTransientFilter;
use WpPack\Component\Transient\Attribute\SiteTransientFilter;
use WpPack\Component\Transient\Attribute\SetSiteTransientAction;

class NetworkCacheHandler
{
    #[PreSiteTransientFilter('update_plugins', priority: 10)]
    public function interceptPluginUpdates(mixed $preValue): mixed
    {
        // カスタムのプラグイン更新ロジック
        return false;
    }

    #[SiteTransientFilter('update_themes', priority: 10)]
    public function filterThemeUpdates(mixed $value): mixed
    {
        // テーマ更新データをフィルタリング
        return $value;
    }

    #[SetSiteTransientAction('update_core', priority: 10)]
    public function onCoreUpdateCached(mixed $value, int $expiration): void
    {
        $this->logger->info('Core update transient refreshed');
    }
}
```

## Hook Attribute リファレンス

```php
// トランジェント読み取り
#[PreTransientFilter('name', priority: 10)]      // トランジェント読み取り前
#[TransientFilter('name', priority: 10)]          // トランジェント読み取り後

// トランジェント書き込み
#[PreSetTransientFilter('name', priority: 10)]    // トランジェント保存前
#[TransientTimeoutFilter('name', priority: 10)]   // 有効期限の変更
#[SetTransientAction('name', priority: 10)]       // トランジェント保存後
#[DeletedTransientAction(priority: 10)]           // トランジェント削除後

// サイトトランジェント（マルチサイト）
#[PreSiteTransientFilter('name', priority: 10)]   // サイトトランジェント読み取り前
#[SiteTransientFilter('name', priority: 10)]      // サイトトランジェント読み取り後
#[SetSiteTransientAction('name', priority: 10)]   // サイトトランジェント保存後
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `CacheInterface` | PSR-16 準拠のキャッシュインターフェース |
| `AbstractTransient` | 型付きトランジェント定義の基底クラス |
| `Adapter\TransientAdapter` | WordPress Transient API バックエンド |

## Cache コンポーネントとの違い

| | Transient | Cache |
|---|----------|-------|
| **バックエンド** | WordPress Transient API のみ | WordPress Object Cache API |
| **WordPress API** | `get_transient()` / `set_transient()` | `wp_cache_get()` / `wp_cache_set()` |
| **PSR** | PSR-16 (SimpleCache) | PSR-6 / PSR-16 |
| **永続化** | データベースに保存（有効期限あり） | オブジェクトキャッシュドロップインに依存 |
| **用途** | 有効期限付きの一時データ保存 | 汎用キャッシュ |
