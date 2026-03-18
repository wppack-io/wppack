# Routing Component

**パッケージ:** `wppack/routing`
**名前空間:** `WpPack\Component\Routing\`
**レイヤー:** Feature

WordPress リライトルール API を Symfony スタイルの Controller + Route + Response パターンでラップ。Controller は `RouteResponse` を返し、テンプレート指定・JSON・リダイレクト等を宣言的に行えるコンポーネントです。

## インストール

```bash
composer require wppack/routing
```

## 基本コンセプト

### Before（従来の WordPress）

```php
add_action('init', function () {
    add_rewrite_rule('^products/([^/]+)/?$', 'index.php?product_slug=$matches[1]', 'top');
});
add_filter('query_vars', function ($vars) {
    $vars[] = 'product_slug';
    return $vars;
});
add_action('template_redirect', function () {
    $slug = get_query_var('product_slug');
    if (!$slug) return;
    get_header();
    echo '<h1>' . esc_html($slug) . '</h1>';
    get_footer();
    exit;
});
```

### After（WpPack — Controller が TemplateResponse を返す）

```php
use WpPack\Component\Routing\Attribute\Route;
use WpPack\Component\Routing\Response\TemplateResponse;
use WpPack\Component\Routing\RouteRegistry;

#[Route(
    name: 'product_detail',
    regex: '^products/([^/]+)/?$',
    query: 'index.php?product_slug=$matches[1]',
)]
class ShowProductController
{
    public function __invoke(): TemplateResponse
    {
        $slug = get_query_var('product_slug');

        return new TemplateResponse(
            get_template_directory() . '/templates/single-product.php',
            ['product_slug' => $slug],
        );
    }
}

$registry = new RouteRegistry();
$registry->register(new ShowProductController());
```

## レスポンスタイプ

Controller は `?RouteResponse` を返す。`null` を返すと WordPress 通常処理に委譲。

### TemplateResponse（クラシックテンプレート）

WordPress テーマの PHP テンプレートファイルを指定。`wp_head()` / `wp_footer()` を含む通常のテーマ出力が行われる。`context` は `set_query_var()` でテンプレートから `get_query_var()` で取得可能。

```php
#[Route(name: 'member_profile', regex: '^member/([^/]+)/?$', query: 'index.php?member_slug=$matches[1]')]
class ShowMemberController
{
    public function __invoke(string $memberSlug): ?TemplateResponse
    {
        $member = $this->findMember($memberSlug);

        if (!$member) {
            return null; // WordPress の 404 処理に委譲
        }

        return new TemplateResponse(
            get_template_directory() . '/templates/single-member.php',
            ['member' => $member],
        );
    }
}
```

テンプレートファイル（`templates/single-member.php`）:

```php
<?php
$member = get_query_var('member');
get_header();
?>
<div class="member-profile">
    <h1><?= esc_html($member->name) ?></h1>
</div>
<?php get_footer(); ?>
```

### BlockTemplateResponse（FSE ブロックテンプレート）

ブロックテーマの FSE テンプレートを指定。テーマの `templates/` ディレクトリまたはサイトエディタで作成したテンプレートが使用される。

```php
#[Route(name: 'portfolio_detail', regex: '^portfolio/([^/]+)/?$', query: 'index.php?portfolio_slug=$matches[1]')]
class ShowPortfolioController
{
    public function __invoke(string $portfolioSlug): BlockTemplateResponse
    {
        return new BlockTemplateResponse(
            slug: 'single-portfolio',
            context: ['portfolio_slug' => $portfolioSlug],
        );
    }
}
```

テーマの `templates/single-portfolio.html` が使用される。ブロックテーマ + WordPress 5.9+ が必要。

### JsonResponse

JSON API レスポンス。`wp_send_json()` で送信（内部で `wp_die()` を呼ぶ）。

```php
class ProductApiController
{
    #[Route(name: 'api_products', regex: '^api/products/?$', query: 'index.php?api_route=products')]
    public function list(): JsonResponse
    {
        $products = $this->repository->findAll();

        return new JsonResponse(['products' => $products]);
    }
}
```

### RedirectResponse

HTTP リダイレクト。デフォルトで `wp_safe_redirect()`（同一ドメイン制限）を使用。外部リダイレクトには `safe: false` を指定。

```php
return new RedirectResponse('/new-location');                     // 302 + wp_safe_redirect
return new RedirectResponse('/new-location', 301);                // 301 永久リダイレクト
return new RedirectResponse('https://external.com', safe: false); // 外部サイトへのリダイレクト
```

### BinaryFileResponse（ファイルダウンロード）

ファイルを送信するレスポンス。Symfony の `BinaryFileResponse` に相当。

```php
// ダウンロードとして送信（デフォルト）
return new BinaryFileResponse('/path/to/report.pdf');

