# WpPack DashboardWidget

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=dashboard_widget)](https://codecov.io/github/wppack-io/wppack)

A component for WordPress dashboard widgets. Provides `AbstractDashboardWidget` + `#[AsDashboardWidget]` attribute for widget definition, and Named Hook attributes.

## Installation

```bash
composer require wppack/dashboard-widget
```

## Usage

### Dashboard Widget Definition

```php
use WpPack\Component\DashboardWidget\AbstractDashboardWidget;
use WpPack\Component\DashboardWidget\Attribute\AsDashboardWidget;

#[AsDashboardWidget(
    id: 'site_stats_widget',
    label: 'Site Statistics',
    capability: 'manage_options',
    context: 'normal',
    priority: 'high',
)]
class SiteStatsWidget extends AbstractDashboardWidget
{
    public function render(): void
    {
        echo '<p>Total Posts: ' . wp_count_posts()->publish . '</p>';
    }

    public function configure(): void
    {
        // Optional configure callback
    }
}
```

### DashboardWidgetRegistry

```php
use WpPack\Component\DashboardWidget\DashboardWidgetRegistry;
use WpPack\Component\HttpFoundation\ArgumentResolver;
use WpPack\Component\HttpFoundation\RequestValueResolver;

$registry = new DashboardWidgetRegistry(
    argumentResolver: new ArgumentResolver([
        new RequestValueResolver($request),
    ]),
);
$registry->register(new SiteStatsWidget());
$registry->unregister('site_stats_widget');
```

### Named Hook Attributes

```php
use WpPack\Component\Hook\Attribute\DashboardWidget\Action\WpDashboardSetupAction;
use WpPack\Component\Hook\Attribute\DashboardWidget\Action\ActivityBoxEndAction;
use WpPack\Component\Hook\Attribute\DashboardWidget\Filter\DashboardGlanceItemsFilter;

final class DashboardHooks
{
    #[WpDashboardSetupAction]
    public function registerWidgets(): void
    {
        // Register dashboard widgets
    }

    #[ActivityBoxEndAction]
    public function addActivityContent(): void
    {
        // Add content at the end of the activity box
    }

    #[DashboardGlanceItemsFilter(priority: 5)]
    public function filterGlanceItems(array $items): array
    {
        return $items;
    }
}
```

**Action Attributes:**
- `#[WpDashboardSetupAction]` — `wp_dashboard_setup`
- `#[WpNetworkDashboardSetupAction]` — `wp_network_dashboard_setup`
- `#[ActivityBoxEndAction]` — `activity_box_end`

**Filter Attributes:**
- `#[DashboardGlanceItemsFilter]` — `dashboard_glance_items`

## Documentation

See [docs/components/dashboard-widget/](../../../docs/components/dashboard-widget/) for full documentation.

## License

MIT
