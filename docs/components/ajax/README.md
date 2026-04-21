# Ajax コンポーネント

**パッケージ:** `wppack/ajax`
**名前空間:** `WPPack\Component\Ajax\`
**Category:** HTTP

WordPress の AJAX ハンドリングをモダン化するコンポーネントです。従来の手続き的なコールバックを、型安全で自動セキュリティ機能を備えたオブジェクト指向のアトリビュートベースハンドラーに置き換えます。

> [!WARNING]
> WordPress の Admin Ajax（`admin-ajax.php`）はレガシーな仕組みです。新規開発では [REST API](../rest/README.md) と [`@wordpress/api-fetch`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-api-fetch/) の利用を推奨します。このコンポーネントは、既存の AJAX ハンドラーの保守や、REST API では対応しにくいケース（例: `admin-ajax.php` に依存するサードパーティプラグインとの統合）で使用してください。

## インストール

```bash
composer require wppack/ajax
```

## このコンポーネントの機能

- **アトリビュートベースの AJAX ハンドラー定義** — `#[Ajax]` で宣言的にハンドラーを定義
- **3種類のアクセス制御** — `Access` enum でログイン/未ログイン/全ユーザーを切り替え
- **自動セキュリティ機能** — 組み込みの nonce 検証と権限チェック
- **型安全なレスポンス** — `JsonResponse` による `wp_send_json_*` ラッパー
- **Named Hook アトリビュート** — 低レベルの WordPress AJAX フック用

## 基本コンセプト

### Before（従来の WordPress）

```php
// 全ユーザー対応には2つのアクションを手動登録
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
      'id' => $post->ID,QQ
            'title' => $post->post_title,
        ];
    }

    wp_send_json_success($data);
}
```

### After（WPPack）

```php
use WPPack\Component\Ajax\Attribute\Ajax;
use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\HttpFoundation\Request;

class ProductController
{
    #[Ajax(action: 'my_search', checkReferer: 'my_nonce')]
    public function search(Request $request): JsonResponse
    {
        $query = sanitize_text_field($request->request->get('query', ''));
        $results = get_posts(['s' => $query, 'post_type' => 'product']);

        return new JsonResponse(array_map(fn ($post) => [
            'id' => $post->ID,
            'title' => $post->post_title,
        ], $results));
    }
}
```

### アクセス制御の Before/After

```php
// Before: ログインユーザーのみ
add_action('wp_ajax_update_product', 'handle_update');
// wp_ajax_nopriv_ を登録しないことでゲストを除外

// Before: 未ログインユーザーのみ
add_action('wp_ajax_nopriv_get_preview', 'handle_preview');
// wp_ajax_ を登録しないことでログインユーザーを除外

// Before: 全ユーザー
add_action('wp_ajax_search', 'handle_search');
add_action('wp_ajax_nopriv_search', 'handle_search');
```

```php
use WPPack\Component\Ajax\Access;
use WPPack\Component\Ajax\Attribute\Ajax;

// After: Access enum で明示的に指定
#[Ajax(action: 'update_product', access: Access::Authenticated)]
public function update(): JsonResponse { /* ... */ }

#[Ajax(action: 'get_preview', access: Access::Guest)]
public function preview(): JsonResponse { /* ... */ }

#[Ajax(action: 'search')]  // デフォルトは Access::Public
public function search(): JsonResponse { /* ... */ }
```

## コアクラス

### Access enum

3種類のアクセスレベルを表す enum です。WordPress の AJAX フック登録に対応します。

| ケース | 説明 | 登録されるフック |
|--------|------|----------------|
| `Access::Public` | 全ユーザー（デフォルト） | `wp_ajax_{action}` + `wp_ajax_nopriv_{action}` |
| `Access::Authenticated` | ログインユーザーのみ | `wp_ajax_{action}` のみ |
| `Access::Guest` | 未ログインユーザーのみ | `wp_ajax_nopriv_{action}` のみ |

### Ajax アトリビュート

メソッドレベルのアトリビュートで、AJAX ハンドラーを宣言的に定義します。

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|-----------|------|
| `action` | `string` | （必須） | WordPress AJAX アクション名 |
| `access` | `Access` | `Access::Public` | アクセスレベル |
| `checkReferer` | `?string` | `null` | nonce アクション名。設定時に `check_ajax_referer()` で検証 |
| `priority` | `int` | `10` | フック優先度 |

