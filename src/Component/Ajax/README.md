# WpPack Ajax

A component for WordPress AJAX handling. Provides handler definition via `#[AjaxHandler]` attribute, three types of access control via the `Access` enum, type-safe responses with `JsonResponse`, and Named Hook attributes.

## Installation

```bash
composer require wppack/ajax
```

## Usage

### AjaxHandler Attribute

```php
use WpPack\Component\Ajax\Access;
use WpPack\Component\Ajax\Attribute\AjaxHandler;
use WpPack\Component\HttpFoundation\JsonResponse;

class ProductController
{
    #[AjaxHandler(action: 'search_products')]
    public function search(): JsonResponse
    {
        $results = get_posts(['s' => $_POST['query'], 'post_type' => 'product']);

        return new JsonResponse($results);
    }

    #[AjaxHandler(action: 'delete_product', access: Access::Authenticated, capability: 'delete_posts', checkReferer: 'delete_product_nonce')]
    public function delete(): JsonResponse
    {
        wp_delete_post((int) $_POST['product_id']);

        return new JsonResponse();
    }
}
```

### Access Enum

```php
use WpPack\Component\Ajax\Access;

#[AjaxHandler(action: 'public_action')]                          // Public (default)
#[AjaxHandler(action: 'auth_action', access: Access::Authenticated)]  // Authenticated users only
#[AjaxHandler(action: 'guest_action', access: Access::Guest)]         // Unauthenticated users only
```

### AjaxHandlerRegistry

```php
use WpPack\Component\Ajax\AjaxHandlerRegistry;

$registry = new AjaxHandlerRegistry();
$registry->register(new ProductController());
```

### Named Hook Attributes

```php
use WpPack\Component\Ajax\Attribute\Action\WpAjaxAction;
use WpPack\Component\Ajax\Attribute\Action\WpAjaxNoprivAction;
use WpPack\Component\Ajax\Attribute\Action\CheckAjaxRefererAction;

final class AjaxHooks
{
    #[WpAjaxAction(action: 'my_action')]
    public function handleAjax(): void
    {
        wp_send_json_success(['message' => 'OK']);
    }

    #[WpAjaxNoprivAction(action: 'public_action')]
    public function handlePublicAjax(): void
    {
        wp_send_json_success(['message' => 'Public OK']);
    }

    #[CheckAjaxRefererAction]
    public function onRefererCheck(): void
    {
        // When check_ajax_referer is executed
    }
}
```

## Documentation

See [docs/components/ajax/](../../../docs/components/ajax/) for full documentation.

## License

MIT