// カスタムファイル名
return new BinaryFileResponse('/path/to/report.pdf', 'monthly-report.pdf');

// ブラウザ内表示（inline）
return new BinaryFileResponse('/path/to/image.png', disposition: 'inline');

// キャッシュヘッダー付き
return new BinaryFileResponse(
    '/path/to/file.zip',
    'archive.zip',
    headers: ['Cache-Control' => 'no-cache'],
);
```

### Response（HTML コンテンツ）

HTML を直接送信して終了。テーマのヘッダー・フッターを使わない場合に。

```php
return new Response('<html><body><h1>Hello</h1></body></html>');
return new Response('Not Found', 404);
return new Response('', 204, ['X-Custom' => 'value']);
```

### null（WordPress に委譲）

`null` を返すと WordPress の通常テンプレート処理が続行。404 処理をテーマに委譲する場合に有用。

```php
public function __invoke(string $productSlug): ?RouteResponse
{
    $product = $this->findProduct($productSlug);

    if (!$product) {
        return null; // WordPress の 404 テンプレートが表示される
    }

    return new TemplateResponse(...);
}
```

## AbstractController

Symfony の `AbstractController` と同様のヘルパーメソッドを提供。使用は任意 — Controller は POPO（plain old PHP object）でも `RouteResponse` を `new` で直接返しても動作する。

```php
use WpPack\Component\Routing\AbstractController;
use WpPack\Component\Routing\Attribute\Route;

#[Route(
    name: 'product_detail',
    regex: '^products/([^/]+)/?$',
    query: 'index.php?product_slug=$matches[1]',
)]
class ShowProductController extends AbstractController
{
    public function __invoke(string $productSlug): ?TemplateResponse
    {
        $product = $this->findProduct($productSlug);

        if (!$product) {
            return null;
        }

        return $this->render(
            get_template_directory() . '/templates/single-product.php',
            ['product' => $product],
        );
    }
}
```

**ヘルパーメソッド一覧:**

| メソッド | 返り値 | 用途 |
|---------|--------|------|
| `$this->render($template, $context, $status, $headers)` | `TemplateResponse` | クラシックテンプレート |
| `$this->block($slug, $context, $status, $headers)` | `BlockTemplateResponse` | FSE ブロックテンプレート |
| `$this->json($data, $status, $headers)` | `JsonResponse` | JSON レスポンス |
| `$this->redirect($url, $status, $safe, $headers)` | `RedirectResponse` | リダイレクト |
| `$this->file($path, $filename, $disposition, $headers)` | `BinaryFileResponse` | ファイルダウンロード |
| `$this->getUser()` | `?\WP_User` | 認証済みユーザー取得 |
| `$this->isGranted($attribute, $subject)` | `bool` | 権限チェック |
| `$this->denyAccessUnlessGranted($attribute, $subject, $message)` | `void` | 権限がなければ例外 |

### 複数アクション Controller での使用例

```php
class EventController extends AbstractController
{
    #[Route(name: 'event_api', regex: '^api/events/?$', query: 'index.php?api_route=events')]
    public function api(): JsonResponse
    {
        return $this->json(['events' => $this->repository->findAll()]);
    }

    #[Route(name: 'event_export', regex: '^events/export/?$', query: 'index.php?event_action=export')]
    public function export(): BinaryFileResponse
    {
        $path = $this->generateCsv();

        return $this->file($path, 'events.csv');
    }

    #[Route(name: 'event_archive', regex: '^events/?$', query: 'index.php?event_page=archive')]
    public function archive(): TemplateResponse
    {
        return $this->render(
            get_template_directory() . '/templates/event-archive.php',
            ['events' => $this->repository->findAll()],
            headers: ['Cache-Control' => 'public, max-age=3600'],
        );
    }

    #[Route(name: 'event_block', regex: '^events/block/?$', query: 'index.php?event_page=block')]
    public function blockPage(): BlockTemplateResponse
    {
        return $this->block('event-archive');
    }
}
```

## エラーハンドリング

Controller から `HttpException` を throw すると、Routing コンポーネントが自動的にキャッチして適切なエラーページを表示します。

### 基本的な使い方

```php
use WpPack\Component\HttpFoundation\Exception\NotFoundException;
use WpPack\Component\HttpFoundation\Exception\ForbiddenException;