> [!NOTE]
> capability チェックには `#[IsGranted('capability')]` アトリビュートを `#[Ajax]` と併用します。詳細は [Security コンポーネント](../security/) を参照。

#### 基本的な使用例

```php
use WPPack\Component\Ajax\Attribute\Ajax;
use WPPack\Component\HttpFoundation\JsonResponse;

class ProductController
{
    // 全ユーザーがアクセス可能
    #[Ajax(action: 'get_products')]
    public function getProducts(): JsonResponse
    {
        $products = get_posts(['post_type' => 'product']);

        return new JsonResponse($products);
    }
}
```

#### 認証付きハンドラー

```php
#[Ajax(action: 'update_product', access: Access::Authenticated)]
public function updateProduct(): JsonResponse
{
    $result = wp_update_post([
        'ID' => (int) $_POST['product_id'],
        'post_title' => sanitize_text_field($_POST['title']),
    ]);

    return $result ? new JsonResponse(['success' => true]) : new JsonResponse(['error' => 'Update failed.'], 400);
}
```

#### nonce + capability 付きハンドラー

```php
use WPPack\Component\Security\Attribute\IsGranted;

#[Ajax(
    action: 'delete_product',
    access: Access::Authenticated,
    checkReferer: 'delete_product_nonce',
)]
#[IsGranted('delete_posts')]
public function deleteProduct(): JsonResponse
{
    // nonce と権限は AjaxHandlerRegistry が自動検証
    wp_delete_post((int) $_POST['product_id']);

    return new JsonResponse(['success' => true]);
}
```

#### Guest のみのハンドラー

```php
#[Ajax(action: 'get_preview', access: Access::Guest)]
public function getPreview(): JsonResponse
{
    return new JsonResponse(['message' => 'Preview for guests']);
}
```

### JsonResponse

`HttpFoundation\JsonResponse` を返すと、`AjaxHandlerRegistry` がステータスコードに基づいて自動的に `wp_send_json_success()` / `wp_send_json_error()` を呼び分けます。

```php
use WPPack\Component\HttpFoundation\JsonResponse;

// 成功レスポンス（statusCode < 400）
$response = new JsonResponse(['key' => 'value']);                // 200 → wp_send_json_success
$response = new JsonResponse(['created' => true], 201);         // 201 → wp_send_json_success

// エラーレスポンス（statusCode >= 400）
$response = new JsonResponse(['error' => 'Something went wrong'], 400);  // → wp_send_json_error
$response = new JsonResponse(['error' => 'Not found'], 404);            // → wp_send_json_error
```

| ステータスコード | WordPress 関数 | 判定 |
|----------------|---------------|------|
| < 400 | `wp_send_json_success()` | 成功 |
| >= 400 | `wp_send_json_error()` | エラー |

### AbstractAjaxController

`AbstractAjaxController` を継承すると、Security メソッドと `json()` レスポンスヘルパーが使えます。Routing の `AbstractController`、Rest の `AbstractRestController` と同じパターンです。

```php
use WPPack\Component\Ajax\AbstractAjaxController;
use WPPack\Component\Ajax\Attribute\Ajax;
use WPPack\Component\Ajax\Access;
use WPPack\Component\HttpFoundation\JsonResponse;

class ProductController extends AbstractAjaxController
{
    #[Ajax(action: 'delete_product', access: Access::Authenticated)]
    public function delete(): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete_posts');

        wp_delete_post((int) $_POST['product_id']);

        return $this->json(['deleted' => true]);
    }

    #[Ajax(action: 'get_product', access: Access::Authenticated)]
    public function get(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'user' => $user?->display_name,
            'can_edit' => $this->isGranted('edit_posts'),
        ]);
    }
}
```

#### 利用可能なメソッド

| メソッド | 説明 |
|---------|------|
| `getUser(): ?\WP_User` | 現在の認証済みユーザーを取得 |
| `isGranted(string, mixed): bool` | 認可チェック |
| `denyAccessUnlessGranted(string, mixed, string): void` | 認可されていない場合 `AccessDeniedException` をスロー |
| `json(mixed, int, array): JsonResponse` | JSON レスポンスを生成 |

> [!WARNING]
> `getUser()`、`isGranted()`、`denyAccessUnlessGranted()` を使うには `wppack/security` パッケージが必要です。Security が未設定の場合、`LogicException` がスローされます。

