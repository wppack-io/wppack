## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Option/Subscriber/`

Option コンポーネントは WordPress のオプションフックに対応する Named Hook Attributes を提供します。

### オプション読み取りフック

#### #[PreOptionFilter]

データベースから読み取る前にオプション値をインターセプトします。

**WordPress Hook:** `pre_option_{$option}`

```php
use WPPack\Component\Hook\Attribute\Option\Filter\PreOptionFilter;

class OptionInterceptor
{
    #[PreOptionFilter('blogname', priority: 10)]
    public function filterSiteName(mixed $preValue): mixed
    {
        // 値を返すとデータベース検索をショートサーキットします
        if (defined('SITE_NAME_OVERRIDE')) {
            return SITE_NAME_OVERRIDE;
        }

        // false を返すと通常のデータベース検索を続行します
        return false;
    }
}
```

#### #[OptionFilter]

データベースから読み取った後にオプション値をフィルタリングします。

**WordPress Hook:** `option_{$option}`

```php
use WPPack\Component\Hook\Attribute\Option\Filter\OptionFilter;

class OptionProcessor
{
    #[OptionFilter('blogdescription', priority: 10)]
    public function filterDescription(mixed $value): mixed
    {
        if (empty($value)) {
            return 'Default site description';
        }

        return $value;
    }
}
```

#### #[DefaultOptionFilter]

データベースに存在しないオプションのデフォルト値を提供します。

**WordPress Hook:** `default_option_{$option}`

```php
use WPPack\Component\Hook\Attribute\Option\Filter\DefaultOptionFilter;

class OptionDefaults
{
    #[DefaultOptionFilter('my_plugin_settings', priority: 10)]
    public function provideDefaults(mixed $default, string $option, bool $passedDefault): mixed
    {
        if (!$passedDefault) {
            return [
                'version' => '1.0.0',
                'enabled' => true,
                'features' => ['basic'],
            ];
        }

        return $default;
    }
}
```

### オプション書き込みフック

#### #[PreUpdateOptionFilter]

保存前にオプション値を検証または変更します。

**WordPress Hook:** `pre_update_option_{$option}`

```php
use WPPack\Component\Hook\Attribute\Option\Filter\PreUpdateOptionFilter;

class OptionValidator
{
    #[PreUpdateOptionFilter('my_plugin_settings', priority: 10)]
    public function validateBeforeSave(mixed $value, mixed $oldValue, string $option): mixed
    {
        // 値をサニタイズ
        if (is_array($value)) {
            $value['api_key'] = sanitize_text_field($value['api_key'] ?? '');
            $value['max_retries'] = max(1, min(10, (int) ($value['max_retries'] ?? 3)));
        }

        return $value;
    }
}
```

#### #[UpdateOptionAction]

オプション更新後にアクションを実行します。

**WordPress Hook:** `update_option_{$option}`

```php
use WPPack\Component\Hook\Attribute\Option\Action\UpdateOptionAction;

class OptionChangeHandler
{
    #[UpdateOptionAction('my_plugin_settings', priority: 10)]
    public function onSettingsUpdated(mixed $oldValue, mixed $newValue, string $option): void
    {
        // キャッシュをクリア
        wp_cache_flush_group('my_plugin');

        // 変更をログに記録
        $this->logger->info('Settings updated', [
            'option' => $option,
            'changed_by' => get_current_user_id(),
        ]);
    }
}
```

#### #[AddOptionAction]

オプションが初めて作成されたときにアクションを実行します。

**WordPress Hook:** `add_option_{$option}`

```php
use WPPack\Component\Hook\Attribute\Option\Action\AddOptionAction;

class OptionCreationHandler
{
    #[AddOptionAction('my_plugin_settings', priority: 10)]
    public function onSettingsCreated(string $option, mixed $value): void
    {
        $this->logger->info('Plugin settings initialized', [
            'option' => $option,
        ]);
    }
}
```

#### #[DeleteOptionAction]

オプションが削除されたときにアクションを実行します。

**WordPress Hook:** `delete_option_{$option}`

```php
use WPPack\Component\Hook\Attribute\Option\Action\DeleteOptionAction;

class OptionDeletionHandler
{
    #[DeleteOptionAction('my_plugin_settings', priority: 10)]
    public function onSettingsDeleted(string $option): void
    {
        // 関連データをクリーンアップ
        delete_transient('my_plugin_cache');
        $this->logger->info('Plugin settings deleted');
    }
}
```

### サイトオプションフック（マルチサイト）

```php
use WPPack\Component\Hook\Attribute\Option\Filter\PreSiteOptionFilter;
use WPPack\Component\Hook\Attribute\Option\Filter\SiteOptionFilter;
use WPPack\Component\Hook\Attribute\Option\Action\UpdateSiteOptionAction;

class NetworkOptionHandler
{
    #[PreSiteOptionFilter('network_settings', priority: 10)]
    public function filterNetworkOption(mixed $preValue): mixed
    {
        // ネットワークオプション検索をショートサーキット
        return false;
    }

    #[SiteOptionFilter('network_settings', priority: 10)]
    public function processNetworkOption(mixed $value): mixed
    {
        return $value;
    }

    #[UpdateSiteOptionAction('network_settings', priority: 10)]
    public function onNetworkSettingsUpdated(mixed $oldValue, mixed $newValue): void
    {
        // ネットワーク内の全サイトに反映
    }
}
```

## クイックリファレンス

```php
// オプション読み取り
#[PreOptionFilter('option_name', priority: 10)]      // データベース読み取り前
#[OptionFilter('option_name', priority: 10)]         // データベース読み取り後
#[DefaultOptionFilter('option_name', priority: 10)]  // デフォルト値プロバイダー

// オプション書き込み
#[PreUpdateOptionFilter('option_name', priority: 10)]  // 保存前（バリデーション）
#[UpdateOptionAction('option_name', priority: 10)]     // 更新後
#[AddOptionAction('option_name', priority: 10)]        // 初回作成後
#[DeleteOptionAction('option_name', priority: 10)]     // 削除後

// マルチサイト（サイトオプション）
#[PreSiteOptionFilter('option_name', priority: 10)]    // サイトオプション読み取り前
#[SiteOptionFilter('option_name', priority: 10)]       // サイトオプション読み取り後
#[UpdateSiteOptionAction('option_name', priority: 10)] // サイトオプション更新後
```
