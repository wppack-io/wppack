# Templating Component

**Package:** `wppack/templating`
**Namespace:** `WpPack\Component\Templating\`
**Layer:** Infrastructure

WordPress のテンプレート関数 `get_template_part()` / `locate_template()` をモダンな PHP でラップするコンポーネントです。型安全なデータの受け渡し、自動出力エスケープ、テンプレートの検索と解決、再利用可能なコンポーネント、WordPress テーマとの統合を提供します。

## インストール

```bash
composer require wppack/templating
```

## 基本コンセプト

### 従来の WordPress と WpPack の比較

```php
// 従来の WordPress - 手動エスケープ、型安全でないデータ渡し
<?php get_header(); ?>
<div class="content">
    <h1><?php echo esc_html(get_the_title()); ?></h1>
    <div class="meta">
        By <?php echo esc_html(get_the_author()); ?>
        on <?php echo esc_html(get_the_date()); ?>
    </div>
    <div class="content">
        <?php echo wp_kses_post(get_the_content()); ?>
    </div>
</div>
<?php get_footer(); ?>

// WpPack Templating - 型安全、自動エスケープ
use WpPack\Component\Templating\TemplateRenderer;

$renderer = $container->get(TemplateRenderer::class);

echo $renderer->render('content/single', [
    'title' => get_the_title(),
    'author' => get_the_author(),
    'date' => get_the_date(),
    'content' => get_the_content(),
]);
```

## TemplateRenderer クラス

テンプレートレンダリングのメインエントリーポイントです。内部で `locate_template()` を使用してテンプレートファイルを検索し、PHP テンプレートとしてレンダリングします：

```php
use WpPack\Component\Templating\TemplateRenderer;

final class TemplateService
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {}

    public function render(string $template, array $data = []): string
    {
        return $this->renderer->render($template, $data);
    }

    public function display(string $template, array $data = []): void
    {
        $this->renderer->display($template, $data);
    }
}
```

## テンプレートパーツの利用

`get_template_part()` のモダンなラッパーとして、型安全なデータの受け渡しをサポートします：

```php
use WpPack\Component\Templating\TemplatePart;

// 基本的な使い方（get_template_part() と同等）
TemplatePart::render('template-parts/content', 'post');

// データを渡す（WordPress 5.5+ の $args に対応）
TemplatePart::render('template-parts/content', 'post', [
    'show_thumbnail' => true,
    'excerpt_length' => 150,
]);

// レンダリング結果を文字列として取得
$html = TemplatePart::capture('template-parts/card', 'product', [
    'product' => $product,
    'show_price' => true,
]);
```

## テンプレートの検索と解決

`locate_template()` をラップし、テンプレートの検索順序をカスタマイズできます：

```php
use WpPack\Component\Templating\TemplateLocator;

final class TemplateLocator
{
    public function __construct(
        private readonly array $paths = [],
    ) {}

    /**
     * テンプレートファイルを検索する。
     * 子テーマ → 親テーマ → カスタムパスの順に検索。
     */
    public function locate(string $template, string $variant = ''): ?string
    {
        $templates = [];

        if ($variant !== '') {
            $templates[] = "{$template}-{$variant}.php";
        }
        $templates[] = "{$template}.php";

        // locate_template() による検索
        $found = locate_template($templates);

        if ($found !== '') {
            return $found;
        }

        // カスタムパスから検索
        foreach ($this->paths as $path) {
            foreach ($templates as $tmpl) {
                $file = $path . '/' . $tmpl;
                if (file_exists($file)) {
                    return $file;
                }
            }
        }

        return null;
    }
}
```

### テーマ統合

```php
use WpPack\Component\Templating\WordPress\ThemeTemplating;

// functions.php での設定
add_action('after_setup_theme', function () {
    ThemeTemplating::enable([
        'views_path' => get_template_directory() . '/resources/views',
        'debug' => WP_DEBUG,
    ]);
});
```

## 自動出力エスケープ

テンプレート内の変数出力を自動的にエスケープし、セキュリティを確保します：

```php
use WpPack\Component\Templating\EscapingEngine;

$escaper = new EscapingEngine();

// HTML エスケープ（デフォルト）
echo $escaper->escape($value);                    // esc_html() 相当
echo $escaper->escape($value, 'html');             // esc_html()

// 属性エスケープ
echo $escaper->escape($value, 'attr');             // esc_attr()

// URL エスケープ
echo $escaper->escape($value, 'url');              // esc_url()

// JavaScript エスケープ
echo $escaper->escape($value, 'js');               // esc_js()

// テンプレート内での自動エスケープ
// resources/views/template-parts/card.php
```

```php
<?php
/** @var WpPack\Component\Templating\TemplateContext $this */
?>
<div class="card">
    <h3 class="card-title"><?= $this->e($title) ?></h3>
    <p class="card-excerpt"><?= $this->e($excerpt) ?></p>
    <a href="<?= $this->e($link, 'url') ?>" class="card-link">
        <?= $this->e($linkText) ?>
    </a>
    <!-- HTML コンテンツはエスケープなしで出力（明示的に指定） -->
    <div class="card-content"><?= $this->raw($content) ?></div>
</div>
```

## 再利用可能なコンポーネント

### コンポーネントの定義

```php
use WpPack\Component\Templating\Component;

