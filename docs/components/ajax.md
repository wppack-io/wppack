# Ajax Component

**Package:** `wppack/ajax`
**Namespace:** `WpPack\Component\Ajax\`
**Layer:** Feature

WordPress の AJAX ハンドリングをモダン化するコンポーネントです。従来の手続き的なコールバックを、型安全で自動セキュリティ機能を備えたオブジェクト指向のアトリビュートベースハンドラーに置き換えます。

## インストール

```bash
composer require wppack/ajax
```

## このコンポーネントの機能

- **アトリビュートベースの AJAX ハンドラー定義** - クリーンで宣言的な構文
- **自動セキュリティ機能** - 組み込みの nonce 検証と権限チェック
- **型安全なリクエスト/レスポンスオブジェクト** - 自動バリデーション付き
- **パラメータバインディング** - リクエストデータからメソッドパラメータへの自動変換
- **エラーハンドリング** - 一貫したエラーレスポンス
- **テストユーティリティ** - 信頼性の高い AJAX ハンドラーテスト

## 基本コンセプト

### Before（従来の WordPress）

```php
add_action('wp_ajax_my_search', 'handle_search_ajax');
add_action('wp_ajax_nopriv_my_search', 'handle_search_ajax');

function handle_search_ajax() {
    if (!wp_verify_nonce($_POST['nonce'], 'my_nonce')) {
        wp_die('Security check failed');
    }

    $query = sanitize_text_field($_POST['query']);
    $results = get_posts(['s' => $query, 'post_type' => 'product']);

    $data = [];
    foreach ($results as $post) {
        $data[] = [
            'id' => $post->ID,
            'title' => $post->post_title,
        ];
    }

    wp_send_json_success($data);
}
```

### After（WpPack）

```php
use WpPack\Component\Ajax\Attribute\AjaxHandler;
use WpPack\Component\Ajax\Response\JsonResponse;

class ProductController
{
    #[AjaxHandler('search_products', priority: 10)]
    public function searchProducts(string $query): JsonResponse
    {
        $results = get_posts(['s' => $query, 'post_type' => 'product']);

        return new JsonResponse([
            'success' => true,
            'products' => array_map(fn ($post) => [
                'id' => $post->ID,
                'title' => $post->post_title,
            ], $results),
        ]);
    }
}
```

## クイックスタート

### 基本的な AJAX ハンドラー

```php
use WpPack\Component\Ajax\Attribute\AjaxHandler;
use WpPack\Component\Ajax\Response\JsonResponse;

class ProductController
{
    #[AjaxHandler('get_products', priority: 10)]
    public function getProducts(): JsonResponse
    {
        $products = get_posts(['post_type' => 'product']);

        return new JsonResponse([
            'success' => true,
            'products' => $products,
        ]);
    }

    #[AjaxHandler('update_product', requiresAuth: true, priority: 10)]
    public function updateProduct(int $productId, array $data): JsonResponse
    {
        $result = wp_update_post([
            'ID' => $productId,
            'post_title' => $data['title'],
        ]);

        return new JsonResponse(['success' => (bool) $result]);
    }
}
```

### セキュリティ機能

組み込みの nonce 検証と権限チェック：

```php
#[AjaxHandler('delete_product',
    requiresAuth: true,
    capability: 'delete_products',
    nonceAction: 'delete_product_nonce',
    priority: 10
)]
public function deleteProduct(int $productId): JsonResponse
{
    // nonce と権限は自動的に検証済み
    wp_delete_post($productId);

    return new JsonResponse(['success' => true]);
}
```

### リクエストパラメータバインディング

自動バリデーション付きの型安全なリクエスト処理：

```php
use WpPack\Component\Ajax\Request\AjaxRequest;
use WpPack\Component\Ajax\Attribute\RequestParam;

#[AjaxHandler('search_products', priority: 10)]
public function searchProducts(
    #[RequestParam(required: true)] string $query,
    #[RequestParam(default: 10)] int $limit,
    #[RequestParam(default: 'date')] string $orderBy,
    #[RequestParam(default: 'desc')] string $order
): JsonResponse {
    // パラメータは自動的に抽出、バリデーション、型変換されます
    $products = get_posts([
        's' => $query,
        'post_type' => 'product',
        'posts_per_page' => $limit,
        'orderby' => $orderBy,
        'order' => $order,
    ]);

    return new JsonResponse([
        'success' => true,
        'count' => count($products),
        'products' => array_map(fn ($p) => [
            'id' => $p->ID,
            'title' => $p->post_title,
            'price' => get_post_meta($p->ID, '_price', true),
        ], $products),
    ]);
}
```

### バッチ操作

```php
#[AjaxHandler('bulk_update_products',
    requiresAuth: true,
    capability: 'edit_products',
    priority: 10
)]
public function bulkUpdateProducts(
    array $productIds,
    array $updates
): JsonResponse {
    $updated = 0;
    $errors = [];

    foreach ($productIds as $productId) {
        try {
            foreach ($updates as $field => $value) {
                update_post_meta($productId, "_{$field}", $value);
            }
            $updated++;
        } catch (\Exception $e) {
            $errors[] = [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ];
        }
    }

    return new JsonResponse([
        'success' => true,
        'updated' => $updated,
        'errors' => $errors,
    ]);
}
```

## JavaScript 連携

```javascript
// jQuery を使用
jQuery.post(ajaxurl, {
    action: 'search_products',
    _wpnonce: wppackAjax.nonce,
    query: 'laptop',
    limit: 5,
}, function (response) {
    if (response.success) {
        console.log(response.data.products);
    }
});
```

## ハンドラーの登録

```php
add_action('init', function () {
    $container = new WpPack\Container();
    $container->register([
        ProductController::class,
    ]);
});
```

## このコンポーネントを使用すべき場面

**最適な用途：**
- リアルタイム更新を伴う動的な管理画面インターフェース
- フロントエンドの検索とフィルタリング
- ページリロードなしのフォーム送信
- ライブコンテンツの読み込みと無限スクロール
- インタラクティブなダッシュボード

**代替を検討すべき場合：**
- 完全な REST API エンドポイント（REST コンポーネントを使用）
- サーバーサイドのフォーム処理
- シンプルなページナビゲーション

## 依存関係

### 必須
- **Hook Component** - AJAX アクション登録用

### 推奨
- **Security Component** - nonce と権限管理用
- **Cache Component** - レスポンスキャッシュ用
- **Logger Component** - リクエストログ用