#[Route(name: 'product_detail', regex: '^products/([^/]+)/?$', query: 'index.php?product_slug=$matches[1]')]
class ShowProductController
{
    public function __invoke(string $productSlug): TemplateResponse
    {
        $product = $this->findProduct($productSlug);

        if (!$product) {
            throw new NotFoundException('Product not found.');
        }

        if (!current_user_can('read_product', $product->id)) {
            throw new ForbiddenException('Access denied.');
        }

        return new TemplateResponse(
            get_template_directory() . '/templates/single-product.php',
            ['product' => $product],
        );
    }
}
```

`AbstractController` を使用する場合も同様に throw できます:

```php
class ProductController extends AbstractController
{
    #[Route(name: 'product_detail', regex: '^products/([^/]+)/?$', query: 'index.php?product_slug=$matches[1]')]
    public function show(string $productSlug): TemplateResponse
    {
        $product = $this->findProduct($productSlug);

        if (!$product) {
            throw new NotFoundException();
        }

        return $this->render(
            get_template_directory() . '/templates/single-product.php',
            ['product' => $product],
        );
    }
}
```

### テンプレート探索順序

例外発生時、以下の順序でエラーテンプレートを探索します:

1. **FSE ブロックテンプレート** — `templates/{statusCode}.html`（例: `templates/404.html`, `templates/403.html`）
2. **クラシックテンプレート** — `{statusCode}.php`（例: `404.php`, `403.php`）
3. **`wp_die()` フォールバック** — テンプレートが見つからない場合

`NotFoundException` の場合は WordPress の `$wp_query->set_404()` も呼ばれ、テーマの 404 テンプレートと一貫した動作になります。

### テーマ側のエラーテンプレート

テンプレートには `exception` 変数（`HttpException` オブジェクト）が `set_query_var()` で渡されます。

**クラシックテーマ:**

テーマルートに `403.php`, `500.php` 等を配置:

```php
<?php
// 403.php
$exception = get_query_var('exception');
get_header();
?>
<h1>アクセスが拒否されました</h1>
<p><?= esc_html($exception->getMessage()) ?></p>
<?php get_footer(); ?>
```

**ブロックテーマ:**

`templates/403.html`, `templates/500.html` 等を配置し、サイトエディタまたは HTML ファイルで編集。

### フックポイント

| フック | 種別 | 引数 | 用途 |
|--------|------|------|------|
| `wppack_routing_exception` | action | `HttpException $e` | ログ・モニタリング |
| `wppack_routing_exception_response` | filter | `null`, `HttpException $e` → `?Response` | カスタムレスポンスで上書き |

```php
// ログ出力
add_action('wppack_routing_exception', function (HttpException $e): void {
    error_log(sprintf('[%d] %s', $e->getStatusCode(), $e->getMessage()));
});

// 全エラーを JSON で返す（API 用途）
add_filter('wppack_routing_exception_response', function (?Response $response, HttpException $e): JsonResponse {
    return new JsonResponse(
        ['error' => $e->getErrorCode(), 'message' => $e->getMessage()],
        $e->getStatusCode(),
    );
}, 10, 2);
```

## 単一アクション Controller（\_\_invoke）

Symfony と同様、`__invoke()` メソッドを持つクラスに `#[Route]` をクラスレベルで付与。

```php
use WpPack\Component\Routing\Attribute\Route;
use WpPack\Component\Routing\Response\TemplateResponse;

#[Route(
    name: 'member_profile',
    regex: '^member/([^/]+)/?$',
    query: 'index.php?member_slug=$matches[1]',
)]
class ShowMemberController
{
    public function __invoke(string $memberSlug): TemplateResponse
    {
        return new TemplateResponse(
            get_template_directory() . '/templates/single-member.php',
            ['member_slug' => $memberSlug],
        );
    }
}
```

## 複数アクション Controller

1 つの Controller クラスに複数の `#[Route]` メソッドを定義。