final class CardComponent extends Component
{
    public function __construct(
        public readonly string $title,
        public readonly string $excerpt = '',
        public readonly ?string $imageUrl = null,
        public readonly ?string $linkUrl = null,
        public readonly string $linkText = 'Read More',
        public readonly string $cssClass = '',
    ) {}

    public function templatePath(): string
    {
        return 'template-parts/components/card';
    }
}
```

### コンポーネントの使用

```php
use WpPack\Component\Templating\ComponentRenderer;

$componentRenderer = $container->get(ComponentRenderer::class);

// コンポーネントをレンダリング
echo $componentRenderer->render(new CardComponent(
    title: get_the_title(),
    excerpt: get_the_excerpt(),
    imageUrl: get_the_post_thumbnail_url(null, 'medium'),
    linkUrl: get_permalink(),
    cssClass: 'post-card',
));
```

### コンポーネントテンプレート

`template-parts/components/card.php`:

```php
<?php
/** @var WpPack\Component\Templating\TemplateContext $this */
/** @var CardComponent $component */
?>
<div class="card <?= $this->e($component->cssClass) ?>">
    <?php if ($component->imageUrl): ?>
        <div class="card-image">
            <img src="<?= $this->e($component->imageUrl, 'url') ?>"
                 alt="<?= $this->e($component->title, 'attr') ?>"
                 loading="lazy">
        </div>
    <?php endif; ?>

    <div class="card-content">
        <h3 class="card-title"><?= $this->e($component->title) ?></h3>

        <?php if ($component->excerpt): ?>
            <p class="card-excerpt"><?= $this->e($component->excerpt) ?></p>
        <?php endif; ?>

        <?php if ($component->linkUrl): ?>
            <a href="<?= $this->e($component->linkUrl, 'url') ?>" class="card-link">
                <?= $this->e($component->linkText) ?>
            </a>
        <?php endif; ?>
    </div>
</div>
```

## WordPress ループ統合

```php
<?php
/** @var WpPack\Component\Templating\TemplateContext $this */
?>
<div class="posts-container">
    <?php if (have_posts()): ?>
        <?php while (have_posts()): the_post(); ?>
            <article <?php post_class(); ?>>
                <h2 class="entry-title">
                    <a href="<?= esc_url(get_permalink()) ?>">
                        <?= esc_html(get_the_title()) ?>
                    </a>
                </h2>

                <div class="entry-meta">
                    <span class="posted-on"><?= esc_html(get_the_date()) ?></span>
                    <span class="byline">by <?= esc_html(get_the_author()) ?></span>
                </div>

                <div class="entry-content">
                    <?php the_excerpt(); ?>
                </div>

                <?php if (has_post_thumbnail()): ?>
                    <div class="thumbnail">
                        <?php the_post_thumbnail('medium'); ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endwhile; ?>

        <?= paginate_links() ?>
    <?php else: ?>
        <p>投稿が見つかりませんでした。</p>
    <?php endif; ?>
</div>
```

## カスタムテンプレート関数

テンプレート内で使用できるヘルパー関数を登録します：

```php
final class TemplateHelpers
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {}

    #[Action('init', priority: 10)]
    public function onInit(): void
    {
        $this->renderer->registerHelper('asset', [$this, 'assetUrl']);
        $this->renderer->registerHelper('route', [$this, 'generateRoute']);
    }

    public function assetUrl(string $path): string
    {
        return get_template_directory_uri() . '/assets/' . ltrim($path, '/');
    }
}
```

テンプレート内での使用：

```php
<?php /** @var WpPack\Component\Templating\TemplateContext $this */ ?>
<img src="<?= $this->helper('asset', 'images/logo.png') ?>" alt="Logo">
```

## テスト

```php
use PHPUnit\Framework\TestCase;
use WpPack\Component\Templating\TemplateRenderer;

class TemplateRenderingTest extends TestCase
{
    private TemplateRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TemplateRenderer(
            paths: [__DIR__ . '/fixtures/templates'],
        );
    }

    public function testRendersTemplateWithData(): void
    {
        $html = $this->renderer->render('simple', [
            'title' => 'Test Title',
            'content' => 'Test Content',
        ]);

        $this->assertStringContainsString('Test Title', $html);
        $this->assertStringContainsString('Test Content', $html);
    }

    public function testAutoEscapesOutput(): void
    {
        $html = $this->renderer->render('simple', [
            'title' => '<script>alert("xss")</script>',
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testLocatesTemplateVariant(): void
    {
        $html = $this->renderer->render('content', 'page', [
            'title' => 'Page Title',
        ]);

        $this->assertStringContainsString('Page Title', $html);
    }
}
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `TemplateRenderer` | テンプレートレンダリングエンジン |
| `TemplatePart` | `get_template_part()` のモダンラッパー |
| `TemplateLocator` | テンプレートファイルの検索・解決 |
| `TemplateContext` | テンプレート内のコンテキスト（エスケープヘルパー付き） |
| `EscapingEngine` | 出力エスケープエンジン |
| `Component` | 再利用可能なテンプレートコンポーネント基底クラス |
| `ComponentRenderer` | コンポーネントのレンダリング |
| `WordPress\ThemeTemplating` | WordPress テーマ統合 |
