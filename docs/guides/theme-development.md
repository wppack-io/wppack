# テーマ開発ガイド

WpPack を使って WordPress テーマを一から開発するための実践ガイドです。クラシックテーマと FSE（ブロックテーマ）の両方式について、Kernel・DependencyInjection・Hook を組み合わせた開発方法を解説します。

## 使用するコンポーネント

| コンポーネント | 役割 |
|--------------|------|
| [Kernel](../components/kernel/README.md) | アプリケーションブートストラップ、ライフサイクル管理 |
| [DependencyInjection](../components/dependency-injection/README.md) | サービスコンテナ、自動検出 |
| [Hook](../components/hook/README.md) | アトリビュートベースのアクション/フィルター登録 |
| [Theme](../components/theme/) | テーマ関連の Named Hook アトリビュート |

## 共通パート

クラシックテーマと FSE テーマの両方に共通する基盤部分を解説します。

### functions.php でのブートストラップ

`functions.php` で Composer オートロードの読み込みと Kernel への登録を行います。

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use MyTheme\MyTheme;
use WpPack\Component\Kernel\Kernel;

Kernel::registerTheme(new MyTheme());
```

`Kernel::registerTheme()` は `Kernel::registerPlugin()` と同様に、初回呼び出し時に Kernel インスタンスを自動生成し、`init` フック（priority 0）で `boot()` をスケジュールします。

> [!NOTE]
> プラグインが常にテーマより先に登録・ブートされます。テーマはプラグインが登録したサービスをオーバーライドしたり拡張したりできます。

### ThemeInterface の実装

`ThemeInterface` は `ServiceProviderInterface` を拡張し、テーマのライフサイクルを定義します。プラグインとの主な違いは `onActivate()` / `onDeactivate()` がない点です。

```php
<?php

declare(strict_types=1);

namespace MyTheme;

use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\ThemeInterface;

final class MyTheme implements ThemeInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->loadConfig(__DIR__ . '/../config/services.php');
    }

    public function getCompilerPasses(): array
    {
        return [
            new RegisterHookSubscribersPass(),
        ];
    }

    public function boot(Container $container): void
    {
        // テーマサポートの宣言
        add_theme_support('post-thumbnails');
        add_theme_support('title-tag');
        add_theme_support('html5', [
            'search-form', 'comment-form', 'comment-list', 'gallery', 'caption',
        ]);
        add_theme_support('custom-logo');
        add_theme_support('automatic-feed-links');

        // ナビゲーションメニューの登録
        register_nav_menus([
            'primary' => 'Primary Navigation',
            'footer' => 'Footer Navigation',
        ]);

        // コンテンツ幅の設定
        if (!isset($GLOBALS['content_width'])) {
            $GLOBALS['content_width'] = 1200;
        }
    }
}
```

### 各メソッドの役割

| メソッド | フェーズ | 説明 |
|---------|---------|------|
| `register()` | 登録 | ContainerBuilder にサービス・パラメータを登録 |
| `getCompilerPasses()` | コンパイル | コンパイラーパスを返す |
| `boot()` | ブート | `add_theme_support()`、`register_nav_menus()` 等のセットアップ |

`boot()` はコンテナが確定した後に呼ばれるため、コンテナからサービスを取得して利用することも可能です。

### PluginInterface との違い

| 項目 | PluginInterface | ThemeInterface |
|------|----------------|---------------|
| `register()` | あり | あり |
| `getCompilerPasses()` | あり | あり |
| `boot()` | あり | あり |
| `onActivate()` | あり | **なし** |
| `onDeactivate()` | あり | **なし** |

テーマの切り替えイベントに応じた処理が必要な場合は、`#[AfterSetupThemeAction]` 等のフックを使用します。

### サービス登録・フック登録・設定管理

プラグインと同じパターンで `#[AsHookSubscriber]`、`#[Env]`、`#[Option]`、`#[Constant]` を使用します。サービスはディレクトリスキャンにより自動登録されるため、個別のアトリビュート指定は不要です。

→ 詳細: [プラグイン開発ガイド](plugin-development.md) のサービス登録・フック登録・設定管理セクション

---

## クラシックテーマ

PHP テンプレートファイルと WordPress テンプレート階層を使用する従来型のテーマです。

### ディレクトリ構成

