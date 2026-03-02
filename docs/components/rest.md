# REST コンポーネント

**パッケージ:** `wppack/rest`
**名前空間:** `WpPack\Component\Rest\`
**レイヤー:** Feature

REST コンポーネントは、アトリビュートベースのルート定義、自動リクエストバリデーション、レスポンス変換、高度な API 機能を備えた WordPress REST API 開発のための拡張フレームワークを提供します。

## インストール

```bash
composer require wppack/rest
```

## このコンポーネントの機能

- **アトリビュートベースのルート登録** - クリーンで宣言的な構文
- **自動リクエストバリデーション** - 型安全なパラメータ処理
- **レスポンス変換** - 一貫したデータフォーマット
- **API バージョニングサポート** - 後方互換性の維持

## 基本コンセプト

### Before（従来の WordPress）

```php
add_action('rest_api_init', function () {
    register_rest_route('my-plugin/v1', '/products/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'get_product_callback',
        'permission_callback' => 'check_product_permissions',
        'args' => [
            'id' => [
                'validate_callback' => function ($param) {
                    return is_numeric($param);
                },
                'sanitize_callback' => 'absint'
            ]
        ]
    ]);
});

function get_product_callback($request) {
    $id = $request['id'];
    $product = get_post($id);

    if (!$product || $product->post_type !== 'product') {
        return new WP_Error('not_found', 'Product not found', ['status' => 404]);
    }

    return rest_ensure_response([
        'id' => $product->ID,
        'title' => $product->post_title,
        'price' => get_post_meta($id, 'price', true)
    ]);
}
```

### After（WpPack）

```php
use WpPack\Component\Rest\AbstractRestEndpoint;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\Attribute\RestParam;
use WpPack\Component\Rest\Attribute\RestPermission;

#[RestRoute('/products/{id}', namespace: 'my-plugin/v1')]
class ProductEndpoint extends AbstractRestEndpoint
{
    #[RestRoute(methods: ['GET'])]
    #[RestPermission('read')]
    #[RestParam('id', type: 'integer', required: true)]
    public function getProduct(int $id): array
    {
        $product = Product::find($id);

        if (!$product) {
            throw new NotFoundException('Product not found');
        }

        return [
            'id' => $product->ID,
            'title' => $product->post_title,
            'price' => get_post_meta($id, 'price', true),
        ];
    }
}
```

## クイックスタート

### 完全なブログ API の構築

```php
use WpPack\Component\Rest\AbstractRestEndpoint;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\Attribute\RestParam;
use WpPack\Component\Rest\Attribute\RestPermission;

