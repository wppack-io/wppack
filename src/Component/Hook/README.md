# WpPack Hook

Attribute-based WordPress hook (action/filter) management.

## Installation

```bash
composer require wppack/hook
```

## Usage

### Attribute-Based Hooks

```php
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Attribute\Action\InitAction;
use WpPack\Component\Hook\Attribute\Action\SavePostAction;

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

    #[SavePostAction]
    public function onSavePost(int $postId, \WP_Post $post, bool $update): void
    {
        // Runs on 'save_post' action
    }
}
```

### Named Hook Attributes

Frequently used hooks have dedicated attributes for type safety:

- `#[InitAction]`, `#[AdminInitAction]`, `#[SavePostAction]`
- `#[WpEnqueueScriptsAction]`, `#[PluginsLoadedAction]`
- `#[TheContentFilter]`, `#[TheTitleFilter]`, `#[BodyClassFilter]`

### Auto-Discovery

```php
use WpPack\Component\Hook\HookDiscovery;
use WpPack\Component\Hook\HookRegistry;

$registry = new HookRegistry();
$discovery = new HookDiscovery($registry);

$discovery->register(new ContentHooks());
$registry->bind();
```

### Conditional Registration

```php
use WpPack\Component\Hook\Attribute\Condition\IsAdmin;

#[Action('init')]
#[IsAdmin]
public function adminOnlyInit(): void
{
    // Runs only in admin context
}
```

## Documentation

See [docs/components/hook.md](../../docs/components/hook.md) for full documentation.

## License

MIT