```
my-classic-theme/
├── style.css              # テーマヘッダー（必須）
├── functions.php          # エントリーポイント
├── composer.json
├── config/
│   └── services.php       # サービス設定
├── src/
│   ├── MyTheme.php        # ThemeInterface 実装
│   ├── Service/
│   └── Hook/
│       ├── AssetLoader.php
│       ├── ThemeCustomizer.php
│       └── WidgetAreas.php
├── index.php              # フォールバックテンプレート（必須）
├── single.php
├── page.php
├── archive.php
├── header.php
├── footer.php
├── sidebar.php
├── search.php
├── 404.php
└── assets/
    ├── css/
    └── js/
```

`style.css` のテーマヘッダー：

```css
/*
Theme Name: My Classic Theme
Description: A WpPack-powered classic theme.
Version: 1.0.0
Requires PHP: 8.2
Text Domain: my-classic-theme
*/
```

### テーマセットアップ

`boot()` で WordPress テーマ機能を宣言します。

```php
public function boot(Container $container): void
{
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('custom-logo', [
        'height' => 100,
        'width' => 400,
        'flex-height' => true,
        'flex-width' => true,
    ]);
    add_theme_support('html5', [
        'search-form', 'comment-form', 'comment-list', 'gallery', 'caption',
    ]);
    add_theme_support('automatic-feed-links');
    add_theme_support('custom-background', [
        'default-color' => 'ffffff',
    ]);

    register_nav_menus([
        'primary' => __('Primary Navigation', 'my-classic-theme'),
        'footer' => __('Footer Navigation', 'my-classic-theme'),
    ]);
}
```

### テーマ関連フック

Theme コンポーネントの Named Hook アトリビュートを使って、テーマ固有のフックを宣言的に扱います。

#### アセット読み込み

```php
<?php

declare(strict_types=1);

namespace MyTheme\Hook;

use WpPack\Component\Hook\Attribute\AsHookSubscriber;
use WpPack\Component\Theme\Attribute\Action\WpEnqueueScriptsAction;

#[AsHookSubscriber]
final class AssetLoader
{
    #[WpEnqueueScriptsAction]
    public function enqueueAssets(): void
    {
        $version = wp_get_theme()->get('Version');

        wp_enqueue_style(
            'my-theme-style',
            get_stylesheet_uri(),
            [],
            $version,
        );

        wp_enqueue_style(
            'my-theme-main',
            get_template_directory_uri() . '/assets/css/main.css',
            ['my-theme-style'],
            $version,
        );

        wp_enqueue_script(
            'my-theme-script',
            get_template_directory_uri() . '/assets/js/theme.js',
            [],
            $version,
            true,
        );
    }
}
```

#### テンプレート出力フック

```php
<?php

declare(strict_types=1);

namespace MyTheme\Hook;

use WpPack\Component\Hook\Attribute\AsHookSubscriber;
use WpPack\Component\Theme\Attribute\Action\WpHeadAction;
use WpPack\Component\Theme\Attribute\Action\WpFooterAction;
use WpPack\Component\Theme\Attribute\Filter\BodyClassFilter;

#[AsHookSubscriber]
final class ThemeOutput
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

    #[BodyClassFilter]
    public function addBodyClasses(array $classes): array
    {
        $classes[] = 'scheme-' . get_theme_mod('color_scheme', 'light');

        if (is_singular()) {
            $classes[] = 'singular-' . get_post_type();
        }

        return $classes;
    }
}
```

#### カスタマイザー

```php
<?php

declare(strict_types=1);

namespace MyTheme\Hook;

use WpPack\Component\Hook\Attribute\AsHookSubscriber;
use WpPack\Component\Theme\Attribute\Action\CustomizeRegisterAction;
use WpPack\Component\Theme\Attribute\Action\CustomizePreviewInitAction;

#[AsHookSubscriber]
final class ThemeCustomizer
{
    #[CustomizeRegisterAction]
    public function register(\WP_Customize_Manager $wp_customize): void
    {
        // セクション追加
        $wp_customize->add_section('my_theme_options', [
            'title' => __('Theme Options', 'my-classic-theme'),
            'priority' => 130,
        ]);

        // カラースキーム設定
        $wp_customize->add_setting('color_scheme', [
            'default' => 'light',
            'sanitize_callback' => 'sanitize_text_field',
            'transport' => 'postMessage',
        ]);

        $wp_customize->add_control('color_scheme', [
            'label' => __('Color Scheme', 'my-classic-theme'),
            'section' => 'my_theme_options',
            'type' => 'select',
            'choices' => [
                'light' => __('Light', 'my-classic-theme'),
                'dark' => __('Dark', 'my-classic-theme'),
            ],
        ]);
    }

    #[CustomizePreviewInitAction]
    public function enqueuePreviewScript(): void
    {
        wp_enqueue_script(
            'my-theme-customizer-preview',
            get_template_directory_uri() . '/assets/js/customizer-preview.js',
            ['customize-preview'],
            wp_get_theme()->get('Version'),
            true,
        );
    }
}
```