```php
use WpPack\Component\Routing\Attribute\Route;
use WpPack\Component\Routing\Response\{TemplateResponse, JsonResponse, RedirectResponse};

class ProductController
{
    #[Route(
        name: 'product_list',
        regex: '^products/?$',
        query: 'index.php?product_page=list',
    )]
    public function list(): TemplateResponse
    {
        return new TemplateResponse(
            get_template_directory() . '/templates/product-list.php',
        );
    }

    #[Route(
        name: 'product_detail',
        regex: '^products/([^/]+)/?$',
        query: 'index.php?product_slug=$matches[1]',
    )]
    public function show(string $productSlug): ?TemplateResponse
    {
        $product = $this->findProduct($productSlug);

        if (!$product) {
            return null; // 404 に委譲
        }

        return new TemplateResponse(
            get_template_directory() . '/templates/single-product.php',
            ['product' => $product],
        );
    }
}
```

## DI との組み合わせ

Controller はコンストラクタインジェクション可能な普通のクラス。

```php
#[Route(
    name: 'portfolio_detail',
    regex: '^portfolio/([^/]+)/?$',
    query: 'index.php?portfolio_slug=$matches[1]',
)]
class ShowPortfolioController
{
    public function __construct(
        private readonly PortfolioRepository $repository,
    ) {}

    public function __invoke(string $portfolioSlug): ?TemplateResponse
    {
        $portfolio = $this->repository->findBySlug($portfolioSlug);

        if (!$portfolio) {
            return null;
        }

        return new TemplateResponse(
            get_template_directory() . '/templates/single-portfolio.php',
            ['portfolio' => $portfolio],
        );
    }
}

$registry->register($container->get(ShowPortfolioController::class));
```

## Route パラメータ一覧

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|-----------|------|
| `name` | `string` | （必須） | ルート識別子 |
| `regex` | `string` | （必須） | URL マッチング正規表現 |
| `query` | `string` | （必須） | WordPress クエリ文字列マッピング |
| `position` | `RoutePosition` | `Top` | `Top`（既存ルールより優先）/ `Bottom` |

## クエリ変数の自動パース

`query` パラメータから `$matches[N]` を値に持つパラメータ名を自動抽出し、WordPress `query_vars` フィルタに登録。

```
query: 'index.php?post_type=event&event_year=$matches[1]&event_month=$matches[2]'
→ 自動登録: ['event_year', 'event_month']
→ 除外:     'post_type' （静的値 = WordPress 組み込み変数）
```

## リライトタグ

`#[RewriteTag]` で WordPress リライトタグを登録。クラスレベルのタグは全ルートで共有。

```php
use WpPack\Component\Routing\Attribute\RewriteTag;
use WpPack\Component\Routing\Attribute\Route;
use WpPack\Component\Routing\Response\TemplateResponse;

#[RewriteTag('%event_year%', '(\d{4})')]
#[RewriteTag('%event_month%', '(\d{2})')]
class EventController
{
    #[Route(
        name: 'event_archive',
        regex: '^events/%event_year%/%event_month%/?$',
        query: 'index.php?event_year=$matches[1]&event_month=$matches[2]',
    )]
    public function archive(string $eventYear, string $eventMonth): TemplateResponse
    {
        return new TemplateResponse(
            get_template_directory() . '/templates/event-archive.php',
            [
                'event_year' => (int) $eventYear,
                'event_month' => (int) $eventMonth,
            ],
        );
    }

    #[RewriteTag('%event_slug%', '([^/]+)')]
    #[Route(
        name: 'event_detail',
        regex: '^events/%event_year%/%event_month%/%event_slug%/?$',
        query: 'index.php?event_year=$matches[1]&event_month=$matches[2]&event_slug=$matches[3]',
    )]
    public function show(string $eventYear, string $eventMonth, string $eventSlug): TemplateResponse
    {
        return new TemplateResponse(
            get_template_directory() . '/templates/single-event.php',
            [
                'event_year' => (int) $eventYear,
                'event_month' => (int) $eventMonth,
                'event_slug' => $eventSlug,
            ],
        );
    }
}
```

## Request / パラメータ自動注入

`RouteRegistry` にコンストラクタで `Request` を渡すと、コントローラーメソッドのパラメータに `Request` オブジェクトとルートパラメータを自動注入できます。

- camelCase のパラメータ名は snake_case の query var に自動マッチング（例: `$productSlug` → `product_slug`）
- ルートパラメータは `Request::$attributes` にも格納されるため、`$request->attributes->get('product_slug')` でもアクセス可能

