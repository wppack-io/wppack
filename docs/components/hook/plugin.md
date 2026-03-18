## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Plugin/Subscriber/`

Plugin コンポーネントは、WordPress プラグイン関連フックのための Named Hook アトリビュートを提供します。

> [!NOTE]
> プラグイン自身の初期化は `PluginInterface::boot()`、有効化/無効化は `PluginInterface::onActivate()` / `onDeactivate()` で行います。ここで紹介するフックは、他プラグインのイベントへの反応やプラグイン管理画面の拡張に使用します。

### プラグインライフサイクルフック

#### #[ActivatedPluginAction(priority?: int = 10)]

**WordPress フック:** `activated_plugin`

他のプラグインが有効化された際のイベントを購読します。自プラグインの有効化処理は `PluginInterface::onActivate()` を使用してください。

```php
use WpPack\Component\Hook\Attribute\Plugin\Action\ActivatedPluginAction;

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
use WpPack\Component\Hook\Attribute\Plugin\Action\DeactivatedPluginAction;

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
use WpPack\Component\Hook\Attribute\Plugin\Filter\PluginActionLinksFilter;

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

#### #[NetworkPluginActionLinksFilter(plugin: string, priority?: int = 10)]

**WordPress フック:** `network_admin_plugin_action_links_{plugin}`

マルチサイトのネットワーク管理画面でのプラグインアクションリンクをカスタマイズします。

```php
use WpPack\Component\Hook\Attribute\Plugin\Filter\NetworkPluginActionLinksFilter;

class NetworkPluginActionLinks
{
    #[NetworkPluginActionLinksFilter(plugin: 'wppack-plugin/wppack-plugin.php')]
    public function addNetworkActionLinks(array $links): array
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            network_admin_url('admin.php?page=wppack-network-settings'),
            __('Network Settings', 'wppack')
        );
        array_unshift($links, $settings_link);

        return $links;
    }
}
```

#### #[PluginRowMetaFilter(priority?: int = 10)]

**WordPress フック:** `plugin_row_meta`

```php
use WpPack\Component\Hook\Attribute\Plugin\Filter\PluginRowMetaFilter;

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

#### #[AfterPluginRowAction(priority?: int = 10)]

**WordPress フック:** `after_plugin_row`

プラグイン一覧テーブルの各行の後にカスタムコンテンツを出力します。

```php
use WpPack\Component\Hook\Attribute\Plugin\Action\AfterPluginRowAction;

class PluginRowInfo
{
    #[AfterPluginRowAction]
    public function addPluginRowNotice(string $plugin_file, array $plugin_data, string $status): void
    {
        if ($plugin_file !== plugin_basename(WPPACK_PLUGIN_FILE)) {
            return;
        }

        printf(
            '<tr class="plugin-update-tr"><td colspan="4"><div class="notice inline notice-info">%s</div></td></tr>',
            __('Premium version available.', 'wppack')
        );
    }
}
```

### プラグイン更新フック

#### #[UpgraderProcessCompleteAction(priority?: int = 10)]

**WordPress フック:** `upgrader_process_complete`

```php
use WpPack\Component\Hook\Attribute\Plugin\Action\UpgraderProcessCompleteAction;

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

#### #[PreSetSiteTransientUpdatePluginsFilter(priority?: int = 10)]

**WordPress フック:** `pre_set_site_transient_update_plugins`

プラグイン更新情報のトランジェントを変更し、カスタム更新サーバーからの更新を統合します。

```php
use WpPack\Component\Hook\Attribute\Plugin\Filter\PreSetSiteTransientUpdatePluginsFilter;

class CustomUpdateChecker
{
    #[PreSetSiteTransientUpdatePluginsFilter]
    public function checkForUpdates(mixed $transient): mixed
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        // カスタム更新サーバーからの更新情報を取得して統合
        $remote = $this->fetchUpdateInfo();
        if ($remote && version_compare($remote->version, $transient->checked['my-plugin/my-plugin.php'] ?? '', '>')) {
            $transient->response['my-plugin/my-plugin.php'] = $remote;
        }

