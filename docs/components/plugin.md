# Plugin コンポーネント

**パッケージ:** `wppack/plugin`
**名前空間:** `WpPack\Component\Plugin\`
**レイヤー:** Application

WordPress プラグインライフサイクルに関連するフック（`plugins_loaded`、`activated_plugin`、`deactivated_plugin`、`plugin_action_links_{plugin}` など）を Named Hook Attributes でラップするコンポーネントです。

> **Note:** プラグインのブートストラップ、サービスコンテナ、サービスプロバイダーパターンなどのフレームワーク機能は [Kernel コンポーネント](kernel/README.md) が提供します。

## インストール

```bash
composer require wppack/plugin
```

## Named Hook アトリビュート

Plugin コンポーネントは、WordPress プラグイン関連フックのための Named Hook アトリビュートを提供します。

> **Note:** プラグイン自身の初期化は `PluginInterface::boot()`、有効化/無効化は `PluginInterface::onActivate()` / `onDeactivate()` で行います。ここで紹介するフックは、他プラグインのイベントへの反応やプラグイン管理画面の拡張に使用します。

### プラグインライフサイクルフック

#### #[PluginsLoadedAction(priority?: int = 10)]

**WordPress フック:** `plugins_loaded`

```php
use WpPack\Component\Plugin\Attribute\PluginsLoadedAction;

class WooCommerceIntegration
{
    #[PluginsLoadedAction]
    public function registerIntegration(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // WooCommerce が利用可能な場合のみ統合を登録
        add_filter('woocommerce_payment_gateways', [$this, 'addGateway']);
    }
}
```

#### #[ActivatedPluginAction(priority?: int = 10)]

**WordPress フック:** `activated_plugin`

他のプラグインが有効化された際のイベントを購読します。自プラグインの有効化処理は `PluginInterface::onActivate()` を使用してください。

```php
use WpPack\Component\Plugin\Attribute\ActivatedPluginAction;

class PluginCompatibility
{
    #[ActivatedPluginAction]
    public function onOtherPluginActivated(string $plugin, bool $network_wide): void
    {
        if (str_contains($plugin, 'woocommerce')) {
            delete_transient('wppack_compatibility_cache');
        }
    }
}
```

#### #[DeactivatedPluginAction(priority?: int = 10)]

**WordPress フック:** `deactivated_plugin`

他のプラグインが無効化された際のイベントを購読します。自プラグインの無効化処理は `PluginInterface::onDeactivate()` を使用してください。

```php
use WpPack\Component\Plugin\Attribute\DeactivatedPluginAction;

class PluginCompatibility
{
    #[DeactivatedPluginAction]
    public function onOtherPluginDeactivated(string $plugin, bool $network_wide): void
    {
        if (str_contains($plugin, 'woocommerce')) {
            delete_transient('wppack_compatibility_cache');
        }
    }
}
```

### プラグイン管理フック

#### #[PluginActionLinksFilter(plugin: string, priority?: int = 10)]

**WordPress フック:** `plugin_action_links_{plugin}`

```php
use WpPack\Component\Plugin\Attribute\PluginActionLinksFilter;

class PluginActionLinks
{
    #[PluginActionLinksFilter(plugin: 'wppack-plugin/wppack-plugin.php')]
    public function addActionLinks(array $links): array
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wppack-settings'),
            __('Settings', 'wppack')
        );
        array_unshift($links, $settings_link);

        return $links;
    }
}
```

#### #[PluginRowMetaFilter(priority?: int = 10)]

**WordPress フック:** `plugin_row_meta`

```php
use WpPack\Component\Plugin\Attribute\PluginRowMetaFilter;

class PluginRowMeta
{
    #[PluginRowMetaFilter]
    public function addMetaLinks(array $links, string $plugin_file): array
    {
        if ($plugin_file !== plugin_basename(WPPACK_PLUGIN_FILE)) {
            return $links;
        }

        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://wppack.io/docs',
            __('Documentation', 'wppack')
        );

        return $links;
    }
}
```

### プラグイン更新フック

#### #[UpgraderProcessCompleteAction(priority?: int = 10)]

**WordPress フック:** `upgrader_process_complete`

```php
use WpPack\Component\Plugin\Attribute\UpgraderProcessCompleteAction;

class PluginUpdater
{
    #[UpgraderProcessCompleteAction]
    public function onUpgradeComplete(object $upgrader, array $options): void
    {
        if ($options['type'] !== 'plugin' || $options['action'] !== 'update') {
            return;
        }

        if (!isset($options['plugins']) || !in_array('my-plugin/my-plugin.php', $options['plugins'])) {
            return;
        }

        wp_cache_flush();
    }
}
```

## Hook アトリビュートリファレンス

```php
// プラグインライフサイクル
#[PluginsLoadedAction(priority?: int = 10)]          // 全プラグイン読み込み完了
#[NetworkPluginsLoadedAction(priority?: int = 10)]   // ネットワークプラグイン読み込み完了
#[MuPluginsLoadedAction(priority?: int = 10)]        // Must-Use プラグイン読み込み完了

// プラグイン管理
#[ActivatedPluginAction(priority?: int = 10)]        // プラグイン有効化後
#[DeactivatedPluginAction(priority?: int = 10)]      // プラグイン無効化後
#[PluginActionLinksFilter(plugin: string, priority?: int = 10)]      // プラグインアクションリンク
#[PluginRowMetaFilter(priority?: int = 10)]          // プラグイン行メタリンク
#[NetworkAdminPluginActionLinksFilter(plugin: string, priority?: int = 10)] // ネットワーク管理アクションリンク

// プラグイン更新
#[UpgraderProcessCompleteAction(priority?: int = 10)]        // 更新完了後
#[PreSetSiteTransientUpdatePluginsFilter(priority?: int = 10)] // 更新情報の変更
#[PluginsApiFilter(priority?: int = 10)]                     // プラグイン API 結果のフィルタリング

// プラグイン読み込み
#[PluginLoadedAction(priority?: int = 10)]           // 個別プラグイン読み込み完了
#[AfterPluginRowAction(priority?: int = 10)]          // プラグイン一覧の行の後
```

## このコンポーネントの使用場面

**最適な用途：**
- プラグイン管理画面の拡張（アクションリンク、行メタ）を宣言的に行いたい場合
- 他プラグインの有効化/無効化/更新イベントに反応したい場合
- `plugin_action_links`、`plugin_row_meta`、`upgrader_process_complete` などのフックを型安全に扱いたい場合

**代替を検討すべき場合：**
- プラグイン自身の初期化・有効化・無効化 → `PluginInterface`（[Kernel コンポーネント](kernel/README.md)）を使用

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress フック登録用