#### ウィジェットエリア

```php
<?php

declare(strict_types=1);

namespace MyTheme\Hook;

use WpPack\Component\Hook\Attribute\AsHookSubscriber;
use WpPack\Component\Hook\Attribute\Action;

#[AsHookSubscriber]
final class WidgetAreas
{
    #[Action('widgets_init')]
    public function registerSidebars(): void
    {
        register_sidebar([
            'name' => __('Primary Sidebar', 'my-classic-theme'),
            'id' => 'primary-sidebar',
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget' => '</section>',
            'before_title' => '<h3 class="widget-title">',
            'after_title' => '</h3>',
        ]);

        register_sidebar([
            'name' => __('Footer Widgets', 'my-classic-theme'),
            'id' => 'footer-widgets',
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h4 class="widget-title">',
            'after_title' => '</h4>',
        ]);
    }
}
```

→ 詳細: [Theme コンポーネント](../components/theme/)

### 実践例：カスタマイザー対応クラシックテーマ

以下のファイル構成で、カラースキームの切り替えとウィジェットエリアを備えたクラシックテーマを構築できます。

```
my-classic-theme/
├── style.css
├── functions.php
├── composer.json
├── config/
│   └── services.php             # サービス設定
├── src/
│   ├── MyTheme.php              # ThemeInterface 実装（上記参照）
│   └── Hook/
│       ├── AssetLoader.php      # アセット読み込み
│       ├── ThemeOutput.php      # head/footer 出力、body_class フィルター
│       ├── ThemeCustomizer.php  # カスタマイザー設定
│       └── WidgetAreas.php      # サイドバー登録
├── index.php
├── header.php
├── footer.php
├── sidebar.php
└── assets/
    ├── css/
    │   └── main.css
    └── js/
        ├── theme.js
        └── customizer-preview.js
```

`functions.php` で `Kernel::registerTheme()` を呼ぶだけで、すべてのフックサブスクライバーが自動検出・登録されます。

---

## FSE（ブロックテーマ）

WordPress 5.9 以降で利用可能な Full Site Editing（FSE）方式のテーマです。PHP テンプレートの代わりに HTML テンプレートと `theme.json` を使用します。

### ディレクトリ構成

```
my-block-theme/
├── style.css              # テーマヘッダー（必須）
├── functions.php          # エントリーポイント
├── composer.json
├── theme.json             # グローバルスタイル・設定
├── config/
│   └── services.php       # サービス設定
├── src/
│   ├── MyTheme.php        # ThemeInterface 実装
│   ├── Service/
│   └── Hook/
│       ├── BlockAssets.php
│       ├── BlockPatterns.php
│       └── BlockStyles.php
├── templates/             # ブロックテンプレート（HTML）
│   ├── index.html
│   ├── single.html
│   ├── page.html
│   ├── archive.html
│   ├── search.html
│   └── 404.html
├── parts/                 # テンプレートパーツ（HTML）
│   ├── header.html
│   ├── footer.html
│   └── sidebar.html
├── patterns/              # ブロックパターン（PHP）
│   └── hero.php
└── assets/
    ├── css/
    └── js/
```

### theme.json

`theme.json` でグローバルスタイルと設定を宣言的に定義します。カスタマイザーの代わりにサイトエディタで編集可能になります。

```json
{
    "$schema": "https://schemas.wp.org/trunk/theme.json",
    "version": 3,
    "settings": {
        "color": {
            "palette": [
                {
                    "slug": "primary",
                    "color": "#0073aa",
                    "name": "Primary"
                },
                {
                    "slug": "secondary",
                    "color": "#23282d",
                    "name": "Secondary"
                },
                {
                    "slug": "background",
                    "color": "#ffffff",
                    "name": "Background"
                },
                {
                    "slug": "foreground",
                    "color": "#333333",
                    "name": "Foreground"
                }
            ]
        },
        "typography": {
            "fontSizes": [
                {
                    "slug": "small",
                    "size": "0.875rem",
                    "name": "Small"
                },
                {
                    "slug": "medium",
                    "size": "1rem",
                    "name": "Medium"
                },
                {
                    "slug": "large",
                    "size": "1.5rem",
                    "name": "Large"
                }
            ]
        },
        "layout": {
            "contentSize": "800px",
            "wideSize": "1200px"
        },
        "appearanceTools": true
    },
    "styles": {
        "color": {
            "background": "var(--wp--preset--color--background)",
            "text": "var(--wp--preset--color--foreground)"
        },
        "typography": {
            "fontSize": "var(--wp--preset--font-size--medium)",
            "lineHeight": "1.6"
        },
        "elements": {
            "link": {
                "color": {
                    "text": "var(--wp--preset--color--primary)"
                }
            }
        }
    },
    "templateParts": [
        {
            "name": "header",
            "title": "Header",
            "area": "header"
        },
        {
            "name": "footer",
            "title": "Footer",
            "area": "footer"
        }
    ]
}
```

