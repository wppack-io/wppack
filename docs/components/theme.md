# Theme コンポーネント

**パッケージ:** `wppack/theme`
**名前空間:** `WpPack\Component\Theme\`
**レイヤー:** Application

WordPress のテーマセットアップ関数 `add_theme_support()` / `register_nav_menus()` / `register_sidebar()` をアトリビュートでラップし、テーマ関連の WordPress フックを Named Hook アトリビュートとして提供するコンポーネントです。

## インストール

```bash
composer require wppack/theme
```

## 基本コンセプト

### 従来の WordPress コード

```php
// functions.php - procedural code with global functions
add_action('after_setup_theme', 'my_theme_setup');
function my_theme_setup() {
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('title-tag');
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'my-theme'),
        'footer' => __('Footer Menu', 'my-theme')
    ));
}

add_action('widgets_init', 'my_theme_sidebars');
function my_theme_sidebars() {
    register_sidebar(array(
        'name' => __('Main Sidebar', 'my-theme'),
        'id' => 'main-sidebar',
    ));
}

add_action('wp_enqueue_scripts', 'my_theme_scripts');
function my_theme_scripts() {
    wp_enqueue_style('my-theme-style', get_stylesheet_uri(), array(), '1.0.0');
    wp_enqueue_script('my-theme-script', get_template_directory_uri() . '/js/script.js', array('jquery'), '1.0.0', true);
}
```

### WpPack コード

```php
namespace MyTheme;

use WpPack\Component\Theme\AbstractTheme;
use WpPack\Component\Theme\Attribute\Theme;
use WpPack\Component\Theme\Attribute\ThemeSupport;
use WpPack\Component\Theme\Attribute\Menu;
use WpPack\Component\Theme\Attribute\Sidebar;

#[Theme(
    textDomain: 'my-theme',
    version: '1.0.0'
)]
#[ThemeSupport('post-thumbnails')]
#[ThemeSupport('automatic-feed-links')]
#[ThemeSupport('title-tag')]
#[ThemeSupport('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption'])]
#[ThemeSupport('custom-logo', [
    'height' => 100,
    'width' => 400,
    'flex-height' => true,
    'flex-width' => true
])]
#[Menu('primary', 'Primary Menu')]
#[Menu('footer', 'Footer Menu')]
#[Sidebar('main', 'Main Sidebar', 'The main widget area')]
class Theme extends AbstractTheme
{
}
```

## Named Hook アトリビュート

### テーマセットアップフック

#### #[AfterSetupThemeAction]

**WordPress フック:** `after_setup_theme`

```php
use WpPack\Component\Theme\Attribute\AfterSetupThemeAction;

class ThemeSetup
{
    #[AfterSetupThemeAction]
    public function setupTheme(): void
    {
        add_theme_support('post-thumbnails');
        add_theme_support('html5', [
            'search-form', 'comment-form', 'comment-list',
            'gallery', 'caption', 'script', 'style',
        ]);
        add_image_size('hero-banner', 1920, 600, true);
        register_nav_menus([
            'primary' => __('Primary Menu', 'wppack-theme'),
            'footer' => __('Footer Menu', 'wppack-theme'),
        ]);
    }

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
            'sanitize_callback' => [$this, 'sanitizeColorScheme'],
        ]);

        $wp_customize->add_control('color_scheme', [
            'label' => __('Color Scheme', 'wppack-theme'),
            'section' => 'wppack_theme_options',
            'type' => 'select',
            'choices' => [
                'light' => __('Light', 'wppack-theme'),
                'dark' => __('Dark', 'wppack-theme'),
                'auto' => __('Auto (System)', 'wppack-theme'),
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

## テーマアトリビュートクイックリファレンス

```php
#[Theme(textDomain: 'text-domain', version: '1.0.0')]
#[ThemeSupport('feature-name', ['options'])]
#[Menu('location', 'Description')]
#[Sidebar('id', 'Name', 'Description')]
```

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress アクション/フィルターの登録

### 推奨
- **DependencyInjection コンポーネント** - サービスコンテナ
- **Config コンポーネント** - 設定管理
- **Filesystem コンポーネント** - テンプレート操作
