# Shortcode コンポーネント

**パッケージ:** `wppack/shortcode`
**名前空間:** `WpPack\Component\Shortcode\`
**レイヤー:** Feature

Shortcode コンポーネントは、アトリビュートベースの設定、依存性注入、拡張されたショートコード処理を備えた、モダンでオブジェクト指向な WordPress ショートコード開発アプローチを提供します。

## インストール

```bash
composer require wppack/shortcode
```

## このコンポーネントの機能

- **オブジェクト指向のショートコード開発** - クラスベースの定義による優れた構造化
- **型安全なショートコード属性** - 自動バリデーションと型変換
- **依存性注入のサポート** - サービスやリポジトリの注入
- **テンプレートベースのレンダリング** - 再利用可能なビューコンポーネントとレイアウト
- **ネストされたショートコードの処理** - 複雑なコンテンツ構造とコンポジション
- **キャッシュとパフォーマンス最適化** - ページ読み込み時間の改善
- **セキュリティ強化** - 自動入力サニタイズとバリデーション

## 従来の WordPress vs WpPack

### Before（従来の WordPress）

```php
add_shortcode('gallery', 'my_gallery_shortcode');

function my_gallery_shortcode($atts, $content = null) {
    $atts = shortcode_atts([
        'ids' => '',
        'columns' => 3,
        'size' => 'thumbnail',
        'link' => 'file',
    ], $atts);

    // Manual validation
    $ids = explode(',', $atts['ids']);
    $columns = intval($atts['columns']);
    if ($columns < 1) $columns = 3;

    // Manual HTML construction
    $output = '<div class="gallery columns-' . $columns . '">';

    foreach ($ids as $id) {
        $attachment = get_post($id);
        if ($attachment) {
            $image = wp_get_attachment_image($id, $atts['size']);
            $output .= '<div class="gallery-item">' . $image . '</div>';
        }
    }

    $output .= '</div>';
    return $output;
}
```

### After（WpPack）

```php
use WpPack\Component\Shortcode\AbstractShortcode;
use WpPack\Component\Shortcode\Attribute\Shortcode;
use WpPack\Component\Shortcode\Attribute\ShortcodeAttr;

#[Shortcode('gallery', description: 'Display an image gallery')]
class GalleryShortcode extends AbstractShortcode
{
    #[ShortcodeAttr(type: 'array', required: true, description: 'Comma-separated image IDs')]
    private array $ids;

    #[ShortcodeAttr(type: 'integer', default: 3, min: 1, max: 6)]
    private int $columns;

    #[ShortcodeAttr(type: 'string', default: 'thumbnail', enum: ['thumbnail', 'medium', 'large', 'full'])]
    private string $size;

    #[ShortcodeAttr(type: 'string', default: 'file', enum: ['file', 'attachment', 'none'])]
    private string $link;

    public function render(): string
    {
        return $this->view('gallery', [
            'images' => $this->getImages(),
            'columns' => $this->columns,
            'link' => $this->link,
        ]);
    }

    private function getImages(): array
    {
        return array_filter(array_map(
            fn ($id) => wp_get_attachment_image($id, $this->size),
            $this->ids
        ));
    }
}
```

## クイックスタート

### 基本的なショートコード

```php
use WpPack\Component\Shortcode\AbstractShortcode;
use WpPack\Component\Shortcode\Attribute\Shortcode;
use WpPack\Component\Shortcode\Attribute\ShortcodeAttr;

#[Shortcode('button', description: 'Styled button shortcode')]
class ButtonShortcode extends AbstractShortcode
{
    #[ShortcodeAttr(type: 'string', required: true)]
    private string $url;

    #[ShortcodeAttr(type: 'string', default: 'primary', enum: ['primary', 'secondary', 'outline'])]
    private string $style;

    #[ShortcodeAttr(type: 'string', default: 'medium', enum: ['small', 'medium', 'large'])]
    private string $size;

    #[ShortcodeAttr(type: 'boolean', default: false)]
    private bool $newTab;

    public function render(): string
    {
        $target = $this->newTab ? ' target="_blank" rel="noopener noreferrer"' : '';

        return sprintf(
            '<a href="%s" class="btn btn-%s btn-%s"%s>%s</a>',
            esc_url($this->url),
            esc_attr($this->style),
            esc_attr($this->size),
            $target,
            esc_html($this->content)
        );
    }
}
```

使用例：
```
[button url="https://example.com" style="primary" size="large" new_tab="true"]Click Me[/button]
```

### 依存性注入を使用したショートコード

```php
#[Shortcode('recent_posts', description: 'Display recent posts')]
class RecentPostsShortcode extends AbstractShortcode
{
    public function __construct(
        private PostRepository $postRepository,
        private CacheManager $cache
    ) {}

    #[ShortcodeAttr(type: 'integer', default: 5, min: 1, max: 20)]
    private int $count;

