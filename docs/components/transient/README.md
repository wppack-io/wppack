# Transient コンポーネント

**パッケージ:** `wppack/transient`
**名前空間:** `WpPack\Component\Transient\`
**レイヤー:** Infrastructure

WordPress Transient API（`get_transient()` / `set_transient()`）の型安全なラッパーです。シングルサイト用の `TransientManager` とマルチサイト用の `SiteTransientManager` を提供します。

> [!NOTE]
> WordPress は Object Cache ドロップイン（`object-cache.php`）が有効な場合、Transient API の呼び出しを内部的にオブジェクトキャッシュに自動委譲します。つまり Redis / Valkey 等のドロップインを導入するだけで、Transient もデータベースではなくオブジェクトキャッシュに保存されるようになります。

## インストール

```bash
composer require wppack/transient
```

## Cache コンポーネントとの違い

| | Cache | Transient |
|---|-------|----------|
| **バックエンド** | WordPress Object Cache API | WordPress Transient API |
| **WordPress API** | `wp_cache_get()` / `wp_cache_set()` | `get_transient()` / `set_transient()` |
| **永続化** | ドロップインに依存（デフォルトはリクエスト内メモリ） | デフォルトはデータベース。ドロップインがあればオブジェクトキャッシュに自動委譲 |
| **有効期限** | 任意（デフォルト 0 = 期限なし） | 任意（0 = 期限なし。常に設定推奨） |
| **グループ** | キャッシュグループをサポート | グループなし |
| **用途** | 高頻度アクセスデータのキャッシュ | 有効期限付きの一時データ保存 |

## 基本コンセプト

### Before（従来の WordPress）

```php
// 従来の WordPress - 型安全でない
$data = get_transient('api_response');
if ($data === false) {
    $response = wp_remote_get('https://api.example.com/data');
    $data = json_decode(wp_remote_retrieve_body($response), true);
    set_transient('api_response', $data, HOUR_IN_SECONDS);
}
```

### After（WpPack）

```php
use WpPack\Component\Transient\TransientManager;
use WpPack\Component\HttpClient\HttpClient;

final class ApiClient
{
    public function __construct(
        private readonly TransientManager $transient,
        private readonly HttpClient $http,
    ) {}

    public function getData(): array
    {
        $cached = $this->transient->get('api_response');

        if ($cached !== false) {
            return $cached;
        }

        $response = $this->http->get('https://api.example.com/data');
        $data = json_decode((string) $response->getBody(), true);

        $this->transient->set('api_response', $data, HOUR_IN_SECONDS);

        return $data;
    }
}
```

## TransientManager

シングルサイト用のトランジェント操作クラスです。

### メソッド一覧

| メソッド | 説明 |
|---------|------|
| `get(string $transient): mixed` | トランジェント値を取得。見つからない場合は `false` |
| `set(string $transient, mixed $value, int $expiration = 0): bool` | 値を保存 |
| `delete(string $transient): bool` | トランジェントを削除 |

### 基本操作

```php
use WpPack\Component\Transient\TransientManager;

$transient = new TransientManager();

// データの保存（1時間の有効期限）
$transient->set('api_response', $data, HOUR_IN_SECONDS);

// データの取得
$data = $transient->get('api_response');

// 削除
$transient->delete('api_response');
```

## SiteTransientManager

マルチサイト環境で、ネットワーク全体のトランジェントを操作するクラスです。

### メソッド一覧

| メソッド | 説明 |
|---------|------|
| `get(string $transient): mixed` | サイトトランジェント値を取得。見つからない場合は `false` |
| `set(string $transient, mixed $value, int $expiration = 0): bool` | 値を保存 |
| `delete(string $transient): bool` | サイトトランジェントを削除 |

### 基本操作

```php
use WpPack\Component\Transient\SiteTransientManager;

$siteTransient = new SiteTransientManager();

// ネットワーク全体のトランジェント
$siteTransient->set('network_status', $status, DAY_IN_SECONDS);
$status = $siteTransient->get('network_status');
$siteTransient->delete('network_status');
```

## Named Hook Attributes

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Transient/Subscriber/`

Transient コンポーネントは WordPress のトランジェントフックに対応する Named Hook Attributes を提供します。

### トランジェント読み取りフック

#### #[PreTransientFilter]

データベースから読み取る前にトランジェント値をインターセプトします。

**WordPress Hook:** `pre_transient_{$transient}`

```php
use WpPack\Component\Transient\Attribute\Filter\PreTransientFilter;

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
use WpPack\Component\Transient\Attribute\Filter\TransientFilter;

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
use WpPack\Component\Transient\Attribute\Filter\PreSetTransientFilter;

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
use WpPack\Component\Transient\Attribute\Filter\TransientTimeoutFilter;

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
use WpPack\Component\Transient\Attribute\Action\SetTransientAction;

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
use WpPack\Component\Transient\Attribute\Action\DeletedTransientAction;

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
use WpPack\Component\Transient\Attribute\Filter\PreSiteTransientFilter;
use WpPack\Component\Transient\Attribute\Filter\SiteTransientFilter;
use WpPack\Component\Transient\Attribute\Action\SetSiteTransientAction;

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
| `TransientManager` | WordPress Transient API のラッパー |
| `SiteTransientManager` | WordPress Site Transient API のラッパー（マルチサイト） |
| `Attribute\Filter\PreTransientFilter` | トランジェント読み取り前フィルター |
| `Attribute\Filter\TransientFilter` | トランジェント読み取り後フィルター |
| `Attribute\Filter\PreSetTransientFilter` | トランジェント保存前フィルター |
| `Attribute\Filter\TransientTimeoutFilter` | 有効期限変更フィルター |
| `Attribute\Action\SetTransientAction` | トランジェント保存後アクション |
| `Attribute\Action\DeletedTransientAction` | トランジェント削除後アクション |
| `Attribute\Filter\PreSiteTransientFilter` | サイトトランジェント読み取り前フィルター |
| `Attribute\Filter\SiteTransientFilter` | サイトトランジェント読み取り後フィルター |
| `Attribute\Action\SetSiteTransientAction` | サイトトランジェント保存後アクション |
