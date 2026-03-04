# OptionsResolver コンポーネント

**パッケージ:** `wppack/options-resolver`
**名前空間:** `WpPack\Component\OptionsResolver\`
**レイヤー:** Abstraction

OptionsResolver コンポーネントは、Symfony OptionsResolver を WordPress 向けに拡張したコンポーネントです。`setAllowedTypes()` で単一の型を指定した場合に文字列からの自動キャストを行い、WordPress のショートコード属性のようにすべての値が文字列で渡される場面で型安全なオプション解決を提供します。

## インストール

```bash
composer require wppack/options-resolver
```

## このコンポーネントの機能

- **Symfony OptionsResolver 互換** - Symfony の全機能がそのまま利用可能
- **型指定による自動キャスト** - `setAllowedTypes()` で `'int'`、`'float'`、`'bool'` を指定すると自動変換
- **Shortcode コンポーネントとの統合** - `configureAttributes()` で宣言的なアトリビュート定義

## 基本コンセプト

### Before（従来の WordPress）

```php
// ショートコード内での手動処理
function my_shortcode($atts) {
    $atts = shortcode_atts([
        'count' => 5,
        'enabled' => 'true',
    ], $atts);

    // すべて string で渡される → 手動キャスト
    $count = (int) $atts['count'];
    $enabled = $atts['enabled'] === 'true';

    // バリデーションなし
}
```

### After（WpPack）

```php
use WpPack\Component\OptionsResolver\OptionsResolver;

$resolver = new OptionsResolver();
$resolver->setDefaults([
    'count' => 5,
    'enabled' => false,
    'style' => 'primary',
]);
$resolver->setAllowedTypes('count', 'int');     // string → int 自動キャスト
$resolver->setAllowedTypes('enabled', 'bool');  // string → bool 自動キャスト
$resolver->setAllowedValues('style', ['primary', 'secondary', 'danger']);

$resolved = $resolver->resolve(['count' => '10', 'enabled' => 'yes', 'style' => 'danger']);
// ['count' => 10, 'enabled' => true, 'style' => 'danger']
```

## 基本的な使い方

### デフォルト値とバリデーション

Symfony OptionsResolver のすべての機能がそのまま利用可能です。

```php
use WpPack\Component\OptionsResolver\OptionsResolver;

$resolver = new OptionsResolver();

// デフォルト値
$resolver->setDefaults([
    'title' => '',
    'style' => 'primary',
    'max_items' => '10',
]);

// 必須オプション
$resolver->setRequired('title');

// 許可値
$resolver->setAllowedValues('style', ['primary', 'secondary', 'danger']);

// カスタム normalizer
$resolver->addNormalizer('title', static fn($resolver, $value) => trim($value));

$resolved = $resolver->resolve(['title' => ' Hello ', 'style' => 'danger']);
// ['title' => 'Hello', 'style' => 'danger', 'max_items' => '10']
```

### 型指定による自動キャスト

`setAllowedTypes()` で単一の castable な型を指定すると、文字列からの自動キャスト normalizer が自動登録されます。

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

$resolved = $resolver->resolve([
    'count' => '10',
    'ratio' => '3.14',
    'enabled' => 'yes',
]);
// ['count' => 10, 'ratio' => 3.14, 'enabled' => true]
```

#### 自動キャストルール

| 指定型 | 変換 |
|-------|------|
| `'int'` | `(int)` キャスト |
| `'float'` | `(float)` キャスト |
| `'bool'` | `'true'`/`'1'`/`'yes'`（大文字小文字不問） → `true`、それ以外 → `false` |

#### 自動キャストが行われない場合

- 配列で複数型を指定した場合: `setAllowedTypes('count', ['int', 'string'])`
- `'string'` を指定した場合: `setAllowedTypes('title', 'string')`
- 値が既にターゲット型の場合: `int` 値が渡されたらそのまま

### ユーザー定義 normalizer との共存

自動キャスト normalizer はユーザー定義の normalizer より先に実行されます。

```php
$resolver = new OptionsResolver();
$resolver->setDefaults(['count' => 0]);
$resolver->setAllowedTypes('count', 'int');

// ユーザー normalizer: 自動キャスト後の値を受け取る
$resolver->addNormalizer('count', static fn($resolver, $value) => max(1, $value));

$resolved = $resolver->resolve(['count' => '0']);
// 自動キャスト: '0' → 0、ユーザー normalizer: max(1, 0) → 1
// ['count' => 1]
```

## Shortcode コンポーネントとの統合

`wppack/shortcode` コンポーネントの `configureAttributes()` メソッドで宣言的にアトリビュートを定義できます。

```php
use WpPack\Component\OptionsResolver\OptionsResolver;
use WpPack\Component\Shortcode\AbstractShortcode;
use WpPack\Component\Shortcode\Attribute\AsShortcode;

#[AsShortcode(name: 'recent_posts', description: 'Display recent posts')]
class RecentPostsShortcode extends AbstractShortcode
{
    protected function configureAttributes(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'count' => 5,
            'category' => '',
        ]);
        $resolver->setAllowedTypes('count', 'int');
    }

    public function render(array $atts, string $content): string
    {
        // $atts['count'] は int 型で渡される
        $posts = get_posts(['numberposts' => $atts['count']]);
        // ...
    }
}
```

詳細は [Shortcode ドキュメント](../shortcode/) を参照してください。

## 内部動作

`setAllowedTypes()` で単一の castable な型が指定されると、以下の処理が行われます:

1. `parent::setAllowedTypes()` に `['string', $type]` を渡す（WordPress からの string 入力を許可）
2. `addNormalizer()` で型キャスト normalizer を登録

Symfony OptionsResolver の実行順序は「型バリデーション → normalizer」なので、`'string'` も許可型に含めることで string 入力がバリデーションを通過し、normalizer で目的の型にキャストされます。

## 主要クラス

| クラス | 説明 |
|-------|------|
| `OptionsResolver` | Symfony OptionsResolver を拡張した WordPress 向け OptionsResolver |

## 依存関係

### 必須
- **symfony/options-resolver** `^7.0 \|\| ^8.0` - Symfony OptionsResolver
