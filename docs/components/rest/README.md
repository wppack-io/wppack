# Rest コンポーネント

WordPress REST API をモダン PHP でラップするコントローラーフレームワーク。Symfony スタイルのアトリビュートベースルーティング、型安全なリクエスト/レスポンス、例外ベースのエラーハンドリングを提供する。

## インストール

```bash
composer require wppack/rest
```

## 基本的な使い方

### コントローラーの定義

```php
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\Route;
use WpPack\Component\Rest\Attribute\Param;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\HttpFoundation\Exception\NotFoundException;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\JsonResponse;

#[Route('/products', namespace: 'my-plugin/v1')]
#[Permission(public: true)]
class ProductController extends AbstractRestController
{
    #[Route(methods: HttpMethod::GET)]
    public function list(
        #[Param(minimum: 1, maximum: 100)] int $perPage = 10,
        #[Param(minimum: 1)] int $page = 1,
    ): array {
        return ['data' => get_posts([
            'post_type' => 'product',
            'posts_per_page' => $perPage,
            'paged' => $page,
        ])];
    }

    #[Route('/(?P<id>\d+)', methods: HttpMethod::GET)]
    public function show(int $id): JsonResponse
    {
        $product = get_post($id);
        if (!$product) {
            throw new NotFoundException('Product not found.');
        }

        return $this->json(['id' => $product->ID, 'title' => $product->post_title]);
    }

    #[Route(methods: HttpMethod::POST)]
    #[Permission(capability: 'edit_posts')]
    public function create(
        #[Param(minLength: 3)] string $title,
        Request $request,
    ): JsonResponse {
        $id = wp_insert_post(['post_title' => $title, 'post_type' => 'product']);
        return $this->created(['id' => $id]);
    }

    #[Route('/(?P<id>\d+)', methods: HttpMethod::DELETE)]
    #[Permission(callback: 'canDelete')]
    public function delete(int $id): JsonResponse
    {
        wp_delete_post($id, true);
        return $this->noContent();
    }

    public function canDelete(\WP_REST_Request $request): bool
    {
        return current_user_can('delete_post', $request->get_param('id'));
    }
}
```

### ルート登録

```php
use WpPack\Component\Rest\RestRegistry;

$registry = new RestRegistry();
$registry->register(new ProductController());
```

## アトリビュート

### `#[Route]`

クラスレベルとメソッドレベルの両方で使用可能。

| パラメータ | 説明 | クラスレベル | メソッドレベル |
|-----------|------|:---:|:---:|
| `route` | ルートパス（第1引数） | プレフィックス | サブルート |
| `namespace` | REST API 名前空間 | 必須 | - |
| `methods` | HTTP メソッド | - | 必須 |

`IS_REPEATABLE` 対応のため、メソッドに複数の `#[Route]` を付与可能。

### `#[Param]`

パラメータレベルで使用。名前・型・required・default は PHP の引数定義から自動推論される。追加のバリデーション制約のみ指定する。

```php
#[Param(minimum: 1, maximum: 100, description: 'Items per page')]
int $perPage = 10
```

**自動推論ルール:**
- **名前**: PHP パラメータ名を snake_case に変換（`$perPage` → `per_page`）
- **型**: `int` → `'integer'`, `string` → `'string'`, `bool` → `'boolean'`, `float` → `'number'`, `array` → `'array'`
- **required**: デフォルト値なし → `true`
- **default**: PHP のデフォルト値をそのまま使用

### `#[Permission]`

クラスレベル（デフォルト）とメソッドレベル（オーバーライド）で使用。

```php
#[Permission(capability: 'edit_posts')]    // ケイパビリティチェック
#[Permission(callback: 'canDelete')]       // コントローラーメソッドをコールバック
#[Permission(public: true)]                // 公開エンドポイント
```

## レスポンス

### レスポンスフロー

```
Controller メソッドの返り値 → WP REST API への変換
├── Response / JsonResponse → WP_REST_Response（data + status + headers）
├── array                   → rest_ensure_response() で変換
├── null                    → 204 No Content
└── throw HttpException     → WP_Error に変換
```

### `AbstractRestController` ヘルパー

