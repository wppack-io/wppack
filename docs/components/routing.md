# Routing Component

**パッケージ:** `wppack/routing`
**名前空間:** `WpPack\Component\Routing\`
**レイヤー:** Feature

WordPress のリライトルール API（`add_rewrite_rule()` / `add_rewrite_tag()`）をアトリビュートベースでラップし、宣言的なルート定義を提供するコンポーネントです。

## インストール

```bash
composer require wppack/routing
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// 従来の WordPress - 手続き型のリライトルール管理
add_action('init', function () {
    add_rewrite_rule(
        '^products/([^/]+)/([^/]+)/?$',
        'index.php?product_category=$matches[1]&product_name=$matches[2]',
        'top'
    );

    add_rewrite_tag('%product_category%', '([^/]+)');
    add_rewrite_tag('%product_name%', '([^/]+)');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'product_category';
    $vars[] = 'product_name';
    return $vars;
});

add_action('template_redirect', function () {
    $category = get_query_var('product_category');
    $name = get_query_var('product_name');

    if ($category && $name) {
        include get_template_directory() . '/product-single.php';
        exit;
    }
});
```

### After（WpPack）

```php
use WpPack\Component\Routing\Attribute\RewriteRule;
use WpPack\Component\Routing\Attribute\RewriteTag;

class ProductRoutes
{
    #[RewriteTag('%product_category%', '([^/]+)')]
    #[RewriteTag('%product_name%', '([^/]+)')]
    #[RewriteRule(
        regex: '^products/([^/]+)/([^/]+)/?$',
        query: 'index.php?product_category=$matches[1]&product_name=$matches[2]',
        position: 'top',
    )]
    public function productPage(): void
    {
        $category = get_query_var('product_category');
        $name = get_query_var('product_name');

        if ($category && $name) {
            $this->renderProduct($category, $name);
        }
    }
}
```

## 主要機能

### #[RewriteRule] アトリビュート

`add_rewrite_rule()` をアトリビュートでラップします。ルートの定義をメソッドに直接宣言できます。

```php
use WpPack\Component\Routing\Attribute\RewriteRule;

class CustomRoutes
{
    #[RewriteRule(
        regex: '^events/(\d{4})/(\d{2})/?$',
        query: 'index.php?post_type=event&event_year=$matches[1]&event_month=$matches[2]',
        position: 'top',
    )]
    public function eventArchive(): void
    {
        $year = get_query_var('event_year');
        $month = get_query_var('event_month');
        // イベントアーカイブの表示
    }

    #[RewriteRule(
        regex: '^member/([^/]+)/?$',
        query: 'index.php?member_slug=$matches[1]',
        position: 'top',
    )]
    public function memberProfile(): void
    {
        $slug = get_query_var('member_slug');
        // メンバープロフィールの表示
    }
}
```

### #[RewriteTag] アトリビュート

`add_rewrite_tag()` をアトリビュートでラップします。カスタムクエリ変数を宣言的に登録できます。

```php
use WpPack\Component\Routing\Attribute\RewriteTag;
use WpPack\Component\Routing\Attribute\RewriteRule;

class EventRoutes
{
    #[RewriteTag('%event_year%', '(\d{4})')]
    #[RewriteTag('%event_month%', '(\d{2})')]
    #[RewriteRule(
        regex: '^events/%event_year%/%event_month%/?$',
        query: 'index.php?post_type=event&event_year=$matches[1]&event_month=$matches[2]',
        position: 'top',
    )]
    public function eventArchive(): void
    {
        // イベントアーカイブの処理
    }
}
```

### #[QueryVar] アトリビュート

`query_vars` フィルタへのカスタムクエリ変数の登録をアトリビュートで宣言します。

```php
use WpPack\Component\Routing\Attribute\QueryVar;
use WpPack\Component\Routing\Attribute\RewriteRule;

class SearchRoutes
{
    #[QueryVar('custom_search_type')]
    #[QueryVar('custom_sort_by')]
    #[RewriteRule(
        regex: '^search/([^/]+)/([^/]+)/?$',
        query: 'index.php?custom_search_type=$matches[1]&custom_sort_by=$matches[2]',
        position: 'top',
    )]
    public function customSearch(): void
    {
        $searchType = get_query_var('custom_search_type');
        $sortBy = get_query_var('custom_sort_by');
        // カスタム検索の処理
    }
}
```

## クイックスタート

### ルートの自動登録

```php
use WpPack\Component\Routing\RouteManager;

