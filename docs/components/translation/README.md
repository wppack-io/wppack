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
use WpPack\Component\Translation\Attribute\PluginTextDomain;

#[PluginTextDomain(domain: 'my-plugin', path: 'my-plugin/languages')]
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

プラグインとテーマで異なるアトリビュートを使用します（WordPress API が異なるため）。

### PluginTextDomain — プラグイン用

```php
use WpPack\Component\Translation\Attribute\PluginTextDomain;

#[PluginTextDomain(domain: 'my-plugin', path: 'my-plugin/languages')]
class PluginTranslator extends Translator {}
```

- `path`: `WP_PLUGIN_DIR` からの相対パス。空の場合は `{domain}/languages` がデフォルト

### ThemeTextDomain — テーマ用

```php
use WpPack\Component\Translation\Attribute\ThemeTextDomain;

#[ThemeTextDomain(domain: 'my-theme', path: 'languages')]
class ThemeTranslator extends Translator {}
```

- `path`: テーマディレクトリからの相対パス。デフォルトは `languages`

内部的には以下の WordPress 関数を呼び出します：

- `load_plugin_textdomain($domain, false, $path)` — プラグイン用
- `load_theme_textdomain($domain, $path)` — テーマ用

## Translator クラス

`Translator` は非 abstract クラスで、直接インスタンス化もサブクラス化も可能です。

### 直接使用

```php
$translator = new Translator('my-plugin');
$text = $translator->translate('Hello');
```

### サブクラスでの使用

```php
#[PluginTextDomain(domain: 'my-shop', path: 'my-shop/languages')]
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

## TextDomainRegistry

テキストドメインの登録状態を管理するレジストリです。**Translator に限らず、任意のオブジェクト**を受け付けます。

### register() — アトリビュートからテキストドメインを登録

```php
$registry = new TextDomainRegistry();

// Translator サブクラス
$registry->register(new ShopTranslator());

// Translator 以外のオブジェクトも可
#[PluginTextDomain(domain: 'my-plugin', path: 'my-plugin/languages')]
class MyPlugin {}
$registry->register(new MyPlugin());
```

### static convenience メソッド — アトリビュート不要

```php
// プラグイン用
TextDomainRegistry::loadPlugin('my-plugin', 'my-plugin/languages');

// テーマ用
TextDomainRegistry::loadTheme('my-theme', 'languages');
```

### 状態確認

```php
$registry->has('my-plugin');          // bool
$registry->getRegisteredDomains();    // array<string, PluginTextDomain|ThemeTextDomain>
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/translation.md) を参照してください。

## 実践的な例

### プラグインの翻訳クラス

```php
<?php
declare(strict_types=1);

namespace MyPlugin\Translation;

use WpPack\Component\Translation\Translator;
use WpPack\Component\Translation\Attribute\PluginTextDomain;

#[PluginTextDomain(domain: 'my-shop', path: 'my-shop/languages')]
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
- なし（PHP 8.2 のみ）

### オプション
- **Hook コンポーネント** — Named Hook アトリビュート使用時のみ必要
- **DependencyInjection コンポーネント** — Translator のインジェクション用
