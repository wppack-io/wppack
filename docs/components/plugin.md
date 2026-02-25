# Plugin コンポーネント

**パッケージ:** `wppack/plugin`
**名前空間:** `WpPack\Component\Plugin\`
**レイヤー:** Application

WordPress プラグインライフサイクルに関連するフック（`plugins_loaded`、`activated_plugin`、`deactivated_plugin`、`plugin_action_links_{plugin}` など）を Named Hook Attributes でラップするコンポーネントです。

> **Note:** プラグインのブートストラップ、サービスコンテナ、サービスプロバイダーパターンなどのフレームワーク機能は [Kernel コンポーネント](kernel.md) が提供します。

## インストール

```bash
composer require wppack/plugin
```

## Named Hook アトリビュート

Plugin コンポーネントは、WordPress プラグインライフサイクル管理のための Named Hook アトリビュートを提供します。

### プラグインライフサイクルフック

#### #[PluginsLoadedAction(priority?: int = 10)]

**WordPress フック:** `plugins_loaded`

```php
use WpPack\Component\Plugin\Attribute\PluginsLoadedAction;

class PluginInitializer
{
    #[PluginsLoadedAction]
    public function initializePlugin(): void
    {
        load_plugin_textdomain(
            'wppack-plugin',
            false,
            dirname(plugin_basename(WPPACK_PLUGIN_FILE)) . '/languages'
        );

        $this->initializeComponents();

        if (!$this->checkDependencies()) {
            add_action('admin_notices', [$this, 'showDependencyNotice']);
            return;
        }

        do_action('wppack_plugin_initialized');
    }

    #[PluginsLoadedAction(priority: 5)]
    public function earlyInitialization(): void
    {
        if (!defined('WPPACK_PLUGIN_URL')) {
            define('WPPACK_PLUGIN_URL', plugin_dir_url(WPPACK_PLUGIN_FILE));
        }
    }
}
```

#### #[ActivatedPluginAction(priority?: int = 10)]

**WordPress フック:** `activated_plugin`

```php
use WpPack\Component\Plugin\Attribute\ActivatedPluginAction;

class PluginActivation
{
    #[ActivatedPluginAction]
    public function onPluginActivated(string $plugin, bool $network_wide): void
    {
        if ($plugin === plugin_basename(WPPACK_PLUGIN_FILE)) {
            set_transient('wppack_activation_redirect', true, 30);
            wp_schedule_single_event(time() + 10, 'wppack_complete_activation');
        }
    }
}
```

#### #[DeactivatedPluginAction(priority?: int = 10)]

**WordPress フック:** `deactivated_plugin`

```php
use WpPack\Component\Plugin\Attribute\DeactivatedPluginAction;

class PluginDeactivation
{
    #[DeactivatedPluginAction]
    public function onPluginDeactivated(string $plugin, bool $network_wide): void
    {
        if ($plugin === plugin_basename(WPPACK_PLUGIN_FILE)) {
            wp_clear_scheduled_hook('wppack_daily_cleanup');
            flush_rewrite_rules();
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

        $our_plugin = plugin_basename(WPPACK_PLUGIN_FILE);

        if (isset($options['plugins']) && in_array($our_plugin, $options['plugins'])) {
            $this->runUpdateRoutine();
        }
    }

    private function runUpdateRoutine(): void
    {
        $current_version = WPPACK_VERSION;
        $previous_version = get_option('wppack_version', '0.0.0');

        if (version_compare($previous_version, '2.0.0', '<')) {
            $this->updateTo200();
        }

        update_option('wppack_version', $current_version);
        wp_cache_flush();
    }
}
```

## Hook アトリビュートリファレンス

```php
// プラグインライフサイクル
#[PluginsLoadedAction(priority?: int = 10)]          // 全プラグイン読み込み完了
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
- プラグインライフサイクルフックを Named Hook Attributes で宣言的に使いたい場合
- `plugins_loaded`、`activated_plugin`、`plugin_action_links` などのフックを型安全に扱いたい場合
- プラグイン更新処理をアトリビュートで管理したい場合

**代替を検討すべき場合：**
- プラグインのブートストラップやサービスコンテナが必要な場合 → [Kernel コンポーネント](kernel.md) を使用

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress フック登録用
