# Translation コンポーネント

**パッケージ:** `wppack/translation`
**名前空間:** `WPPack\Component\Translation\`
**Category:** Presentation

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

### After（WPPack）

```php
use WPPack\Component\Translation\Translator;
use WPPack\Component\Kernel\Attribute\TextDomain;

#[TextDomain(domain: 'my-plugin')]
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

## テキストドメインアトリビュート

テキストドメインの宣言には Kernel コンポーネントの `#[TextDomain]` アトリビュートを使用します。プラグインクラスに付与すると、Kernel が `boot()` の前に自動で `load_plugin_textdomain()` を呼び出します。

```php
use WPPack\Component\Kernel\Attribute\TextDomain;

#[TextDomain(domain: 'my-plugin')]
class PluginTranslator extends Translator {}
```

- `domain`: テキストドメイン名
- `path`: 言語ファイルのパス。デフォルトは `languages`

## Translator クラス

`Translator` は非 abstract クラスで、直接インスタンス化もサブクラス化も可能です。

### 直接使用

```php
$translator = new Translator('my-plugin');
$text = $translator->translate('Hello');
```

### サブクラスでの使用

```php
#[TextDomain(domain: 'my-shop')]
final class ShopTranslator extends Translator
{
    public function addToCart(): string
    {
        return $this->translate('Add to Cart');
    }
}
```

### translate() — __() のラッパー

```php
$text = $translator->translate('Hello');
```

### echo() — _e() のラッパー

```php
$translator->echo('Hello');
```

### plural() — _n() のラッパー

```php
$text = $translator->plural('One item', '%d items', $count);
```

### translateWithContext() — _x() のラッパー

```php
$text = $translator->translateWithContext('Post', 'verb');
```

### pluralWithContext() — _nx() のラッパー

```php
$text = $translator->pluralWithContext('One item', '%d items', $count, 'cart items');
```

### escHtml() — esc_html__() のラッパー

```php
$safe = $translator->escHtml('User input here');
```

### escAttr() — esc_attr__() のラッパー

```php
$safe = $translator->escAttr('Title text');
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/translation.md) を参照してください。

## 実践的な例

### プラグインの翻訳クラス

```php
<?php
declare(strict_types=1);

namespace MyPlugin\Translation;

use WPPack\Component\Translation\Translator;
use WPPack\Component\Kernel\Attribute\TextDomain;

#[TextDomain(domain: 'my-shop')]
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
use WPPack\Component\Hook\Attribute\InitAction;

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
- なし（PHP 8.2 のみ）

### オプション
- **Hook コンポーネント** — Named Hook アトリビュート使用時のみ必要
- **DependencyInjection コンポーネント** — Translator のインジェクション用
