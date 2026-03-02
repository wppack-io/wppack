# Translation コンポーネント

**パッケージ:** `wppack/translation`
**名前空間:** `WpPack\Component\Translation\`
**レイヤー:** Application

WordPress の国際化（i18n）関数を型安全かつオブジェクト指向でラップするコンポーネントです。

## インストール

```bash
composer require wppack/translation
```

## 基本コンセプト

### Before（従来の WordPress）

```php
add_action('init', function() {
    load_plugin_textdomain('my-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

echo __('Welcome to My Plugin', 'my-plugin');
echo sprintf(__('Hello, %s', 'my-plugin'), $name);
echo sprintf(
    _n('%d comment', '%d comments', $count, 'my-plugin'),
    $count
);
echo _x('Post', 'verb', 'my-plugin');
```

### After（WpPack）

```php
use WpPack\Component\Translation\Translator;
use WpPack\Component\Translation\Attribute\TextDomain;

#[TextDomain(
    domain: 'my-plugin',
    path: 'languages'
)]
class MyPluginTranslator extends Translator
{
    public function welcome(): string
    {
        return $this->translate('Welcome to My Plugin');
    }

    public function hello(string $name): string
    {
        return sprintf($this->translate('Hello, %s'), $name);
    }

    public function commentCount(int $count): string
    {
        return sprintf(
            $this->plural('%d comment', '%d comments', $count),
            $count
        );
    }

    public function postVerb(): string
    {
        return $this->translateWithContext('Post', 'verb');
    }
}
```

## テキストドメインの登録

`#[TextDomain]` アトリビュートで `load_plugin_textdomain()` / `load_theme_textdomain()` を自動化します。

```php
// プラグイン用
#[TextDomain(domain: 'my-plugin', path: 'languages')]
class PluginTranslator extends Translator {}

// テーマ用
#[TextDomain(domain: 'my-theme', path: 'languages', type: 'theme')]
class ThemeTranslator extends Translator {}
```

内部的には以下の WordPress 関数を呼び出します：

- `load_plugin_textdomain($domain, false, $path)` -- プラグイン用
- `load_theme_textdomain($domain, $path)` -- テーマ用

## Translator クラス

`Translator` は WordPress の翻訳関数をラップしたメソッドを提供します。

### translate() -- __() のラッパー

```php
// WordPress: __('Hello', 'my-plugin')
$text = $translator->translate('Hello');
```

### echo() -- _e() のラッパー

```php
// WordPress: _e('Hello', 'my-plugin')
$translator->echo('Hello');
```

### plural() -- _n() のラッパー

```php
// WordPress: _n('One item', '%d items', $count, 'my-plugin')
$text = $translator->plural('One item', '%d items', $count);

// printf 形式のプレースホルダーを使用
echo sprintf($translator->plural('%d item', '%d items', $count), $count);
```

### translateWithContext() -- _x() のラッパー

```php
// WordPress: _x('Post', 'verb', 'my-plugin')
$text = $translator->translateWithContext('Post', 'verb');

// WordPress: _x('Post', 'noun', 'my-plugin')
$text = $translator->translateWithContext('Post', 'noun');
```

### pluralWithContext() -- _nx() のラッパー

```php
// WordPress: _nx('One item', '%d items', $count, 'cart items', 'my-plugin')
$text = $translator->pluralWithContext('One item', '%d items', $count, 'cart items');
```

### escHtml() -- esc_html__() のラッパー

```php
// WordPress: esc_html__('User input here', 'my-plugin')
$safe = $translator->escHtml('User input here');
```

### escAttr() -- esc_attr__() のラッパー

```php
// WordPress: esc_attr__('Title text', 'my-plugin')
$safe = $translator->escAttr('Title text');
```

## 実践的な例

### プラグインの翻訳クラス

```php
<?php
declare(strict_types=1);

namespace MyPlugin\Translation;

use WpPack\Component\Translation\Translator;
use WpPack\Component\Translation\Attribute\TextDomain;

#[TextDomain(domain: 'my-shop', path: 'languages')]
final class ShopTranslator extends Translator
{
    public function productCount(int $count): string
    {
        return sprintf(
            $this->plural('%d product', '%d products', $count),
            number_format_i18n($count)
        );
    }

    public function price(float $amount): string
    {
        return sprintf($this->translate('Price: %s'), number_format_i18n($amount, 2));
    }

    public function addToCart(): string
    {
        return $this->translate('Add to Cart');
    }

    public function outOfStock(): string
    {
        return $this->translate('Out of Stock');
    }

    public function orderStatus(string $status): string
    {
        return match ($status) {
            'pending' => $this->translate('Pending'),
            'processing' => $this->translate('Processing'),
            'completed' => $this->translate('Completed'),
            'cancelled' => $this->translate('Cancelled'),
            default => $this->translate('Unknown'),
        };
    }
}
```

### テンプレートでの使用

```php
<?php
$t = $container->get(ShopTranslator::class);
?>
<div class="product">
    <h2><?php echo esc_html($t->translate('Featured Products')); ?></h2>
    <p><?php echo esc_html($t->productCount($total)); ?></p>

    <button type="button">
        <?php echo esc_html($t->addToCart()); ?>
    </button>
</div>
```

### Named Hook アトリビュートとの併用

```php
use WpPack\Component\Hook\Attribute\InitAction;

class ShopAdmin
{
    public function __construct(
        private ShopTranslator $translator,
    ) {}

    #[InitAction]
    public function addAdminNotice(): void
    {
        $message = $this->translator->translate('Shop settings updated.');
        // ...
    }
}
```

## WordPress 関数の対応表

| Translator メソッド | WordPress 関数 |
|---|---|
| `translate()` | `__()` |
| `echo()` | `_e()` |
| `plural()` | `_n()` |
| `translateWithContext()` | `_x()` |
| `pluralWithContext()` | `_nx()` |
| `escHtml()` | `esc_html__()` |
| `escAttr()` | `esc_attr__()` |

## このコンポーネントの使用場面

**最適な用途：**
- テキストドメインの自動読み込み
- 翻訳呼び出しのクラスへの集約
- DI コンテナとの統合

**代替を検討すべき場合：**
- シンプルなプラグインで翻訳が少数の場合（WordPress 関数を直接使用）

## 依存関係

### 必須
- **Hook コンポーネント** - テキストドメイン読み込みのフック登録用

### 推奨
- **DependencyInjection コンポーネント** - Translator のインジェクション用