#### `#[IsGranted]` と `AbstractAjaxController` の使い分け

`#[IsGranted('capability')]` は宣言的な権限チェックで、ハンドラー実行前に自動検証されます。`AbstractAjaxController` の `isGranted()` / `denyAccessUnlessGranted()` は Security コンポーネントのカスタム Voter による高度な認可チェックに使用します。シンプルな capability チェックには `#[IsGranted]` が適しています。

### AjaxHandlerRegistry

`#[Ajax]` アトリビュートを読み取り、WordPress フックに登録するサービスクラスです。

#### register() の動作フロー

1. `ReflectionClass` でメソッドをスキャン
2. `#[Ajax]` アトリビュートを検出
3. `Access` に応じてフック登録:
   - `Public` → `add_action('wp_ajax_{action}', ...)` + `add_action('wp_ajax_nopriv_{action}', ...)`
   - `Authenticated` → `add_action('wp_ajax_{action}', ...)` のみ
   - `Guest` → `add_action('wp_ajax_nopriv_{action}', ...)` のみ
4. コールバック実行時:
   - `checkReferer` 設定あり → `check_ajax_referer()` 検証
   - `#[IsGranted]` 設定あり → `IsGrantedChecker::check()` 検証（失敗時は 403 エラー）
   - メソッド呼び出し
   - 戻り値が `JsonResponse` なら `->send()`

```php
use WPPack\Component\Ajax\AjaxHandlerRegistry;

$registry = new AjaxHandlerRegistry();
$registry->register(new ProductController());
$registry->register(new UserController());
```

#### DI コンテナとの統合

```php
use WPPack\Component\Ajax\AjaxHandlerRegistry;

// サービスプロバイダで登録
$container->singleton(AjaxHandlerRegistry::class);

// init フックでハンドラーを登録
add_action('init', function () use ($container) {
    $registry = $container->get(AjaxHandlerRegistry::class);
    $registry->register($container->get(ProductController::class));
});
```

#### コンストラクタでの Request 注入

`AjaxHandlerRegistry` のコンストラクタに `Request` を渡すと、ハンドラーメソッドにそのインスタンスが注入されます。省略した場合は `Request::createFromGlobals()` が自動的に使用されます。

```php
use WPPack\Component\Ajax\AjaxHandlerRegistry;
use WPPack\Component\HttpFoundation\Request;

$registry = new AjaxHandlerRegistry(Request::createFromGlobals());
```

#### コンストラクタでの Security 注入

`Security` を渡すと、`AbstractAjaxController` を継承したハンドラーに自動的に Security が設定されます。`getUser()`、`isGranted()`、`denyAccessUnlessGranted()` が利用可能になります。

```php
use WPPack\Component\Ajax\AjaxHandlerRegistry;
use WPPack\Component\Security\Security;

$registry = new AjaxHandlerRegistry(security: $security);
$registry->register(new ProductController()); // AbstractAjaxController を継承している場合、Security が自動設定される
```

### Request インジェクション

ハンドラーメソッドのパラメータに `Request` を型宣言すると、リクエストオブジェクトが自動的に注入されます。

```php
use WPPack\Component\Ajax\Attribute\Ajax;
use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\HttpFoundation\Request;

class ProductController
{
    #[Ajax(action: 'search_products')]
    public function search(Request $request): JsonResponse
    {
        $query = $request->request->get('query', '');
        $results = get_posts(['s' => $query, 'post_type' => 'product']);

        return new JsonResponse($results);
    }
}
```

### CurrentUser インジェクション

`#[CurrentUser]` アトリビュートを `\WP_User` パラメータに付与すると、現在のログインユーザーが注入されます。`wppack/security` パッケージが必要です。

```php
use WPPack\Component\Ajax\Attribute\Ajax;
use WPPack\Component\Ajax\Access;
use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Security\Attribute\CurrentUser;

class ProfileController
{
    #[Ajax(action: 'get_profile', access: Access::Authenticated)]
    public function getProfile(#[CurrentUser] \WP_User $user): JsonResponse
    {
        return new JsonResponse([
            'name' => $user->display_name,
            'email' => $user->user_email,
        ]);
    }

    #[Ajax(action: 'update_profile', access: Access::Authenticated)]
    public function updateProfile(Request $request, #[CurrentUser] \WP_User $user): JsonResponse
    {
        wp_update_user([
            'ID' => $user->ID,
            'display_name' => $request->request->get('name'),
        ]);

        return new JsonResponse(['success' => true]);
    }
}
```

