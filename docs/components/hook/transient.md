## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Transient/Subscriber/`

Transient コンポーネントは WordPress のトランジェントフックに対応する Named Hook Attributes を提供します。

### トランジェント読み取りフック

#### #[PreTransientFilter]

データベースから読み取る前にトランジェント値をインターセプトします。

**WordPress Hook:** `pre_transient_{$transient}`

```php
use WpPack\Component\Hook\Attribute\Transient\Filter\PreTransientFilter;

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
use WpPack\Component\Hook\Attribute\Transient\Filter\TransientFilter;

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
use WpPack\Component\Hook\Attribute\Transient\Filter\PreSetTransientFilter;

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
use WpPack\Component\Hook\Attribute\Transient\Filter\TransientTimeoutFilter;

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
use WpPack\Component\Hook\Attribute\Transient\Action\SetTransientAction;

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
use WpPack\Component\Hook\Attribute\Transient\Action\DeletedTransientAction;

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
use WpPack\Component\Hook\Attribute\Transient\Filter\PreSiteTransientFilter;
use WpPack\Component\Hook\Attribute\Transient\Filter\SiteTransientFilter;
use WpPack\Component\Hook\Attribute\Transient\Action\SetSiteTransientAction;

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

## クイックリファレンス

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
