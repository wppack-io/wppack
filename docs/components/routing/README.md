# Routing Component

**パッケージ:** `wppack/routing`
**名前空間:** `WPPack\Component\Routing\`
**レイヤー:** Feature

WordPress リライトルール API を Symfony スタイルの Controller + Route + Response パターンでラップ。Symfony Router を参考にした path ベースのルート定義と、`UrlGenerator` による URL 生成をサポートします。

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

### After（WPPack — path ベースの Route 定義）

```php
use WPPack\Component\Routing\Attribute\Route;
use WPPack\Component\Routing\Response\TemplateResponse;
use WPPack\Component\Routing\RouteRegistry;

#[Route('/products/{product_slug}', name: 'product_detail')]
class ShowProductController
{
    public function __invoke(string $productSlug): TemplateResponse
    {
        return new TemplateResponse(
            get_template_directory() . '/templates/single-product.php',
            ['product_slug' => $productSlug],
        );
    }
}

$registry = new RouteRegistry();
$registry->register(new ShowProductController());
```

`{product_slug}` は自動的に正規表現 `[^/]+` に変換され、WordPress リライトルールとクエリ文字列が生成されます。

## Route パラメータ一覧

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|-----------|------|
| `path` | `string` | （必須） | URL パスパターン（例: `/products/{slug}`） |
| `name` | `string` | `''` | ルート識別子（URL 生成に必要） |
| `requirements` | `array<string, string>` | `[]` | パラメータ正規表現（Symfony 同様） |
| `vars` | `array<string, string>` | `[]` | 静的クエリ変数 |
| `position` | `RoutePosition` | `Top` | `Top`（既存ルールより優先）/ `Bottom` |

### path → regex/query 変換

`{param}` プレースホルダは自動的に WordPress リライトルールに変換されます:

```
path: '/products/{product_slug}'
requirements: []
→ regex: ^products/(?P<product_slug>[^/]+)/?$
→ query: index.php?product_slug=$matches[1]

path: '/events/{year}/{month}'
requirements: ['year' => '\d{4}', 'month' => '\d{2}']
→ regex: ^events/(?P<year>\d{4})/(?P<month>\d{2})/?$
→ query: index.php?year=$matches[1]&month=$matches[2]

path: '/events/{year}'
vars: ['post_type' => 'event']
→ regex: ^events/(?P<year>[^/]+)/?$
→ query: index.php?post_type=event&year=$matches[1]
```

## URL 生成

`UrlGenerator` を使って、ルート名とパラメータから URL を生成できます（Symfony の `UrlGenerator` に相当）。

### UrlGeneratorInterface

```php
namespace WPPack\Component\Routing\Generator;

interface UrlGeneratorInterface
{
    /**
     * @param array<string, string|int> $parameters
     * @throws RouteNotFoundException
     */
    public function generate(string $name, array $parameters = []): string;
}
```

### 使用例

```php
use WPPack\Component\Routing\Generator\UrlGenerator;

$generator = new UrlGenerator($registry);

// 単一パラメータ
$url = $generator->generate('product_detail', ['product_slug' => 'foo']);
// → /products/foo

// 複数パラメータ
$url = $generator->generate('event_archive', ['year' => 2024, 'month' => '03']);
// → /events/2024/03

// 存在しないルート → RouteNotFoundException
$generator->generate('nonexistent'); // throws RouteNotFoundException

// パラメータ不足 → MissingParametersException
$generator->generate('product_detail'); // throws MissingParametersException
```

### DI での登録

```php
use WPPack\Component\Routing\Generator\UrlGenerator;
use WPPack\Component\Routing\Generator\UrlGeneratorInterface;
use WPPack\Component\Routing\RouteRegistry;

// コンテナに登録
$container->set(UrlGeneratorInterface::class, new UrlGenerator(
    $container->get(RouteRegistry::class),
));

// Controller でインジェクション
class ProductController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function redirectToProduct(string $slug): RedirectResponse
    {
        $url = $this->urlGenerator->generate('product_detail', ['product_slug' => $slug]);

        return new RedirectResponse($url);
    }
}
```

## レスポンスタイプ

Controller は `?Response` を返す。`null` を返すと WordPress 通常処理に委譲。

### TemplateResponse（クラシックテンプレート）

WordPress テーマの PHP テンプレートファイルを指定。`wp_head()` / `wp_footer()` を含む通常のテーマ出力が行われる。`context` は `set_query_var()` でテンプレートから `get_query_var()` で取得可能。

```php
#[Route('/member/{member_slug}', name: 'member_profile')]
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
#[Route('/portfolio/{portfolio_slug}', name: 'portfolio_detail')]
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
    #[Route('/api/products', name: 'api_products')]
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
public function __invoke(string $productSlug): ?TemplateResponse
{
    $product = $this->findProduct($productSlug);

    if (!$product) {
        return null; // WordPress の 404 テンプレートが表示される
    }

    return new TemplateResponse(...);
}
```

## AbstractController

Symfony の `AbstractController` と同様のヘルパーメソッドを提供。使用は任意 — Controller は POPO（plain old PHP object）でもレスポンスを `new` で直接返しても動作する。

```php
use WPPack\Component\Routing\AbstractController;
use WPPack\Component\Routing\Attribute\Route;

