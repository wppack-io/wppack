# Theme コンポーネント

**パッケージ:** `wppack/theme`
**名前空間:** `WpPack\Component\Theme\`
**レイヤー:** Application

テーマ関連の WordPress フックを Named Hook アトリビュートとして提供するコンポーネントです。

> [!NOTE]
> テーマのブートストラップ、`add_theme_support()` / `register_nav_menus()` / `register_sidebar()` などのセットアップは [Kernel コンポーネント](kernel/README.md) の `ThemeInterface` が提供します。

## インストール

```bash
composer require wppack/theme
```

## 基本コンセプト

テーマのセットアップ（`add_theme_support()`、`register_nav_menus()`、`register_sidebar()` など）は Kernel コンポーネントの `ThemeInterface` を実装して行います。Theme コンポーネントはテーマ関連の WordPress フックを Named Hook Attributes として提供します。

```php
// テーマのセットアップは ThemeInterface を実装（→ Kernel コンポーネント参照）
use WpPack\Component\Kernel\Kernel;
use WpPack\Component\Kernel\ThemeInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;

class MyTheme implements ThemeInterface
{
    public function register(ContainerBuilder $container): void
    {
        $container->discover(
            namespace: 'MyTheme\\',
            directory: __DIR__ . '/src',
        );
    }

    public function getCompilerPasses(): array
    {
        return [];
    }

    public function boot(Container $container): void
    {
        add_theme_support('post-thumbnails');
        add_theme_support('title-tag');
        add_theme_support('html5', [
            'search-form', 'comment-form', 'comment-list', 'gallery', 'caption',
        ]);

        register_nav_menus([
            'primary' => 'Primary Navigation',
            'footer' => 'Footer Navigation',
        ]);
    }
}

// functions.php で：
Kernel::registerTheme(new MyTheme());
```

Theme コンポーネントの Named Hook Attributes を使って、テーマ関連フックを宣言的に扱います：

```php
use WpPack\Component\Theme\Attribute\WpEnqueueScriptsAction;
use WpPack\Component\Theme\Attribute\BodyClassFilter;

class ThemeAssets
{
    #[WpEnqueueScriptsAction]
    public function enqueueFrontendAssets(): void
    {
        $theme_version = wp_get_theme()->get('Version');
        wp_enqueue_style('my-theme-style', get_stylesheet_uri(), [], $theme_version);
    }

    #[BodyClassFilter]
    public function addCustomBodyClasses(array $classes): array
    {
        $classes[] = 'scheme-' . get_theme_mod('color_scheme', 'light');
        return $classes;
    }
}
```

## Named Hook アトリビュート

### テーマセットアップフック

#### #[AfterSetupThemeAction]

**WordPress フック:** `after_setup_theme`

> [!NOTE]
> テーマの基本セットアップ（`add_theme_support()`、`register_nav_menus()` 等）は `ThemeInterface::boot()` で行います。`#[AfterSetupThemeAction]` は `after_setup_theme` タイミングで実行する必要がある処理に使用します。

```php
use WpPack\Component\Theme\Attribute\AfterSetupThemeAction;

class ThemeTextDomain
{
    #[AfterSetupThemeAction(priority: 0)]
    public function loadTextDomain(): void
    {
        load_theme_textdomain('wppack-theme', get_template_directory() . '/languages');
    }
}
```

#### #[WidgetsInitAction]

**WordPress フック:** `widgets_init`

```php
use WpPack\Component\Theme\Attribute\WidgetsInitAction;

class WidgetAreas
{
    #[WidgetsInitAction]
    public function registerSidebars(): void
    {
        register_sidebar([
            'name' => __('Primary Sidebar', 'wppack-theme'),
            'id' => 'primary-sidebar',
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget' => '</section>',
            'before_title' => '<h3 class="widget-title">',
            'after_title' => '</h3>',
        ]);
    }
}
```

### アセット管理フック

#### #[WpEnqueueScriptsAction]

**WordPress フック:** `wp_enqueue_scripts`

```php
use WpPack\Component\Theme\Attribute\WpEnqueueScriptsAction;

class ThemeAssets
{
    #[WpEnqueueScriptsAction]
    public function enqueueFrontendAssets(): void
    {
        $theme_version = wp_get_theme()->get('Version');
        wp_enqueue_style('wppack-theme-style', get_stylesheet_uri(), [], $theme_version);
        wp_enqueue_script('wppack-theme-script', get_template_directory_uri() . '/assets/js/theme.js', ['jquery'], $theme_version, true);
    }
}
```

### テンプレートと出力フック

#### #[WpHeadAction] / #[WpFooterAction] / #[WpBodyOpenAction]

