# Shortcode コンポーネント

**パッケージ:** `wppack/shortcode`
**名前空間:** `WPPack\Component\Shortcode\`
**レイヤー:** Feature

Shortcode コンポーネントは、アトリビュートベースの設定、依存性注入サポートを備えた、モダンでオブジェクト指向な WordPress ショートコード開発アプローチを提供します。

## インストール

```bash
composer require wppack/shortcode
```

## このコンポーネントの機能

- **オブジェクト指向のショートコード開発** - クラスベースの定義による優れた構造化
- **アトリビュートベースの設定** - `#[AsShortcode]` によるメタデータ定義
- **依存性注入のサポート** - サービスやリポジトリの注入
- **Named Hook Attributes** - ショートコード関連フックの型安全なバインディング

## 基本コンセプト

### Before（従来の WordPress）

```php
add_shortcode('gallery', 'my_gallery_shortcode');

function my_gallery_shortcode($atts, $content = null) {
    $atts = shortcode_atts([
        'ids' => '',
        'columns' => 3,
        'size' => 'thumbnail',
    ], $atts);

    $ids = explode(',', $atts['ids']);
    $columns = intval($atts['columns']);

    $output = '<div class="gallery columns-' . $columns . '">';

    foreach ($ids as $id) {
        $image = wp_get_attachment_image($id, $atts['size']);
        $output .= '<div class="gallery-item">' . $image . '</div>';
    }

    $output .= '</div>';
    return $output;
}
```

### After（WPPack）

```php
use WPPack\Component\OptionsResolver\OptionsResolver;
use WPPack\Component\Shortcode\AbstractShortcode;
use WPPack\Component\Shortcode\Attribute\AsShortcode;

#[AsShortcode(name: 'gallery', description: 'Display an image gallery')]
class GalleryShortcode extends AbstractShortcode
{
    protected function configureAttributes(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'ids' => '',
            'columns' => '3',
            'size' => 'thumbnail',
        ]);
    }

    public function render(array $atts, string $content): string
    {
        $ids = array_filter(explode(',', $atts['ids']));
        $columns = max(1, (int) $atts['columns']);

        $output = '<div class="gallery columns-' . $columns . '">';

        foreach ($ids as $id) {
            $image = wp_get_attachment_image((int) $id, $atts['size']);
            $output .= '<div class="gallery-item">' . $image . '</div>';
        }

        $output .= '</div>';

        return $output;
    }
}
```

## クイックスタート

### 基本的なショートコード

`configureAttributes()` をオーバーライドして、デフォルト値・バリデーションを宣言的に定義します。`render()` には解決済みのアトリビュートが渡されます。内部で `shortcode_atts()` も呼ばれるため、`shortcode_atts_{shortcode}` フィルターとの互換性も維持されます。

```php
use WPPack\Component\OptionsResolver\OptionsResolver;
use WPPack\Component\Shortcode\AbstractShortcode;
use WPPack\Component\Shortcode\Attribute\AsShortcode;

#[AsShortcode(name: 'button', description: 'Styled button shortcode')]
class ButtonShortcode extends AbstractShortcode
{
    protected function configureAttributes(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'url' => '#',
            'style' => 'primary',
            'size' => 'medium',
            'new_tab' => 'false',
        ]);
        $resolver->setAllowedValues('style', ['primary', 'secondary', 'danger']);
        $resolver->setAllowedValues('size', ['small', 'medium', 'large']);
    }

    public function render(array $atts, string $content): string
    {
        $target = $atts['new_tab'] === 'true'
            ? ' target="_blank" rel="noopener noreferrer"'
            : '';

        return sprintf(
            '<a href="%s" class="btn btn-%s btn-%s"%s>%s</a>',
            esc_url($atts['url']),
            esc_attr($atts['style']),
            esc_attr($atts['size']),
            $target,
            esc_html($content),
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
use WPPack\Component\OptionsResolver\OptionsResolver;

#[AsShortcode(name: 'recent_posts', description: 'Display recent posts')]
class RecentPostsShortcode extends AbstractShortcode
{
    public function __construct(
        private PostRepository $postRepository,
    ) {}

    protected function configureAttributes(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'count' => '5',
            'category' => '',
        ]);
    }

    public function render(array $atts, string $content): string
    {
        $posts = $this->postRepository->getRecent(
            count: (int) $atts['count'],
            category: $atts['category'] ?: null,
        );

        $html = '<ul class="recent-posts">';
        foreach ($posts as $post) {
            $html .= sprintf(
                '<li><a href="%s">%s</a></li>',
                get_permalink($post),
                esc_html($post->post_title),
            );
        }
        $html .= '</ul>';

        return $html;
    }
}
```

## ショートコード登録

### ShortcodeRegistry

`ShortcodeRegistry` を使用してショートコードを WordPress に登録します。

```php
use WPPack\Component\Shortcode\ShortcodeRegistry;

$registry = new ShortcodeRegistry();

// DI 解決済みインスタンスを登録
$registry->register(new ButtonShortcode());
$registry->register(new RecentPostsShortcode($postRepository));

// ショートコードの登録解除
$registry->unregister('button');
```

### DI コンテナとの統合

```php
add_action('init', function () use ($container) {
    $registry = $container->get(ShortcodeRegistry::class);

    $registry->register($container->get(ButtonShortcode::class));
    $registry->register($container->get(RecentPostsShortcode::class));
    $registry->register($container->get(GalleryShortcode::class));
});
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/shortcode.md) を参照してください。

## このコンポーネントの使用場面

**最適な用途：**
- 再利用可能なコンポーネントを必要とするコンテンツリッチなサイト
- 柔軟なコンテンツブロックを使用したテーマ開発
- ユーザー向けコンテンツを含むプラグイン開発

**代替を検討すべき場合：**
- Gutenberg ブロックのみを対象とする新規プロジェクト
- 動的要素のないシンプルなコンテンツ

## プラグイン / テーマでの配置

プラグインやテーマでショートコードクラスを作成する場合、以下のディレクトリ構成を推奨します。

```
src/
└── Shortcode/
    ├── ButtonShortcode.php
    ├── GalleryShortcode.php
    └── MapShortcode.php
```

> 詳細は[プラグイン開発ガイド](../../guides/plugin-development.md)、[テーマ開発ガイド](../../guides/theme-development.md)を参照してください。

## 依存関係

### 必須
- **OptionsResolver コンポーネント** - アトリビュートの解決・型キャスト
