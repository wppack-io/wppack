# WpPack Plugin

Named hook attributes for WordPress plugin lifecycle, management, and update hooks.

## Installation

```bash
composer require wppack/plugin
```

## Usage

### Plugin Action Links

```php
use WpPack\Component\Plugin\Attribute\Filter\PluginActionLinksFilter;

final class MyPluginLinks
{
    #[PluginActionLinksFilter(plugin: 'my-plugin/my-plugin.php')]
    public function addSettingsLink(array $links): array
    {
        array_unshift($links, '<a href="' . admin_url('options-general.php?page=my-plugin') . '">Settings</a>');
        return $links;
    }
}
```

### Plugin Activation Event

```php
use WpPack\Component\Plugin\Attribute\Action\ActivatedPluginAction;

final class PluginWatcher
{
    #[ActivatedPluginAction]
    public function onPluginActivated(string $plugin, bool $networkWide): void
    {
        // React to other plugin activation
    }
}
```

## Available Attributes

### Actions

- `ActivatedPluginAction` — `activated_plugin`
- `DeactivatedPluginAction` — `deactivated_plugin`
- `UpgraderProcessCompleteAction` — `upgrader_process_complete`
- `PluginLoadedAction` — `plugin_loaded`
- `NetworkPluginsLoadedAction` — `network_plugins_loaded`
- `MuPluginsLoadedAction` — `muplugins_loaded`
- `AfterPluginRowAction` — `after_plugin_row`

### Filters

- `PluginActionLinksFilter` — `plugin_action_links_{plugin}`
- `NetworkPluginActionLinksFilter` — `network_admin_plugin_action_links_{plugin}`
- `PluginRowMetaFilter` — `plugin_row_meta`
- `PreSetSiteTransientUpdatePluginsFilter` — `pre_set_site_transient_update_plugins`
- `PluginsApiFilter` — `plugins_api`

## Documentation

See [docs/components/plugin/](../../../docs/components/plugin/) for full documentation.

## License

MIT