```php
use WpPack\Component\Theme\Attribute\WpHeadAction;
use WpPack\Component\Theme\Attribute\WpFooterAction;

class ThemeOutput
{
    #[WpHeadAction(priority: 5)]
    public function addMetaTags(): void
    {
        echo '<meta name="theme-color" content="#0073aa">';
    }

    #[WpFooterAction(priority: 100)]
    public function addBackToTop(): void
    {
        echo '<button id="back-to-top" class="back-to-top" aria-label="Back to top"></button>';
    }
}
```

### カスタマイザーフック

#### #[CustomizeRegisterAction]

**WordPress フック:** `customize_register`

```php
use WpPack\Component\Theme\Attribute\CustomizeRegisterAction;

class ThemeCustomizer
{
    #[CustomizeRegisterAction]
    public function registerCustomizerOptions($wp_customize): void
    {
        $wp_customize->add_section('wppack_theme_options', [
            'title' => __('Theme Options', 'wppack-theme'),
            'priority' => 130,
        ]);

        $wp_customize->add_setting('color_scheme', [
            'default' => 'light',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        $wp_customize->add_control('color_scheme', [
            'label' => __('Color Scheme', 'wppack-theme'),
            'section' => 'wppack_theme_options',
            'type' => 'select',
            'choices' => [
                'light' => __('Light', 'wppack-theme'),
                'dark' => __('Dark', 'wppack-theme'),
            ],
        ]);
    }
}
```

### フィルターフック

#### #[BodyClassFilter] / #[PostClassFilter]

```php
use WpPack\Component\Theme\Attribute\BodyClassFilter;

class ThemeBodyClass
{
    #[BodyClassFilter]
    public function addCustomBodyClasses(array $classes): array
    {
        $classes[] = 'scheme-' . get_theme_mod('color_scheme', 'light');

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            foreach ($user->roles as $role) {
                $classes[] = 'role-' . $role;
            }
        }

        return $classes;
    }
}
```

#### #[ScriptLoaderTagFilter] / #[StyleLoaderTagFilter]

```php
use WpPack\Component\Theme\Attribute\ScriptLoaderTagFilter;

class ThemePerformance
{
    #[ScriptLoaderTagFilter]
    public function addAsyncDefer(string $tag, string $handle, string $src): string
    {
        $async_scripts = ['wppack-theme-analytics'];
        if (in_array($handle, $async_scripts)) {
            return str_replace(' src', ' async src', $tag);
        }

        $defer_scripts = ['wppack-theme-enhancements'];
        if (in_array($handle, $defer_scripts)) {
            return str_replace(' src', ' defer src', $tag);
        }

        return $tag;
    }
}
```

## Hook アトリビュートリファレンス

```php
// テーマセットアップ
#[AfterSetupThemeAction(priority?: int = 10)]      // テーマの初期化
#[WidgetsInitAction(priority?: int = 10)]           // ウィジェットエリアの登録

// アセット管理
#[WpEnqueueScriptsAction(priority?: int = 10)]      // フロントエンドのスクリプトとスタイル
#[WpPrintStylesAction(priority?: int = 10)]          // スタイルの直接出力
#[WpPrintScriptsAction(priority?: int = 10)]         // スクリプトの直接出力

// テンプレート出力
#[WpHeadAction(priority?: int = 10)]                 // <head> セクションのコンテンツ
#[WpFooterAction(priority?: int = 10)]               // </body> 前のコンテンツ
#[WpBodyOpenAction(priority?: int = 10)]              // <body> タグ直後のコンテンツ
#[TemplateRedirectAction(priority?: int = 10)]        // テンプレートルーティングロジック

// カスタマイザー
#[CustomizeRegisterAction(priority?: int = 10)]       // カスタマイザーオプションの登録
#[CustomizePreviewInitAction(priority?: int = 10)]    // プレビュースクリプト

// フィルター
#[BodyClassFilter(priority?: int = 10)]               // Body の CSS クラス
#[PostClassFilter(priority?: int = 10)]               // 投稿の CSS クラス
#[ScriptLoaderTagFilter(priority?: int = 10)]         // script タグの変更
#[StyleLoaderTagFilter(priority?: int = 10)]          // style タグの変更
```

## このコンポーネントの使用場面

**最適な用途：**
- アセット管理（`wp_enqueue_scripts`）、テンプレート出力（`wp_head`、`wp_footer`）、カスタマイザーなどのフックを宣言的に扱いたい場合
- `body_class`、`script_loader_tag` などのフィルターを型安全に扱いたい場合

**代替を検討すべき場合：**
- テーマの基本セットアップ（`add_theme_support()`、`register_nav_menus()` 等） → `ThemeInterface::boot()`（[Kernel コンポーネント](kernel/README.md)）を使用

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress アクション/フィルターの登録

### 推奨
- **DependencyInjection コンポーネント** - サービスコンテナ
- **Config コンポーネント** - 設定管理
- **Filesystem コンポーネント** - テンプレート操作