#[RestRoute('/posts', namespace: 'blog-api/v1')]
class PostsEndpoint extends AbstractRestEndpoint
{
    #[RestRoute(methods: ['GET'])]
    #[RestParam('page', type: 'integer', default: 1, min: 1)]
    #[RestParam('per_page', type: 'integer', default: 10, min: 1, max: 100)]
    #[RestParam('status', type: 'string', enum: ['publish', 'draft'], default: 'publish')]
    #[RestParam('category', type: 'string', description: 'Filter by category slug')]
    public function getPosts(
        int $page = 1,
        int $perPage = 10,
        string $status = 'publish',
        ?string $category = null
    ): array {
        $query = [
            'post_type' => 'post',
            'post_status' => $status,
            'posts_per_page' => $perPage,
            'paged' => $page,
        ];

        if ($category) {
            $query['category_name'] = $category;
        }

        $posts = get_posts($query);
        $totalPosts = wp_count_posts('post')->$status;

        return [
            'data' => array_map(fn ($post) => [
                'id' => $post->ID,
                'title' => $post->post_title,
                'status' => $post->post_status,
                'date' => $post->post_date,
            ], $posts),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalPosts,
                'total_pages' => ceil($totalPosts / $perPage),
            ]
        ];
    }

    #[RestRoute('/{id}', methods: ['GET'])]
    #[RestParam('id', type: 'integer', required: true)]
    #[RestParam('include_comments', type: 'boolean', default: false)]
    public function getPost(int $id, bool $includeComments = false): array
    {
        $post = get_post($id);

        if (!$post || $post->post_status !== 'publish') {
            throw new NotFoundException('Post not found');
        }

        $data = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => apply_filters('the_content', $post->post_content),
            'date' => $post->post_date,
        ];

        if ($includeComments) {
            $data['comments'] = get_comments(['post_id' => $id]);
        }

        return $data;
    }

    #[RestRoute(methods: ['POST'])]
    #[RestPermission('edit_posts')]
    #[RestParam('title', type: 'string', required: true, minLength: 3)]
    #[RestParam('content', type: 'string', required: true)]
    #[RestParam('status', type: 'string', enum: ['publish', 'draft'], default: 'draft')]
    #[RestParam('categories', type: 'array', items: 'integer')]
    #[RestParam('tags', type: 'array', items: 'string')]
    public function createPost(
        string $title,
        string $content,
        string $status = 'draft',
        array $categories = [],
        array $tags = []
    ): array {
        $postId = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
            'post_author' => get_current_user_id(),
        ]);

        if (is_wp_error($postId)) {
            throw new BadRequestException('Failed to create post');
        }

        if (!empty($categories)) {
            wp_set_post_categories($postId, $categories);
        }
        if (!empty($tags)) {
            wp_set_post_tags($postId, $tags);
        }

        return ['id' => $postId, 'title' => $title, 'status' => $status];
    }

    #[RestRoute('/{id}', methods: ['PUT', 'PATCH'])]
    #[RestPermission('edit_post')]
    #[RestParam('id', type: 'integer', required: true)]
    #[RestParam('title', type: 'string', minLength: 3)]
    #[RestParam('content', type: 'string')]
    #[RestParam('status', type: 'string', enum: ['publish', 'draft'])]
    public function updatePost(
        int $id,
        ?string $title = null,
        ?string $content = null,
        ?string $status = null
    ): array {
        $post = get_post($id);
        if (!$post) {
            throw new NotFoundException('Post not found');
        }
        if (!current_user_can('edit_post', $id)) {
            throw new ForbiddenException('You cannot edit this post');
        }

        $updateData = ['ID' => $id];
        if ($title !== null) $updateData['post_title'] = $title;
        if ($content !== null) $updateData['post_content'] = $content;
        if ($status !== null) $updateData['post_status'] = $status;

        wp_update_post($updateData);
        $updated = get_post($id);
        return ['id' => $updated->ID, 'title' => $updated->post_title, 'status' => $updated->post_status];
    }

    #[RestRoute('/{id}', methods: ['DELETE'])]
    #[RestPermission('delete_post')]
    #[RestParam('id', type: 'integer', required: true)]
    #[RestParam('force', type: 'boolean', default: false)]
    public function deletePost(int $id, bool $force = false): array
    {
        $post = get_post($id);
        if (!$post) {
            throw new NotFoundException('Post not found');
        }
        if (!current_user_can('delete_post', $id)) {
            throw new ForbiddenException('You cannot delete this post');
        }

        wp_delete_post($id, $force);

        return [
            'message' => $force ? 'Post permanently deleted' : 'Post moved to trash',
            'deleted' => true,
            'id' => $id,
        ];
    }
}
```

### API バージョニング

```php
#[RestRoute('/products', namespace: 'api/v1')]
class ProductEndpointV1 extends AbstractRestEndpoint
{
    public function getProducts(): array
    {
        return $this->getProductsAsArray();
    }
}

#[RestRoute('/products', namespace: 'api/v2')]
class ProductEndpointV2 extends AbstractRestEndpoint
{
    public function getProducts(): array
    {
        return $this->getProductsWithMetadata();
    }
}
```

## Named Hook アトリビュート

### REST API 登録

```php
use WpPack\Component\Rest\Attribute\RestApiInitAction;

class RestEndpointManager
{
    #[RestApiInitAction]
    public function registerEndpoints(): void
    {
        register_rest_route('wppack/v1', '/products', [
            'methods' => 'GET',
            'callback' => [$this, 'getProducts'],
            'permission_callback' => '__return_true',
            'args' => [
                'per_page' => ['type' => 'integer', 'default' => 10],
                'page' => ['type' => 'integer', 'default' => 1],
            ],
        ]);
    }
}
```

### 認証

```php
use WpPack\Component\Rest\Attribute\RestAuthenticationErrorsFilter;

