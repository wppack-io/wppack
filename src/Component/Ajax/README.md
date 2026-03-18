# WpPack Ajax

A component for WordPress AJAX handling. Provides handler definition via `#[Ajax]` attribute, three types of access control via the `Access` enum, type-safe responses with `JsonResponse`, and Named Hook attributes.

## Installation

```bash
composer require wppack/ajax
```

## Usage

### Ajax Attribute

```php
use WpPack\Component\Ajax\Access;
use WpPack\Component\Ajax\Attribute\Ajax;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\Request;

class ProductController
{
    #[Ajax(action: 'search_products')]
    public function search(Request $request): JsonResponse
    {
        $results = get_posts(['s' => $request->request->get('query'), 'post_type' => 'product']);

        return new JsonResponse($results);
    }

    #[Ajax(action: 'delete_product', access: Access::Authenticated, capability: 'delete_posts', checkReferer: 'delete_product_nonce')]
    public function delete(Request $request): JsonResponse
    {
        wp_delete_post($request->request->getInt('product_id'));

        return new JsonResponse();
    }
}
```

### Access Enum

```php
use WpPack\Component\Ajax\Access;

#[Ajax(action: 'public_action')]                          // Public (default)
#[Ajax(action: 'auth_action', access: Access::Authenticated)]  // Authenticated users only
#[Ajax(action: 'guest_action', access: Access::Guest)]         // Unauthenticated users only
```

### Request Injection

Handler methods can receive a `Request` object by type-hinting the parameter:

```php
use WpPack\Component\Ajax\Attribute\Ajax;
use WpPack\Component\HttpFoundation\Request;

#[Ajax(action: 'search_products')]
public function search(Request $request): JsonResponse
{
    $query = $request->request->get('query', '');
    // ...
}
```

If an `AjaxHandlerRegistry` is constructed with a `Request` instance, that instance is injected. Otherwise, `Request::createFromGlobals()` is used automatically.

### CurrentUser Injection

Handler methods can receive the current WordPress user by adding the `#[CurrentUser]` attribute to a `\WP_User` parameter:

```php
use WpPack\Component\Ajax\Attribute\Ajax;
use WpPack\Component\Security\Attribute\CurrentUser;

#[Ajax(action: 'get_profile', access: Access::Authenticated)]
public function getProfile(#[CurrentUser] \WP_User $user): JsonResponse
{
    return new JsonResponse(['name' => $user->display_name]);
}
```

Both `Request` and `#[CurrentUser]` can be used together in any order:

```php
#[Ajax(action: 'update_profile', access: Access::Authenticated)]
public function updateProfile(Request $request, #[CurrentUser] \WP_User $user): JsonResponse
{
    // ...
}
```

### AbstractAjaxController

Extend `AbstractAjaxController` to access Security methods and the `json()` response helper:

```php
use WpPack\Component\Ajax\AbstractAjaxController;
use WpPack\Component\Ajax\Attribute\Ajax;
use WpPack\Component\Ajax\Access;
use WpPack\Component\HttpFoundation\JsonResponse;

class ProductController extends AbstractAjaxController
{
    #[Ajax(action: 'delete_product', access: Access::Authenticated)]
    public function delete(): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete_posts');

        wp_delete_post((int) $_POST['product_id']);

        return $this->json(['deleted' => true]);
    }
}
```

Available methods:

- `getUser(): ?\WP_User` — Get the current authenticated user
- `isGranted(string $attribute, mixed $subject = null): bool` — Check authorization
- `denyAccessUnlessGranted(string $attribute, mixed $subject = null, string $message = 'Access Denied.'): void` — Throw `AccessDeniedException` if not granted
- `json(mixed $data, int $statusCode = 200, array $headers = []): JsonResponse` — Create a JSON response

Requires `wppack/security` for `getUser()`, `isGranted()`, and `denyAccessUnlessGranted()`.

### AjaxHandlerRegistry

```php
use WpPack\Component\Ajax\AjaxHandlerRegistry;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Security;

// Without constructor injection (Request::createFromGlobals() is used)
$registry = new AjaxHandlerRegistry();
$registry->register(new ProductController());

// With constructor injection
$registry = new AjaxHandlerRegistry(Request::createFromGlobals());
$registry->register(new ProductController());

// With Security (enables AbstractAjaxController methods)
$registry = new AjaxHandlerRegistry(security: $security);
$registry->register(new ProductController());
```

### Named Hook Attributes

```php
use WpPack\Component\Hook\Attribute\Ajax\Action\WpAjaxAction;
use WpPack\Component\Hook\Attribute\Ajax\Action\WpAjaxNoprivAction;
use WpPack\Component\Hook\Attribute\Ajax\Action\CheckAjaxRefererAction;

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