| メソッド | 返り値 | ステータス |
|---------|--------|-----------|
| `json($data, $status, $headers)` | `JsonResponse` | 200（デフォルト） |
| `created($data, $headers)` | `JsonResponse` | 201 |
| `noContent($headers)` | `Response` | 204 |
| `response($data, $status, $headers)` | `Response` | 200（デフォルト） |

## Request

`Request` クラスは `WP_REST_Request` のタイプセーフなラッパー。

```php
public function show(Request $request): JsonResponse
{
    $id = $request->getParam('id');
    $headers = $request->getHeaders();
    $body = $request->getBody();
    // ...
}
```

`\WP_REST_Request` を直接受け取ることも可能:

```php
public function show(\WP_REST_Request $request): JsonResponse
{
    // WordPress ネイティブ API を使用
}
```

## 例外ベースエラーハンドリング

`HttpException` を throw すると自動的に `WP_Error` に変換される。

```php
use WpPack\Component\HttpFoundation\Exception\NotFoundException;
use WpPack\Component\HttpFoundation\Exception\BadRequestException;
use WpPack\Component\HttpFoundation\Exception\ForbiddenException;

throw new NotFoundException('Product not found.');      // 404
throw new BadRequestException('Invalid input.');        // 400
throw new ForbiddenException('Access denied.');         // 403
```

### 例外一覧

| クラス | ステータス | デフォルト errorCode |
|--------|-----------|---------------------|
| `BadRequestException` | 400 | `rest_bad_request` |
| `UnauthorizedException` | 401 | `rest_unauthorized` |
| `ForbiddenException` | 403 | `rest_forbidden` |
| `NotFoundException` | 404 | `rest_not_found` |
| `MethodNotAllowedException` | 405 | `rest_method_not_allowed` |
| `ConflictException` | 409 | `rest_conflict` |
| `UnprocessableEntityException` | 422 | `rest_unprocessable_entity` |

## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Rest/Subscriber/`

REST API 関連の WordPress フックを Named Hook として提供。

### アクション

| クラス | フック名 |
|--------|---------|
| `RestApiInitAction` | `rest_api_init` |

### フィルター

| クラス | フック名 |
|--------|---------|
| `RestAuthenticationErrorsFilter` | `rest_authentication_errors` |
| `RestPreDispatchFilter` | `rest_pre_dispatch` |
| `RestPreServeRequestFilter` | `rest_pre_serve_request` |
| `RestPreparePostFilter` | `rest_prepare_post` |
| `RestRequestAfterCallbacksFilter` | `rest_request_after_callbacks` |

```php
use WpPack\Component\Rest\Attribute\Action\RestApiInitAction;
use WpPack\Component\Rest\Attribute\Filter\RestPreDispatchFilter;

class MyRestSubscriber
{
    #[RestApiInitAction]
    public function onRestApiInit(): void
    {
        // REST API 初期化時の処理
    }

    #[RestPreDispatchFilter(priority: 5)]
    public function onPreDispatch(mixed $result, \WP_REST_Server $server, \WP_REST_Request $request): mixed
    {
        // ディスパッチ前の処理
        return $result;
    }
}
```

## Routing コンポーネントとの違い

| | Routing | Rest |
|---|---------|------|
| 登録先 | `add_rewrite_rule()` | `register_rest_route()` |
| フック | `init` | `rest_api_init` |
| レスポンス | Template/Block/Redirect 等 | Response/JsonResponse |
| エラー処理 | null で WordPress に委譲 | `throw HttpException` → `WP_Error` |
| リクエスト | `get_query_var()` | `Request`（`WP_REST_Request` ラッパー） |
| パーミッション | なし | `#[Permission]` 必須 |

## プラグイン / テーマでの配置

プラグインやテーマで REST コントローラーを作成する場合、以下のディレクトリ構成を推奨します。

```
src/
└── Rest/
    └── Controller/
        ├── ProductController.php
        ├── UserController.php
        └── OrderController.php
```

> 詳細は[プラグイン開発ガイド](../../guides/plugin-development.md)、[テーマ開発ガイド](../../guides/theme-development.md)を参照してください。