#[Route('/products/{product_slug}', name: 'product_detail')]
class ShowProductController extends AbstractController
{
    public function __invoke(string $productSlug): ?TemplateResponse
    {
        $product = $this->findProduct($productSlug);

        if (!$product) {
            return null;
        }

        return $this->renderTemplate(
            get_template_directory() . '/templates/single-product.php',
            ['product' => $product],
        );
    }
}
```

**ヘルパーメソッド一覧:**

| メソッド | 返り値 | 用途 |
|---------|--------|------|
| `$this->render($view, $parameters, $status, $headers)` | `Response` | Templating でレンダリング（Symfony スタイル） |
| `$this->renderView($view, $parameters)` | `string` | Templating でレンダリング（HTML 文字列） |
| `$this->renderTemplate($template, $context, $status, $headers)` | `TemplateResponse` | WordPress クラシックテンプレート |
| `$this->renderBlockTemplate($slug, $context, $status, $headers)` | `BlockTemplateResponse` | WordPress FSE ブロックテンプレート |
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
    #[Route('/api/events', name: 'event_api')]
    public function api(): JsonResponse
    {
        return $this->json(['events' => $this->repository->findAll()]);
    }

    #[Route('/events/export', name: 'event_export')]
    public function export(): BinaryFileResponse
    {
        $path = $this->generateCsv();

        return $this->file($path, 'events.csv');
    }

    #[Route('/events', name: 'event_archive')]
    public function archive(): TemplateResponse
    {
        return $this->renderTemplate(
            get_template_directory() . '/templates/event-archive.php',
            ['events' => $this->repository->findAll()],
            headers: ['Cache-Control' => 'public, max-age=3600'],
        );
    }

    #[Route('/events/block', name: 'event_block')]
    public function blockPage(): BlockTemplateResponse
    {
        return $this->renderBlockTemplate('event-archive');
    }
}
```

## エラーハンドリング

Controller から `HttpException` を throw すると、Routing コンポーネントが自動的にキャッチして適切なエラーページを表示します。

### 基本的な使い方

```php
use WPPack\Component\HttpFoundation\Exception\NotFoundException;
use WPPack\Component\HttpFoundation\Exception\ForbiddenException;

#[Route('/products/{product_slug}', name: 'product_detail')]
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
    #[Route('/products/{product_slug}', name: 'product_detail')]
    public function show(string $productSlug): TemplateResponse
    {
        $product = $this->findProduct($productSlug);

        if (!$product) {
            throw new NotFoundException();
        }

        return $this->renderTemplate(
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
// ログ出力（LoggerInterface を DI またはクロージャ経由で注入）
add_action('wppack_routing_exception', function (HttpException $e) use ($logger): void {
    $logger->error('Routing exception', [
        'status' => $e->getStatusCode(),
        'message' => $e->getMessage(),
    ]);
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
use WPPack\Component\Routing\Attribute\Route;
use WPPack\Component\Routing\Response\TemplateResponse;

#[Route('/member/{member_slug}', name: 'member_profile')]
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
use WPPack\Component\Routing\Attribute\Route;
use WPPack\Component\Routing\Response\{TemplateResponse, JsonResponse, RedirectResponse};

class ProductController
{
    #[Route('/products', name: 'product_list')]
    public function list(): TemplateResponse
    {
        return new TemplateResponse(
            get_template_directory() . '/templates/product-list.php',
        );
    }

    #[Route('/products/{product_slug}', name: 'product_detail')]
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
#[Route('/portfolio/{portfolio_slug}', name: 'portfolio_detail')]
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

## requirements（パラメータ正規表現）

`requirements` でパラメータの正規表現パターンを指定。Symfony Router と同じ API。

```php
#[Route(
    '/events/{year}/{month}',
    name: 'event_archive',
    requirements: ['year' => '\d{4}', 'month' => '\d{2}'],
)]
class EventArchiveController
{
    public function __invoke(string $year, string $month): TemplateResponse
    {
        // $year は \d{4} にマッチした値のみ
        // $month は \d{2} にマッチした値のみ
        return new TemplateResponse(
            get_template_directory() . '/templates/event-archive.php',
            ['year' => (int) $year, 'month' => (int) $month],
        );
    }
}
```

## vars（静的クエリ変数）

`vars` でルートに紐づく静的な WordPress クエリ変数を指定。`post_type` の指定などに使用。

```php
#[Route('/events/{year}', name: 'event_by_year', vars: ['post_type' => 'event'])]
class EventByYearController
{
    public function __invoke(string $year): TemplateResponse
    {
        // query: index.php?post_type=event&year=$matches[1]
        return new TemplateResponse(
            get_template_directory() . '/templates/event-year.php',
            ['year' => (int) $year],
        );
    }
}
```

## リライトタグ（高度な使い方）

`#[RewriteTag]` で WordPress リライトタグを登録。path ベースの `{param}` で通常は不要ですが、WordPress のパーマリンク構造と統合する場合に使用します。クラスレベルのタグは全ルートで共有。