```php
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Routing\AbstractController;
use WpPack\Component\Routing\Attribute\Route;
use WpPack\Component\Routing\Response\TemplateResponse;
use WpPack\Component\Routing\RouteRegistry;

$registry = new RouteRegistry(Request::createFromGlobals());

class ProductController extends AbstractController
{
    #[Route(
        name: 'product_detail',
        regex: '^products/([^/]+)/?$',
        query: 'index.php?product_slug=$matches[1]',
    )]
    public function show(Request $request, string $productSlug): TemplateResponse
    {
        // $productSlug は query var 'product_slug' から自動解決（camelCase ↔ snake_case）
        // $request->attributes->get('product_slug') でも取得可能
        return $this->render(
            get_template_directory() . '/templates/single-product.php',
            ['slug' => $productSlug],
        );
    }
}
```

## Security 統合

`RouteRegistry` に `Security` を渡すと、`#[CurrentUser]` アトリビュートによるユーザー注入と `AbstractController` の Security ヘルパーメソッドが利用可能になります。

### `#[CurrentUser]` による WP_User 注入

```php
use WpPack\Component\Security\Attribute\CurrentUser;

class DashboardController extends AbstractController
{
    #[Route(name: 'dashboard', regex: '^dashboard/([^/]+)/?$', query: 'index.php?dashboard_page=$matches[1]')]
    public function index(#[CurrentUser] \WP_User $user, string $dashboardPage): TemplateResponse
    {
        $this->denyAccessUnlessGranted('read');

        return $this->render(
            get_template_directory() . '/templates/dashboard.php',
            [
                'user' => $user,
                'page' => $dashboardPage,
            ],
        );
    }
}
```

### Security ヘルパーメソッド

`AbstractController` を継承すると、以下の Security メソッドが使えます（`RouteRegistry` に `Security` が渡されている場合）:

- `$this->getUser()` — 認証済みユーザー（`?\WP_User`）を取得
- `$this->isGranted($attribute, $subject)` — 権限チェック（`bool`）
- `$this->denyAccessUnlessGranted($attribute, $subject, $message)` — 権限がなければ例外を throw

### 構成

```php
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Routing\RouteRegistry;
use WpPack\Component\Security\Security;

$registry = new RouteRegistry(
    Request::createFromGlobals(),
    $container->get(Security::class),
);
```

## RouteRegistry

```php
use WpPack\Component\Routing\RouteRegistry;

$registry = new RouteRegistry(Request::createFromGlobals(), $security);

// 単一アクション Controller
$registry->register(new ShowProductController());

// 複数アクション Controller（全 #[Route] メソッドが登録される）
$registry->register(new EventController());

// 登録確認
$registry->has('product_detail');         // true
$registry->getRegisteredRoutes();         // array<string, RouteEntry>

// リライトルールフラッシュ（プラグイン有効化時のみ！）
register_activation_hook(__FILE__, fn() => $registry->flush());
```

## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Routing/Subscriber/`

低レベルフック操作用。Controller と併用可能。

**Actions（2）:**

| アトリビュート | WordPress フック |
|--------------|----------------|
| `ParseRequestAction` | `parse_request` |
| `TemplateRedirectAction` | `template_redirect` |

**Filters（7）:**

| アトリビュート | WordPress フック |
|--------------|----------------|
| `RewriteRulesArrayFilter` | `rewrite_rules_array` |
| `RootRewriteRulesFilter` | `root_rewrite_rules` |
| `PostRewriteRulesFilter` | `post_rewrite_rules` |
| `PageRewriteRulesFilter` | `page_rewrite_rules` |
| `QueryVarsFilter` | `query_vars` |
| `RequestFilter` | `request` |
| `TemplateIncludeFilter` | `template_include` |

## プラグイン / テーマでの配置

プラグインやテーマでフロントページコントローラーを作成する場合、以下のディレクトリ構成を推奨します。

```
src/
└── Routing/
    └── Controller/
        ├── ProductPageController.php
        ├── EventController.php
        └── ArchiveController.php
```

REST API コントローラー（`src/Rest/Controller/`）とは別のディレクトリに配置します。

> 詳細は[プラグイン開発ガイド](../../guides/plugin-development.md)、[テーマ開発ガイド](../../guides/theme-development.md)を参照してください。

## 依存関係

### 必須

- **HttpFoundation Component** — Request/Response 基盤
- **Mime Component** — `BinaryFileResponse` の MIME 型判定に使用

### 推奨

- **Hook Component** — Named Hook アトリビュートで使用
- **DependencyInjection Component** — Controller の DI に使用
- **Security Component** — `#[CurrentUser]` 注入と `getUser()` / `isGranted()` に使用
