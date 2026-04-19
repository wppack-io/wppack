# WPPack Hook

[![codecov](https://img.shields.io/codecov/c/github/wppack-io/wppack?component=hook)](https://codecov.io/github/wppack-io/wppack)

Attribute-based WordPress hook (action/filter) management.

> **Note:** For new implementations, consider using the [EventDispatcher](../EventDispatcher/README.md) component instead. EventDispatcher provides a PSR-14 compliant event system backed by WordPress's `$wp_filter`, with support for custom events, `WordPressEvent` wrappers, and `#[AsEventListener]` attributes. The Hook component remains available for existing code and Named Hook attribute-based type-safe hook definitions.

## Installation

```bash
composer require wppack/hook
```

## Usage

### Attribute-Based Hooks

```php
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Hook\Attribute\Filter;
use WPPack\Component\Hook\Attribute\Action\InitAction;

final class ContentHooks
{
    #[InitAction]
    public function onInit(): void
    {
        // Runs on 'init' action
    }

    #[Filter('the_content')]
    public function appendContent(string $content): string
    {
        return $content . '<p>Appended</p>';
    }

    #[Action('save_post')]
    public function onSavePost(int $postId, \WP_Post $post, bool $update): void
    {
        // Runs on 'save_post' action
    }
}
```

### Named Hook Attributes

Frequently used hooks have dedicated attributes for type safety:

#### Lifecycle Actions

- `#[InitAction]`, `#[AdminInitAction]`, `#[PluginsLoadedAction]`
- `#[AfterSetupThemeAction]`, `#[WpLoadedAction]`

#### AJAX Actions

```php
use WPPack\Component\Hook\Attribute\Action\WpAjaxAction;
use WPPack\Component\Hook\Attribute\Action\WpAjaxNoprivAction;

final class AjaxHandler
{
    #[WpAjaxAction('load_more_posts')]
    public function handleLoadMore(): void
    {
        check_ajax_referer('load_more_nonce', 'nonce');
        // Handle authenticated AJAX request
        wp_send_json_success(['items' => []]);
    }

    #[WpAjaxNoprivAction('submit_contact')]
    public function handleContact(): void
    {
        check_ajax_referer('contact_nonce', 'nonce');
        // Handle public AJAX request
        wp_send_json_success(['message' => 'Thank you!']);
    }
}
```

#### Component-Specific Named Hooks

Each WPPack component provides its own named hook attributes. See [Named Hook Conventions](../../../docs/components/hook/named-hook-conventions.md) for details.

### Auto-Discovery

```php
use WPPack\Component\Hook\HookDiscovery;
use WPPack\Component\Hook\HookRegistry;

$registry = new HookRegistry();
$discovery = new HookDiscovery($registry);

$discovery->register(new ContentHooks());
$registry->register();
```

## Documentation

See [docs/components/hook/README.md](../../../docs/components/hook/README.md) for full documentation.

## License

MIT
