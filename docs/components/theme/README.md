# Theme コンポーネント

**パッケージ:** `wppack/theme`
**名前空間:** `WPPack\Component\Theme\`
**レイヤー:** Application

テーマ関連の WordPress フックを Named Hook アトリビュートとして提供するコンポーネントです。

> [!NOTE]
> テーマのブートストラップ、`add_theme_support()` / `register_nav_menus()` / `register_sidebar()` などのセットアップは [Kernel コンポーネント](../kernel/README.md) の `ThemeInterface` が提供します。

> [!NOTE]
> `after_setup_theme` フックは Hook コンポーネントの `AfterSetupThemeAction` を使用してください。Theme コンポーネントはアセット管理・テンプレート出力・カスタマイザーに特化した Named Hook を提供します。

## インストール

```bash
composer require wppack/theme
```

## 基本コンセプト

テーマのセットアップ（`add_theme_support()`、`register_nav_menus()`、`register_sidebar()` など）は Kernel コンポーネントの `ThemeInterface` を実装して行います。Theme コンポーネントはテーマ関連の WordPress フックを Named Hook Attributes として提供します。

```php
// テーマのセットアップは AbstractTheme を継承（→ Kernel コンポーネント参照）
use WPPack\Component\Kernel\Kernel;
use WPPack\Component\Kernel\AbstractTheme;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;

class MyTheme extends AbstractTheme
{
    public function __construct(string $themeFile)
    {
        parent::__construct($themeFile);
    }

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
Kernel::registerTheme(new MyTheme(__FILE__));
```

Theme コンポーネントの Named Hook Attributes を使って、テーマ関連フックを宣言的に扱います：

```php
use WPPack\Component\Hook\Attribute\Theme\Action\WpEnqueueScriptsAction;
use WPPack\Component\Hook\Attribute\Theme\Filter\BodyClassFilter;

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

## Hook アトリビュート

→ 詳細は [Hook コンポーネント — Theme](../hook/theme.md) を参照してください。

## このコンポーネントの使用場面

**最適な用途：**
- アセット管理（`wp_enqueue_scripts`）、テンプレート出力（`wp_head`、`wp_footer`）、カスタマイザーなどのフックを宣言的に扱いたい場合
- `body_class`、`script_loader_tag` などのフィルターを型安全に扱いたい場合

**代替を検討すべき場合：**
- テーマの基本セットアップ（`add_theme_support()`、`register_nav_menus()` 等） → `ThemeInterface::boot()`（[Kernel コンポーネント](../kernel/README.md)）を使用

## 依存関係

### 推奨
- **DependencyInjection コンポーネント** - サービスコンテナ
- **Filesystem コンポーネント** - テンプレート操作