class RoutingService
{
    public function __construct(
        private readonly RouteManager $routeManager,
    ) {}

    #[Action('init', priority: 10)]
    public function onInit(): void
    {
        // #[RewriteRule] / #[RewriteTag] アトリビュートを持つクラスを自動検出
        $this->routeManager->discoverRoutes();
    }
}
```

### テンプレートリダイレクトとの連携

```php
use WpPack\Component\Routing\Attribute\RewriteRule;
use WpPack\Component\Routing\Attribute\RewriteTag;
use WpPack\Component\Routing\Attribute\TemplateRedirect;

class PortfolioRoutes
{
    #[RewriteTag('%portfolio_slug%', '([^/]+)')]
    #[RewriteRule(
        regex: '^portfolio/([^/]+)/?$',
        query: 'index.php?portfolio_slug=$matches[1]',
        position: 'top',
    )]
    #[TemplateRedirect]
    public function showPortfolio(): void
    {
        $slug = get_query_var('portfolio_slug');
        if (!$slug) {
            return;
        }

        $portfolio = $this->repository->findBySlug($slug);
        if (!$portfolio) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        $this->render('portfolio/single', ['portfolio' => $portfolio]);
        exit;
    }
}
```

## Named Hook Attributes

### リライトフック

```php
#[RewriteRulesArrayFilter(priority: 10)]           // リライトルール配列のフィルタ
#[RootRewriteRulesFilter(priority: 10)]            // ルートリライトルールのフィルタ
#[PostRewriteRulesFilter(priority: 10)]            // 投稿リライトルールのフィルタ
#[PageRewriteRulesFilter(priority: 10)]            // ページリライトルールのフィルタ
```

### クエリフック

```php
#[QueryVarsFilter(priority: 10)]                   // クエリ変数のフィルタ
#[RequestFilter(priority: 10)]                     // リクエストのフィルタ
#[ParseRequestAction(priority: 10)]                // リクエスト解析時
```

### テンプレートフック

```php
#[TemplateRedirectAction(priority: 10)]            // テンプレートリダイレクト時
#[TemplateIncludeFilter(priority: 10)]             // テンプレートインクルードのフィルタ
```

### 使用例：リライトルールのカスタマイズ

```php
use WpPack\Component\Routing\Attribute\RewriteRulesArrayFilter;
use WpPack\Component\Routing\Attribute\QueryVarsFilter;
use WpPack\Component\Routing\Attribute\TemplateRedirectAction;

class CustomRouteHandler
{
    #[QueryVarsFilter(priority: 10)]
    public function addQueryVars(array $vars): array
    {
        $vars[] = 'custom_page';
        $vars[] = 'custom_action';
        return $vars;
    }

    #[TemplateRedirectAction(priority: 10)]
    public function handleTemplateRedirect(): void
    {
        $customPage = get_query_var('custom_page');
        if (!$customPage) {
            return;
        }

        $this->handleCustomPage($customPage);
        exit;
    }
}
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `RouteManager` | ルートの登録・管理 |
| `Attribute\RewriteRule` | `add_rewrite_rule()` ラッパーアトリビュート |
| `Attribute\RewriteTag` | `add_rewrite_tag()` ラッパーアトリビュート |
| `Attribute\QueryVar` | クエリ変数登録アトリビュート |
| `Attribute\TemplateRedirect` | テンプレートリダイレクトアトリビュート |

## 利用シーン

**最適なケース:**
- カスタムリライトルールの管理をアトリビュートで宣言的に行いたい場合
- カスタム投稿タイプの URL 構造をカスタマイズしたい場合
- カスタムクエリ変数を多数使用するアプリケーション

**代替を検討すべきケース:**
- WordPress 標準のパーマリンク構造で十分な場合
- カスタムルーティングの必要がないシンプルなサイト

## 依存関係

### 必須
- なし — WordPress のリライト API をそのまま利用

### 推奨
- **Cache Component** — ルートキャッシュ
- **EventDispatcher Component** — ルーティングイベント
