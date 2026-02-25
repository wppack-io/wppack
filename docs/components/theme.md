# Theme コンポーネント

**パッケージ:** `wppack/theme`
**名前空間:** `WpPack\Component\Theme\`
**レイヤー:** Application

依存性注入、テンプレート管理、強化されたカスタマイザーサポート、アトリビュートベースの設定を備えた、モダンでオブジェクト指向な WordPress テーマ開発のためのコンポーネントです。

## インストール

```bash
composer require wppack/theme
```

## 基本コンセプト

### 従来の WordPress コード

```php
// functions.php - グローバル関数を使った手続き型コード
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

add_action('wp_enqueue_scripts', 'my_theme_scripts');
function my_theme_scripts() {
    wp_enqueue_style('my-theme-style', get_stylesheet_uri(), array(), '1.0.0');
    wp_enqueue_script('my-theme-script', get_template_directory_uri() . '/js/script.js', array('jquery'), '1.0.0', true);
}
```

### WpPack コード

```php
// functions.php
<?php
use MyTheme\Theme;

require_once __DIR__ . '/vendor/autoload.php';
Theme::boot(__DIR__);

// src/Theme.php
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
#[Sidebar('main', 'Main Sidebar', 'The main sidebar')]
class Theme extends AbstractTheme
{
    protected function getServiceProviders(): array
    {
        return [
            Providers\ThemeServiceProvider::class,
            Providers\CustomizerServiceProvider::class,
            Providers\BlockServiceProvider::class,
            Providers\AssetServiceProvider::class,
        ];
    }

    protected function onActivation(): void
    {
        $this->customizer->setDefaults([
            'header_color' => '#000000',
            'show_tagline' => true
        ]);
        $this->createDefaultPages();
    }
}
```

## 機能

- **オブジェクト指向テーマアーキテクチャ** - クラスベースの構造
- **依存性注入** - テーマサービスとコンポーネントの注入
- **強化されたテンプレート階層** - コンポーネントベースのテンプレート
- **テーマカスタマイザー統合** - Fluent API による設定
- **アセット管理** - スクリプトとスタイルの管理
- **テーマ設定管理** - 型安全な設定
- **子テーマサポート** - 親テーマのオーバーライド
- **テンプレートパーツ管理** - 再利用可能なコンポーネント
- **ブロックテーマサポート** - パターンとスタイル
- **テーマ更新ハンドリング** - バージョンチェック付き

## クイックスタート

### 1. テーマ構造の作成

```bash
mkdir my-awesome-theme
cd my-awesome-theme
composer init
composer require wppack/theme
```

### 2. style.css

```css
/*
Theme Name: My Awesome Theme
Theme URI: https://example.com/
Author: Your Name
Description: A modern WpPack-powered theme
Version: 1.0.0
Text Domain: my-awesome-theme
*/
```

### 3. functions.php

```php
<?php
use MyAwesomeTheme\Theme;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
Theme::boot(__DIR__);
```

### 4. メインテーマクラス

```php
<?php
namespace MyAwesomeTheme;

use WpPack\Component\Theme\AbstractTheme;
use WpPack\Component\Theme\Attribute\Theme;
use WpPack\Component\Theme\Attribute\ThemeSupport;
use WpPack\Component\Theme\Attribute\Menu;
use WpPack\Component\Theme\Attribute\Sidebar;

#[Theme(
    textDomain: 'my-awesome-theme',
    version: '1.0.0'
)]
#[ThemeSupport('post-thumbnails')]
#[ThemeSupport('automatic-feed-links')]
#[ThemeSupport('title-tag')]
#[ThemeSupport('html5', ['search-form', 'comment-form', 'comment-list'])]
#[Menu('primary', 'Primary Menu')]
#[Menu('footer', 'Footer Menu')]
#[Sidebar('main', 'Main Sidebar', 'The main widget area')]
class Theme extends AbstractTheme
{
    protected function getServiceProviders(): array
    {
        return [
            Core\ServiceProvider::class,
            Assets\ServiceProvider::class,
            Customizer\ServiceProvider::class,
        ];
    }
}
```

## サービスベースアーキテクチャ

```php
namespace MyAwesomeTheme\Core;

use WpPack\Component\DependencyInjection\ServiceProvider;
use WpPack\Component\Theme\Contracts\ThemeInterface;

class ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(TemplateRenderer::class);
        $this->container->singleton(MenuWalker::class);
        $this->container->bind(SearchInterface::class, EnhancedSearch::class);
    }

    public function boot(): void
    {
        $theme = $this->container->get(ThemeInterface::class);
        load_theme_textdomain(
            $theme->getTextDomain(),
            $theme->getPath('languages')
        );
    }
}
```

## 強化されたカスタマイザー

```php
namespace MyAwesomeTheme\Customizer;

use WpPack\Component\Theme\Customizer\AbstractCustomizer;
use WpPack\Component\Theme\Customizer\Section;
use WpPack\Component\Theme\Customizer\Control;