    #[ShortcodeAttr(type: 'string', default: '')]
    private string $category;

    #[ShortcodeAttr(type: 'string', default: 'grid', enum: ['grid', 'list', 'carousel'])]
    private string $layout;

    #[ShortcodeAttr(type: 'boolean', default: true)]
    private bool $showExcerpt;

    #[ShortcodeAttr(type: 'boolean', default: true)]
    private bool $showThumbnail;

    public function render(): string
    {
        $cacheKey = $this->getCacheKey();

        return $this->cache->get($cacheKey, function () {
            $posts = $this->postRepository->getRecent(
                count: $this->count,
                category: $this->category ?: null
            );

            return $this->view("recent-posts/{$this->layout}", [
                'posts' => $posts,
                'showExcerpt' => $this->showExcerpt,
                'showThumbnail' => $this->showThumbnail,
            ]);
        });
    }

    private function getCacheKey(): string
    {
        return 'shortcode_recent_posts_' . md5(serialize([
            $this->count,
            $this->category,
            $this->layout,
        ]));
    }
}
```

### ネストされたショートコード

```php
#[Shortcode('tabs', description: 'Tabbed content container')]
class TabsShortcode extends AbstractShortcode
{
    public function render(): string
    {
        $tabs = $this->parseNestedShortcodes('tab');

        $navHtml = '';
        $contentHtml = '';

        foreach ($tabs as $index => $tab) {
            $active = $index === 0 ? 'active' : '';
            $id = 'tab-' . sanitize_title($tab['title']);

            $navHtml .= sprintf(
                '<li class="tab-nav-item %s" data-tab="%s">%s</li>',
                $active,
                esc_attr($id),
                esc_html($tab['title'])
            );

            $contentHtml .= sprintf(
                '<div class="tab-content %s" id="%s">%s</div>',
                $active,
                esc_attr($id),
                $tab['content']
            );
        }

        return sprintf(
            '<div class="tabs-container"><ul class="tab-nav">%s</ul><div class="tab-panels">%s</div></div>',
            $navHtml,
            $contentHtml
        );
    }
}

#[Shortcode('tab', description: 'Single tab within tabs')]
class TabShortcode extends AbstractShortcode
{
    #[ShortcodeAttr(type: 'string', required: true)]
    private string $title;

    public function render(): string
    {
        return do_shortcode($this->content);
    }
}
```

使用例：
```
[tabs]
    [tab title="Overview"]Overview content here[/tab]
    [tab title="Details"]Details content here[/tab]
    [tab title="Reviews"]Reviews content here[/tab]
[/tabs]
```

### 料金表ショートコード

```php
#[Shortcode('pricing', description: 'Pricing table')]
class PricingShortcode extends AbstractShortcode
{
    #[ShortcodeAttr(type: 'string', required: true)]
    private string $name;

    #[ShortcodeAttr(type: 'number', required: true)]
    private float $price;

    #[ShortcodeAttr(type: 'string', default: 'month')]
    private string $period;

    #[ShortcodeAttr(type: 'string')]
    private string $url;

    #[ShortcodeAttr(type: 'boolean', default: false)]
    private bool $featured;

    #[ShortcodeAttr(type: 'string', default: 'Get Started')]
    private string $buttonText;

    public function render(): string
    {
        $featuredClass = $this->featured ? 'pricing-featured' : '';

        return $this->view('pricing-card', [
            'name' => $this->name,
            'price' => number_format($this->price, 2),
            'period' => $this->period,
            'features' => $this->content ? explode("\n", trim($this->content)) : [],
            'url' => $this->url,
            'buttonText' => $this->buttonText,
            'featured' => $this->featured,
        ]);
    }
}
```

## ショートコード登録

```php
add_action('init', function () {
    $container = new WpPack\Container();
    $container->register([
        GalleryShortcode::class,
        ButtonShortcode::class,
        RecentPostsShortcode::class,
        TabsShortcode::class,
        TabShortcode::class,
        PricingShortcode::class,
    ]);
});
```

## このコンポーネントの使用場面

**最適な用途：**
- 再利用可能なコンポーネントを必要とするコンテンツリッチなサイト
- 柔軟なコンテンツブロックを使用したテーマ開発
- ユーザー向けコンテンツを含むプラグイン開発
- ショートコードから Gutenberg へ移行するサイト

**代替を検討すべき場合：**
- Gutenberg ブロックのみを対象とする新規プロジェクト
- 動的要素のないシンプルなコンテンツ

## 依存関係

### 必須
- **Hook コンポーネント** - ショートコード登録用

### 推奨
- **Cache コンポーネント** - ショートコード出力のキャッシュ用
- **DependencyInjection コンポーネント** - サービス注入用
- **Templating コンポーネント** - テンプレートベースのレンダリング用