`Request` と `#[CurrentUser]` はパラメータの順序に関係なく正しく注入されます。

## セキュリティ

### checkReferer — nonce 検証

`checkReferer` は WordPress の `check_ajax_referer($action)` に渡す **nonce アクション名**（文字列）です。

WordPress の nonce は「アクション名」をキーにして生成・検証する仕組みです。`checkReferer` に文字列を設定すると、ハンドラー実行前に `check_ajax_referer()` が自動で呼ばれ、リクエストに含まれる nonce トークン（`_ajax_nonce` または `_wpnonce` パラメータ）を検証します。`null`（デフォルト）なら検証をスキップします。

nonce アクション名は AJAX アクション名と一致する必要はなく、開発者が自由に決められます。同じ nonce アクション名を複数のハンドラーで共有することも可能です。

```php
// PHP 側 — nonce 生成（テンプレートで出力）
wp_create_nonce('delete_product_nonce')  // → "a1b2c3d4e5"

// PHP 側 — ハンドラー定義
#[Ajax(action: 'delete_product', checkReferer: 'delete_product_nonce')]
public function delete(): JsonResponse { /* ... */ }
// → AjaxHandlerRegistry が check_ajax_referer('delete_product_nonce') を自動実行
```

```javascript
// JavaScript 側 — nonce をリクエストに含める
jQuery.post(ajaxurl, {
    action: 'delete_product',
    _ajax_nonce: '<?= wp_create_nonce("delete_product_nonce") ?>',
    product_id: 123,
});
```

nonce + 権限チェックの組み合わせ:

```php
#[Ajax(
    action: 'publish_post',
    access: Access::Authenticated,
    checkReferer: 'publish_post_nonce',
)]
#[IsGranted('publish_posts')]
```

### 推奨パターン

| ユースケース | access | `#[IsGranted]` | checkReferer |
|-------------|--------|-----------|-------------|
| 公開検索 | `Public` | - | - |
| ログインユーザーのデータ取得 | `Authenticated` | - | - |
| データ変更操作 | `Authenticated` | `edit_posts` | 設定推奨 |
| 削除操作 | `Authenticated` | `delete_posts` | 設定必須 |
| 管理者専用操作 | `Authenticated` | `manage_options` | 設定必須 |

## JavaScript 連携

```javascript
// jQuery を使用
jQuery.post(ajaxurl, {
    action: 'search_products',
    _ajax_nonce: wppackAjax.nonce,
    query: 'laptop',
}, function (response) {
    if (response.success) {
        console.log(response.data);
    }
});

// Fetch API を使用
const formData = new FormData();
formData.append('action', 'search_products');
formData.append('_ajax_nonce', wppackAjax.nonce);
formData.append('query', 'laptop');

const response = await fetch(ajaxurl, {
    method: 'POST',
    body: formData,
});
const result = await response.json();
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/ajax.md) を参照してください。

## クラスリファレンス

| クラス | 説明 |
|-------|------|
| `AbstractAjaxController` | Security メソッド + `json()` ヘルパーを提供する基底クラス |
| `Access` | 3種類のアクセスレベル enum（Public / Authenticated / Guest） |
| `Attribute\Ajax` | メソッドレベル AJAX ハンドラーアトリビュート |
| `Response\JsonResponse` | `wp_send_json_success/error` ラッパー |
| `AjaxHandlerRegistry` | アトリビュートスキャン + フック登録サービス |

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

## プラグイン / テーマでの配置

プラグインやテーマで AJAX ハンドラーを作成する場合、以下のディレクトリ構成を推奨します。

```
src/
└── Ajax/
    └── Handler/
        ├── SearchHandler.php
        ├── FilterHandler.php
        └── FormHandler.php
```

> 詳細は[プラグイン開発ガイド](../../guides/plugin-development.md)、[テーマ開発ガイド](../../guides/theme-development.md)を参照してください。

## 依存関係

### 推奨
- **Security コンポーネント** — nonce と権限管理用
- **Cache コンポーネント** — レスポンスキャッシュ用
- **Logger コンポーネント** — リクエストログ用