        return $transient;
    }
}
```

#### #[PluginsApiFilter(priority?: int = 10)]

**WordPress フック:** `plugins_api`

プラグイン情報 API の結果をフィルタリングします。プラグイン詳細ダイアログのカスタマイズに使用します。

```php
use WpPack\Component\Hook\Attribute\Plugin\Filter\PluginsApiFilter;

class CustomPluginInfo
{
    #[PluginsApiFilter]
    public function overridePluginInfo(mixed $result, string $action, object $args): mixed
    {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== 'my-plugin') {
            return $result;
        }

        // カスタムプラグイン情報を返す
        return $this->fetchPluginInfo();
    }
}
```

### プラグイン読み込みフック

#### #[PluginLoadedAction(priority?: int = 10)]

**WordPress フック:** `plugin_loaded`

個別プラグインが読み込まれた際に実行されます。

```php
use WpPack\Component\Hook\Attribute\Plugin\Action\PluginLoadedAction;

class PluginLoadTracker
{
    #[PluginLoadedAction]
    public function onPluginLoaded(string $plugin): void
    {
        if (str_contains($plugin, 'woocommerce')) {
            // WooCommerce が読み込まれた時点で早期に統合を準備
        }
    }
}
```

#### #[NetworkPluginsLoadedAction(priority?: int = 10)]

**WordPress フック:** `network_plugins_loaded`

マルチサイトのネットワークプラグインが読み込まれた際に実行されます。

```php
use WpPack\Component\Hook\Attribute\Plugin\Action\NetworkPluginsLoadedAction;

class NetworkSetup
{
    #[NetworkPluginsLoadedAction]
    public function onNetworkPluginsLoaded(): void
    {
        // ネットワークプラグインの読み込み後に実行する処理
    }
}
```

#### #[MuPluginsLoadedAction(priority?: int = 10)]

**WordPress フック:** `muplugins_loaded`

Must-Use プラグインが読み込まれた際に実行されます。

```php
use WpPack\Component\Hook\Attribute\Plugin\Action\MuPluginsLoadedAction;

class EarlySetup
{
    #[MuPluginsLoadedAction]
    public function onMuPluginsLoaded(): void
    {
        // MU プラグイン読み込み後の早期セットアップ
    }
}
```

## クイックリファレンス

```php
// プラグイン管理
#[ActivatedPluginAction(priority?: int = 10)]        // プラグイン有効化後
#[DeactivatedPluginAction(priority?: int = 10)]      // プラグイン無効化後
#[PluginActionLinksFilter(plugin: string, priority?: int = 10)]           // プラグインアクションリンク
#[NetworkPluginActionLinksFilter(plugin: string, priority?: int = 10)]    // ネットワーク管理アクションリンク
#[PluginRowMetaFilter(priority?: int = 10)]          // プラグイン行メタリンク
#[AfterPluginRowAction(priority?: int = 10)]         // プラグイン一覧の行の後

// プラグイン更新
#[UpgraderProcessCompleteAction(priority?: int = 10)]                     // 更新完了後
#[PreSetSiteTransientUpdatePluginsFilter(priority?: int = 10)]            // 更新情報の変更
#[PluginsApiFilter(priority?: int = 10)]                                  // プラグイン API 結果のフィルタリング

// プラグイン読み込み
#[PluginLoadedAction(priority?: int = 10)]           // 個別プラグイン読み込み完了
#[NetworkPluginsLoadedAction(priority?: int = 10)]   // ネットワークプラグイン読み込み完了
#[MuPluginsLoadedAction(priority?: int = 10)]        // Must-Use プラグイン読み込み完了
```

> [!NOTE]
> `plugins_loaded` フックは Hook コンポーネントの [`PluginsLoadedAction`](../hook/README.md) を使用してください。
```