class RestAuthManager
{
    #[RestAuthenticationErrorsFilter]
    public function authenticateRequest($result): ?\WP_Error
    {
        if (!empty($result)) {
            return $result;
        }

        // Check for API key
        if ($apiKey = $this->getApiKey()) {
            return $this->authenticateWithApiKey($apiKey);
        }

        // Check for JWT
        if ($token = $this->getBearerToken()) {
            return $this->authenticateWithJWT($token);
        }

        return null;
    }
}
```

### レスポンス変更

```php
use WpPack\Component\Rest\Attribute\RestPreparePostFilter;

class RestResponseFormatter
{
    #[RestPreparePostFilter]
    public function preparePostResponse(
        \WP_REST_Response $response,
        \WP_Post $post,
        \WP_REST_Request $request
    ): \WP_REST_Response {
        $data = $response->get_data();
        $data['reading_time'] = $this->calculateReadingTime($post->post_content);
        $data['author_details'] = $this->getAuthorDetails($post->post_author);

        $response->set_data($data);
        return $response;
    }
}
```

### ディスパッチ前バリデーション

```php
use WpPack\Component\Rest\Attribute\RestPreDispatchFilter;

class RestRequestValidator
{
    #[RestPreDispatchFilter]
    public function preDispatchValidation($result, \WP_REST_Server $server, \WP_REST_Request $request)
    {
        if (!empty($result)) {
            return $result;
        }

        if (!$this->checkRateLimit($request)) {
            return new \WP_Error(
                'rest_rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        return $result;
    }
}
```

### 利用可能な Hook アトリビュート

```php
// 登録
#[RestApiInitAction(priority?: int = 10)]                // rest_api_init - エンドポイント登録

// 認証
#[RestAuthenticationErrorsFilter(priority?: int = 10)]   // rest_authentication_errors - カスタム認証
#[DetermineCurrentUserFilter(priority?: int = 10)]       // determine_current_user - ユーザー判定

// レスポンス
#[RestPreparePostFilter(priority?: int = 10)]            // rest_prepare_post - 投稿レスポンスの変更
#[RestPreServeRequestFilter(priority?: int = 10)]        // rest_pre_serve_request - リクエスト事前処理

// バリデーション
#[RestPreDispatchFilter(priority?: int = 10)]            // rest_pre_dispatch - ディスパッチ前バリデーション
#[RestRequestAfterCallbacksFilter(priority?: int = 10)]  // rest_request_after_callbacks - コールバック後処理
```

## API テスト

```bash
# 全投稿を取得
GET /wp-json/blog-api/v1/posts

# フィルタリング付きで投稿を取得
GET /wp-json/blog-api/v1/posts?page=1&per_page=5&category=technology

# コメント付きで投稿を取得
GET /wp-json/blog-api/v1/posts/123?include_comments=true

# 投稿を作成（認証済み）
POST /wp-json/blog-api/v1/posts
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN

{
  "title": "My New Post",
  "content": "Post content...",
  "status": "publish",
  "categories": [1, 5],
  "tags": ["wordpress", "api"]
}
```

## エンドポイント登録

```php
add_action('rest_api_init', function () {
    $container = new WpPack\Container();
    $container->register([
        PostsEndpoint::class,
        CommentsEndpoint::class,
    ]);
});
```

## このコンポーネントの使用場面

**最適な用途：**
- WordPress 向けの堅牢な REST API の構築
- ヘッドレス WordPress アプリケーション
- モバイルアプリのバックエンド
- サードパーティとの統合
- API ファーストの開発

**代替を検討すべき場合：**
- API が不要なシンプルな WordPress サイト
- 内部専用の機能

## 依存関係

### 必須
- **Hook コンポーネント** - エンドポイント登録フックに使用

### 推奨
- **Security コンポーネント** - 認証と権限チェック
- **Query コンポーネント** - 高度なデータクエリ