### テーマセットアップ（FSE 固有）

```php
public function boot(Container $container): void
{
    // ブロックテーマ固有のサポート
    add_theme_support('wp-block-styles');
    add_theme_support('editor-styles');
    add_editor_style('assets/css/editor.css');

    // title-tag はブロックテーマでは自動的にサポートされるが、明示しても可
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
}
```

### ブロックパターン登録

```php
<?php

declare(strict_types=1);

namespace MyTheme\Hook;

use WpPack\Component\Hook\Attribute\Action\InitAction;
use WpPack\Component\Hook\Attribute\AsHookSubscriber;

#[AsHookSubscriber]
final class BlockPatterns
{
    #[InitAction]
    public function registerPatterns(): void
    {
        register_block_pattern_category('my-theme', [
            'label' => __('My Theme', 'my-block-theme'),
        ]);

        register_block_pattern('my-theme/hero', [
            'title' => __('Hero Section', 'my-block-theme'),
            'categories' => ['my-theme', 'featured'],
            'content' => $this->getPatternContent('hero'),
        ]);
    }

    private function getPatternContent(string $name): string
    {
        ob_start();
        require get_template_directory() . '/patterns/' . $name . '.php';

        return (string) ob_get_clean();
    }
}
```

`patterns/hero.php` の例：

```php
<?php
/**
 * Title: Hero Section
 * Slug: my-theme/hero
 * Categories: my-theme, featured
 */
?>
<!-- wp:cover {"overlayColor":"primary","align":"full"} -->
<div class="wp-block-cover alignfull">
    <span class="wp-block-cover__background has-primary-background-color"></span>
    <div class="wp-block-cover__inner-container">
        <!-- wp:heading {"textAlign":"center","level":1} -->
        <h1 class="has-text-align-center"><?php esc_html_e('Welcome to My Site', 'my-block-theme'); ?></h1>
        <!-- /wp:heading -->
        <!-- wp:paragraph {"align":"center"} -->
        <p class="has-text-align-center"><?php esc_html_e('A beautiful site built with WpPack.', 'my-block-theme'); ?></p>
        <!-- /wp:paragraph -->
    </div>
</div>
<!-- /wp:cover -->
```

### ブロックスタイルバリエーション

```php
<?php

declare(strict_types=1);

namespace MyTheme\Hook;

use WpPack\Component\Hook\Attribute\Action\InitAction;
use WpPack\Component\Hook\Attribute\AsHookSubscriber;

#[AsHookSubscriber]
final class BlockStyles
{
    #[InitAction]
    public function registerStyles(): void
    {
        register_block_style('core/button', [
            'name' => 'outline-primary',
            'label' => __('Outline Primary', 'my-block-theme'),
        ]);

        register_block_style('core/group', [
            'name' => 'card',
            'label' => __('Card', 'my-block-theme'),
        ]);

        register_block_style('core/image', [
            'name' => 'rounded-shadow',
            'label' => __('Rounded with Shadow', 'my-block-theme'),
        ]);
    }
}
```

### Block コンポーネントとの連携

WpPack の [Block コンポーネント](../components/block.md) が提供する Named Hook アトリビュートを使って、ブロックエディタ固有のアセットを管理できます。