class ThemeCustomizer extends AbstractCustomizer
{
    protected function sections(): array
    {
        return [
            Section::create('header_settings', __('Header Settings', 'my-theme'))
                ->priority(30)
                ->capability('edit_theme_options'),

            Section::create('footer_settings', __('Footer Settings', 'my-theme'))
                ->priority(40)
                ->panel('theme_options')
        ];
    }

    protected function controls(): array
    {
        return [
            Control::select('header_layout')
                ->label(__('Header Layout', 'my-theme'))
                ->section('header_settings')
                ->choices([
                    'left' => __('Logo Left', 'my-theme'),
                    'centered' => __('Logo Centered', 'my-theme'),
                    'right' => __('Logo Right', 'my-theme')
                ]),

            Control::checkbox('header_sticky')
                ->label(__('Sticky Header', 'my-theme'))
                ->section('header_settings'),

            Control::color('primary_color')
                ->label(__('Primary Color', 'my-theme'))
                ->section('colors')
        ];
    }
}
```

## アセット管理

```php
namespace MyAwesomeTheme\Assets;

use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Theme\Contracts\ThemeInterface;

class AssetManager
{
    public function __construct(
        private ThemeInterface $theme
    ) {}

    #[Action('wp_enqueue_scripts')]
    public function enqueueScripts(): void
    {
        wp_enqueue_style(
            'theme-style',
            $this->theme->getUrl('style.css'),
            [],
            $this->theme->getVersion()
        );

        wp_enqueue_script(
            'theme-main',
            $this->theme->getUrl('assets/js/main.js'),
            ['jquery'],
            $this->theme->getVersion(),
            true
        );

        wp_localize_script('theme-main', 'themeData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('theme_nonce'),
            'i18n' => [
                'loading' => __('Loading...', 'my-awesome-theme'),
                'error' => __('An error occurred', 'my-awesome-theme'),
            ]
        ]);
    }

    #[Action('wp_enqueue_scripts')]
    public function conditionalScripts(): void
    {
        if (is_singular() && comments_open() && get_option('thread_comments')) {
            wp_enqueue_script('comment-reply');
        }
    }
}
```

## テンプレート管理

```php
namespace MyAwesomeTheme\Templates;

use WpPack\Component\Theme\Contracts\ThemeInterface;

class TemplateService
{
    public function __construct(
        private ThemeInterface $theme
    ) {}

    public function component(string $name, array $args = []): void
    {
        $path = $this->theme->getPath("template-parts/components/{$name}.php");
        if (file_exists($path)) {
            extract($args);
            include $path;
        }
    }

    public function partial(string $name, array $args = []): string
    {
        ob_start();
        $this->component($name, $args);
        return ob_get_clean();
    }
}
```

## ブロックテーマサポート

```php
namespace MyTheme\Blocks;

use WpPack\Component\Theme\Blocks\BlockManager;
use WpPack\Component\Theme\Blocks\Pattern;

class BlockService
{
    public function __construct(
        private BlockManager $blocks,
        private ThemeInterface $theme
    ) {}

    #[Action('init')]
    public function registerPatterns(): void
    {
        $this->blocks->registerPatternCategory('my-theme', [
            'label' => __('My Theme Patterns', 'my-theme')
        ]);

        $heroPattern = new Pattern('my-theme/hero-section');
        $heroPattern->setTitle(__('Hero Section', 'my-theme'));
        $heroPattern->setCategories(['my-theme', 'featured']);
        $heroPattern->setContent($this->getHeroPattern());
        $this->blocks->registerPattern($heroPattern);
    }
}
```

## 子テーマサポート

```php
namespace MyChildTheme;

use WpPack\Component\Theme\AbstractChildTheme;
use WpPack\Component\Theme\Attribute\Theme;
use WpPack\Component\Theme\Attribute\ParentTheme;

#[Theme(
    textDomain: 'my-child-theme',
    version: '1.0.0'
)]
#[ParentTheme('my-awesome-theme', minVersion: '1.0.0')]
class ChildTheme extends AbstractChildTheme
{
    protected function overrideParentServices(): array
    {
        return [
            CustomizerService::class => ChildCustomizerService::class,
        ];
    }
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

#### #[WpHeadAction] / #[WpFooterAction]

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

### パフォーマンス最適化

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
#[ParentTheme('parent-slug', minVersion: '1.0.0')]
```

## ヘルパー関数

```php
$theme = theme();
$theme->getUrl('assets/css/style.css');
$theme->getPath('templates/header.php');
$theme->getVersion();
```

## このコンポーネントの使用場面

**最適な用途：**
- カスタムテーマ開発
- プレミアムテーマの作成
- ブロックテーマ開発
- エンタープライズテーマ

**代替を検討すべき場合：**
- シンプルなブログテーマ
- クイックプロトタイプ

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress アクション/フィルターの登録

### 推奨
- **DependencyInjection コンポーネント** - サービスコンテナ
- **Config コンポーネント** - 設定管理
- **Filesystem コンポーネント** - テンプレート操作
