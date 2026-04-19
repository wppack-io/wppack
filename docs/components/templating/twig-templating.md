# TwigTemplating ブリッジ

**パッケージ:** `wppack/twig-templating`
**名前空間:** `WPPack\Component\Templating\Bridge\Twig\`
**レイヤー:** Infrastructure

Twig テンプレートエンジンを WPPack Templating コンポーネントに統合するブリッジパッケージです。`TemplateRendererInterface` を実装し、`ChainRenderer` 経由で `PhpRenderer` と共存できます。

## インストール

```bash
composer require wppack/twig-templating
```

## 基本使用法

### TwigRenderer

```php
use WPPack\Component\Templating\Bridge\Twig\TwigEnvironmentFactory;
use WPPack\Component\Templating\Bridge\Twig\TwigRenderer;
use WPPack\Component\Templating\Bridge\Twig\Extension\WordPressExtension;

// ファクトリで Twig\Environment を生成
$factory = new TwigEnvironmentFactory(
    paths: [get_template_directory() . '/templates'],
    extensions: [new WordPressExtension()],
);

$renderer = new TwigRenderer($factory->create());

// テンプレートをレンダリング（.html.twig 拡張子は自動付与）
$html = $renderer->render('pages/about', ['title' => 'About Us']);

// 存在チェック
if ($renderer->exists('pages/about')) {
    // ...
}
```

### テンプレート名の解決

| テンプレート名 | 解決後 |
|---|---|
| `pages/about` | `pages/about.html.twig` |
| `pages/about.twig` | `pages/about.twig`（そのまま） |
| `pages/about.html.twig` | `pages/about.html.twig`（そのまま） |

### PhpRenderer との共存

`ChainRenderer` を使って PHP テンプレートと Twig テンプレートを共存させることができます:

```php
use WPPack\Component\Templating\ChainRenderer;
use WPPack\Component\Templating\PhpRenderer;

$chain = new ChainRenderer([
    new PhpRenderer(['/templates']),
    $twigRenderer,
]);

// .php ファイルがあれば PhpRenderer、なければ TwigRenderer に委譲
$html = $chain->render('pages/about', ['title' => 'About']);
```

## TwigEnvironmentFactory

`Twig\Environment` を適切な設定で生成するファクトリです。

```php
use WPPack\Component\Templating\Bridge\Twig\TwigEnvironmentFactory;

$factory = new TwigEnvironmentFactory(
    paths: ['/custom/templates'],
    options: [
        'debug' => true,
        'cache' => '/tmp/twig-cache',
    ],
    extensions: [new WordPressExtension()],
);

$twig = $factory->create();
```

### デフォルトオプション

| オプション | デフォルト値 | 説明 |
|---|---|---|
| `autoescape` | `'html'` | 自動エスケープ戦略 |
| `strict_variables` | `true` | 未定義変数で例外を投げる |

### テンプレート検索パス

WordPress 環境では、以下の順序でテンプレートを検索します:

1. 子テーマディレクトリ（`get_stylesheet_directory()`）
2. 親テーマディレクトリ（`get_template_directory()`）
3. `$paths` で指定したカスタムパス

## WordPressExtension

WordPress のエスケープ関数やテンプレート関数を Twig フィルタ/関数として提供します。

### フィルタ

| フィルタ | 委譲先 | 用例 |
|---------|--------|------|
| `esc_html` | `Escaper::html()` | `{{ title\|esc_html }}` |
| `esc_attr` | `Escaper::attr()` | `{{ value\|esc_attr }}` |
| `esc_url` | `Escaper::url()` | `{{ url\|esc_url }}` |
| `esc_js` | `Escaper::js()` | `{{ name\|esc_js }}` |
| `wp_kses_post` | `wp_kses_post()` | `{{ content\|wp_kses_post }}` |

`Escaper` が注入されている場合はそちらに委譲し、未注入時は WordPress 関数に直接委譲します。

### 関数

| 関数 | WordPress 関数 | 用例 |
|------|---------------|------|
| `wp_head` | `wp_head()` | `{{ wp_head() }}` |
| `wp_footer` | `wp_footer()` | `{{ wp_footer() }}` |
| `body_class` | `body_class()` | `{{ body_class() }}` |
| `language_attributes` | `language_attributes()` | `{{ language_attributes() }}` |

### テンプレート例

```twig
<!DOCTYPE html>
<html {{ language_attributes() }}>
<head>
    {{ wp_head() }}
</head>
<body {{ body_class() }}>
    {% block content %}{% endblock %}
    {{ wp_footer() }}
</body>
</html>
```

## DI 統合

### TwigTemplatingServiceProvider

```php
use WPPack\Component\Templating\Bridge\Twig\DependencyInjection\TwigTemplatingServiceProvider;
use WPPack\Component\Templating\DependencyInjection\TemplatingServiceProvider;

// PhpRenderer + TwigRenderer（ChainRenderer で統合）
$builder->addServiceProvider(new TemplatingServiceProvider(
    paths: [get_template_directory() . '/templates'],
));
$builder->addServiceProvider(new TwigTemplatingServiceProvider(
    paths: [get_template_directory() . '/templates'],
));

// TwigRenderer 単体
$builder->addServiceProvider(new TwigTemplatingServiceProvider(
    paths: [get_template_directory() . '/templates'],
));
```

### 登録されるサービス

| サービス | 説明 |
|---------|------|
| `WordPressExtension` | WordPress フィルタ/関数 Twig 拡張 |
| `TwigEnvironmentFactory` | `Twig\Environment` ファクトリ |
| `Twig\Environment` | Twig 環境（factory 経由で生成） |
| `TwigRenderer` | Twig テンプレートレンダラー |
| `ChainRenderer` | PhpRenderer + TwigRenderer 統合（PhpRenderer 登録済みの場合） |
| `TemplateRendererInterface` | ChainRenderer or TwigRenderer へのエイリアス |

### 動作パターン

| TemplatingServiceProvider | TwigTemplatingServiceProvider | TemplateRendererInterface |
|---|---|---|
| 登録済み | 登録済み | → `ChainRenderer`（PhpRenderer + TwigRenderer） |
| 未登録 | 登録済み | → `TwigRenderer` |
| 登録済み | `useChainRenderer: false` | → `TwigRenderer` |

## 例外マッピング

| Twig 例外 | WPPack 例外 |
|-----------|------------|
| `Twig\Error\LoaderError` | `TemplateNotFoundException` |
| `Twig\Error\RuntimeError` | `RenderingException` |
| `Twig\Error\SyntaxError` | `RenderingException` |

## 依存関係

### 必須
- **wppack/templating** ^1.0 — テンプレートレンダラーインターフェース
- **twig/twig** ^3.0 — Twig テンプレートエンジン

### 推奨
- **wppack/escaper** — WordPress エスケープ関数ラッパー（WordPressExtension で利用）
- **wppack/dependency-injection** — DI コンテナ統合