```php
<?php

declare(strict_types=1);

namespace MyTheme\Hook;

use WpPack\Component\Block\Attribute\Action\EnqueueBlockEditorAssetsAction;
use WpPack\Component\Block\Attribute\Action\EnqueueBlockAssetsAction;
use WpPack\Component\Hook\Attribute\AsHookSubscriber;

#[AsHookSubscriber]
final class BlockAssets
{
    #[EnqueueBlockEditorAssetsAction]
    public function enqueueEditorAssets(): void
    {
        // エディタ専用のスクリプトとスタイル
        wp_enqueue_script(
            'my-theme-editor',
            get_template_directory_uri() . '/assets/js/editor.js',
            ['wp-blocks', 'wp-dom-ready', 'wp-edit-post'],
            wp_get_theme()->get('Version'),
            true,
        );

        wp_enqueue_style(
            'my-theme-editor-style',
            get_template_directory_uri() . '/assets/css/editor.css',
            ['wp-edit-blocks'],
            wp_get_theme()->get('Version'),
        );
    }

    #[EnqueueBlockAssetsAction]
    public function enqueueBlockAssets(): void
    {
        // フロントエンドとエディタの両方で使用するアセット
        wp_enqueue_style(
            'my-theme-blocks',
            get_template_directory_uri() . '/assets/css/blocks.css',
            [],
            wp_get_theme()->get('Version'),
        );
    }
}
```

### HTML テンプレートの例

`templates/index.html`:

```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
    <!-- wp:query {"queryId":1,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true}} -->
    <div class="wp-block-query">
        <!-- wp:post-template -->
            <!-- wp:post-title {"isLink":true} /-->
            <!-- wp:post-date /-->
            <!-- wp:post-excerpt /-->
        <!-- /wp:post-template -->

        <!-- wp:query-pagination -->
            <!-- wp:query-pagination-previous /-->
            <!-- wp:query-pagination-numbers /-->
            <!-- wp:query-pagination-next /-->
        <!-- /wp:query-pagination -->
    </div>
    <!-- /wp:query -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```

### クラシックテーマとの違い

| 項目 | クラシックテーマ | FSE（ブロックテーマ） |
|------|---------------|---------------------|
| テンプレートファイル | PHP（`single.php` 等） | HTML（`templates/single.html`） |
| テンプレートパーツ | `get_header()` / `get_footer()` | `parts/header.html` / `parts/footer.html` |
| スタイル設定 | カスタマイザー + CSS | `theme.json` + サイトエディタ |
| カラーパレット | `add_theme_support('editor-color-palette', ...)` | `theme.json` の `settings.color.palette` |
| レイアウト | PHP + CSS | `theme.json` の `settings.layout` |
| ウィジェット | `register_sidebar()` + ウィジェット画面 | ブロックウィジェット / テンプレートパーツ |
| メニュー | `register_nav_menus()` + メニュー画面 | ナビゲーションブロック |
| WpPack 共通 | `ThemeInterface`、`#[AsHookSubscriber]`、`ContainerConfigurator` | 同じ |

### 実践例：ブロックパターン付き FSE テーマ

```
my-block-theme/
├── style.css
├── functions.php
├── composer.json
├── theme.json                    # グローバルスタイル・設定
├── config/
│   └── services.php              # サービス設定
├── src/
│   ├── MyTheme.php               # ThemeInterface 実装
│   └── Hook/
│       ├── BlockAssets.php        # エディタ・フロントエンドアセット
│       ├── BlockPatterns.php      # パターン登録
│       └── BlockStyles.php       # スタイルバリエーション登録
├── templates/
│   ├── index.html
│   ├── single.html
│   ├── page.html
│   └── 404.html
├── parts/
│   ├── header.html
│   └── footer.html
├── patterns/
│   └── hero.php
└── assets/
    ├── css/
    │   ├── blocks.css
    │   └── editor.css
    └── js/
        └── editor.js
```

`functions.php` と ThemeInterface 実装はクラシックテーマと同じパターンです。違いは `boot()` で FSE 固有のサポートを宣言する点と、フックサブスクライバーがブロック関連の処理を行う点です。

---

## テーマの選択指針

### クラシックテーマが適している場合

- PHP でテンプレートロジックを細かく制御したい
- カスタマイザーを使った設定 UI が必要
- 既存のクラシックテーマから移行する
- ウィジェットやサイドバーを多用する
- プログラマティックなテンプレート制御が重要

### FSE（ブロックテーマ）が適している場合

- サイトエディタでの視覚的な編集を重視する
- `theme.json` によるデザイントークンの一元管理が必要
- ブロックパターンでレイアウトを提供したい
- PHP テンプレートのメンテナンスコストを減らしたい
- WordPress の最新機能を活用したい

### 共通のメリット

どちらの方式でも、WpPack の以下の機能を同じように利用できます：

- **ThemeInterface** によるライフサイクル管理
- **ContainerConfigurator** / **services.php** によるサービスの自動検出
- **#[AsHookSubscriber]** によるフックの宣言的登録
- **#[Env]** / **#[Option]** / **#[Constant]** による型安全な設定管理
- **RegisterHookSubscribersPass** によるコンパイラーパスの自動処理
