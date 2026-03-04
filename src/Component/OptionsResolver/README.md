# WpPack OptionsResolver

WordPress 向け OptionsResolver。Symfony OptionsResolver を拡張し、`setAllowedTypes()` で単一の型（`'int'`、`'float'`、`'bool'`）を指定した場合に文字列からの自動キャストを行います。

## インストール

```bash
composer require wppack/options-resolver
```

## 使い方

### 基本

Symfony OptionsResolver のすべての機能がそのまま利用可能です。

```php
use WpPack\Component\OptionsResolver\OptionsResolver;

$resolver = new OptionsResolver();
$resolver->setDefaults([
    'title' => '',
    'style' => 'primary',
]);
$resolver->setAllowedValues('style', ['primary', 'secondary', 'danger']);

$resolved = $resolver->resolve(['title' => 'Hello', 'style' => 'danger']);
// ['title' => 'Hello', 'style' => 'danger']
```

### 型指定による自動キャスト

`setAllowedTypes()` で単一の型を指定すると、文字列からの自動キャスト normalizer が登録されます。WordPress の shortcode 属性のように、すべての値が文字列で渡される場面で有用です。

```php
$resolver = new OptionsResolver();
$resolver->setDefaults([
    'count' => 5,
    'ratio' => 1.0,
    'enabled' => false,
]);
$resolver->setAllowedTypes('count', 'int');     // '10' → 10
$resolver->setAllowedTypes('ratio', 'float');   // '3.14' → 3.14
$resolver->setAllowedTypes('enabled', 'bool');  // 'true' → true

$resolved = $resolver->resolve(['count' => '10', 'ratio' => '3.14', 'enabled' => 'yes']);
// ['count' => 10, 'ratio' => 3.14, 'enabled' => true]
```

| 指定型 | 変換 |
|-------|------|
| `'int'` | `(int)` キャスト |
| `'float'` | `(float)` キャスト |
| `'bool'` | `'true'`/`'1'`/`'yes'` → `true`、それ以外 → `false` |

配列で複数型を指定した場合（`['int', 'string']`）は自動キャストされません。

## Shortcode コンポーネントとの統合

`wppack/shortcode` コンポーネントと組み合わせて使用する場合は、`configureAttributes()` メソッドで宣言的にアトリビュートを定義できます。詳細は [Shortcode ドキュメント](../../../docs/components/shortcode/) を参照してください。

## ライセンス

MIT