```php
use WPPack\Component\Routing\Attribute\RewriteTag;
use WPPack\Component\Routing\Attribute\Route;
use WPPack\Component\Routing\Response\TemplateResponse;

#[RewriteTag('%event_year%', '(\d{4})')]
#[RewriteTag('%event_month%', '(\d{2})')]
class EventController
{
    #[Route('/events/{event_year}/{event_month}', name: 'event_archive', requirements: ['event_year' => '\d{4}', 'event_month' => '\d{2}'])]
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
    #[Route('/events/{event_year}/{event_month}/{event_slug}', name: 'event_detail', requirements: ['event_year' => '\d{4}', 'event_month' => '\d{2}'])]
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
use WPPack\Component\HttpFoundation\ArgumentResolver;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\HttpFoundation\RequestValueResolver;
use WPPack\Component\Routing\AbstractController;
use WPPack\Component\Routing\Attribute\Route;
use WPPack\Component\Routing\Response\TemplateResponse;
use WPPack\Component\Routing\RouteRegistry;

$request = Request::createFromGlobals();
$argumentResolver = new ArgumentResolver([
    new RequestValueResolver($request),
]);

$registry = new RouteRegistry($request, argumentResolver: $argumentResolver);

class ProductController extends AbstractController
{
    #[Route('/products/{product_slug}', name: 'product_detail')]
    public function show(Request $request, string $productSlug): TemplateResponse
    {
        // $productSlug は query var 'product_slug' から自動解決（camelCase ↔ snake_case）
        // $request->attributes->get('product_slug') でも取得可能
        return $this->renderTemplate(
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
use WPPack\Component\Security\Attribute\CurrentUser;

class DashboardController extends AbstractController
{
    #[Route('/dashboard/{dashboard_page}', name: 'dashboard')]
    public function index(#[CurrentUser] \WP_User $user, string $dashboardPage): TemplateResponse
    {
        $this->denyAccessUnlessGranted('read');

        return $this->renderTemplate(
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
use WPPack\Component\HttpFoundation\ArgumentResolver;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\HttpFoundation\RequestValueResolver;
use WPPack\Component\Routing\RouteRegistry;
use WPPack\Component\Security\Security;
use WPPack\Component\Security\ValueResolver\CurrentUserValueResolver;

$request = Request::createFromGlobals();
$security = $container->get(Security::class);
$argumentResolver = new ArgumentResolver([
    new RequestValueResolver($request),
    new CurrentUserValueResolver($security),
]);

$registry = new RouteRegistry($request, $security, argumentResolver: $argumentResolver);
```

## Templating 統合

`RouteRegistry` に `TemplateRendererInterface` を渡すと、`AbstractController` の `render()` / `renderView()` メソッドで Twig 等のテンプレートエンジンを使ったレンダリングが可能になります。

```php
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\HttpFoundation\Response;
use WPPack\Component\Routing\AbstractController;
use WPPack\Component\Routing\Attribute\Route;
use WPPack\Component\Routing\RouteRegistry;
use WPPack\Component\Templating\TemplateRendererInterface;

class ProductController extends AbstractController
{
    #[Route('/products/{product_slug}', name: 'product_detail')]
    public function show(string $productSlug): Response
    {
        $product = $this->findProduct($productSlug);

        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }
}

$request = Request::createFromGlobals();
$registry = new RouteRegistry(
    $request,
    renderer: $container->get(TemplateRendererInterface::class),
);
$registry->register(new ProductController());
```

`render()` は `TemplateRendererInterface::render()` でテンプレートをレンダリングし、HTML を含む `Response` を返します。`renderView()` はレンダリング結果の HTML 文字列のみを返します。

## RouteRegistry

```php
use WPPack\Component\Routing\RouteRegistry;

$request = Request::createFromGlobals();
$registry = new RouteRegistry($request, $security, argumentResolver: $argumentResolver);

// 単一アクション Controller
$registry->register(new ShowProductController());

// 複数アクション Controller（全 #[Route] メソッドが登録される）
$registry->register(new EventController());

// 登録確認
$registry->has('product_detail');         // true
$registry->all();         // array<string, RouteEntry>

// ルート取得（RouteNotFoundException if not found）
$registry->get('product_detail');         // RouteEntry

// リライトルールフラッシュ（プラグイン有効化時のみ！）
register_activation_hook(__FILE__, fn() => $registry->flush());
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/routing.md) を参照してください。

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
- **Templating Component** — `render()` / `renderView()` によるテンプレートレンダリングに使用
