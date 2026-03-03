# WpPack Ajax

WordPress AJAX ハンドリングのコンポーネント。`#[AjaxHandler]` アトリビュートによるハンドラー定義、`Access` enum による3種類のアクセス制御、`JsonResponse` による型安全なレスポンス、Named Hook アトリビュートを提供します。

## Installation

```bash
composer require wppack/ajax
```

## Usage

### AjaxHandler アトリビュート

```php
use WpPack\Component\Ajax\Access;
use WpPack\Component\Ajax\Attribute\AjaxHandler;
use WpPack\Component\Ajax\Response\JsonResponse;

class ProductController
{
    #[AjaxHandler(action: 'search_products')]
    public function search(): JsonResponse
    {
        $results = get_posts(['s' => $_POST['query'], 'post_type' => 'product']);

        return JsonResponse::success($results);
    }

    #[AjaxHandler(action: 'delete_product', access: Access::Authenticated, capability: 'delete_posts', checkReferer: 'delete_product_nonce')]
    public function delete(): JsonResponse
    {
        wp_delete_post((int) $_POST['product_id']);

        return JsonResponse::success();
    }
}
```

### Access enum

```php
use WpPack\Component\Ajax\Access;

#[AjaxHandler(action: 'public_action')]                          // Public（デフォルト）
#[AjaxHandler(action: 'auth_action', access: Access::Authenticated)]  // ログインユーザーのみ
#[AjaxHandler(action: 'guest_action', access: Access::Guest)]         // 未ログインユーザーのみ
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
        // check_ajax_referer 実行時
    }
}
```

## Documentation

See [docs/components/ajax/](../../../docs/components/ajax/) for full documentation.

## License

MIT
