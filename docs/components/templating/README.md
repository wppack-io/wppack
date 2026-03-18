# Templating コンポーネント

**パッケージ:** `wppack/templating`
**名前空間:** `WpPack\Component\Templating\`
**レイヤー:** Infrastructure

Templating コンポーネントは、PHP テンプレートをレンダリングするためのエンジンです。レイアウト継承、セクション、自動エスケープ、パーシャルインクルードを提供し、Plates / Symfony PhpEngine のパターンに倣った設計です。将来の Twig 対応を念頭に、エンジン非依存のインターフェース（`TemplateRendererInterface`）で抽象化されています。

## インストール

```bash
composer require wppack/templating
```

## このコンポーネントの機能

- **PHP テンプレートエンジン** — `PhpRenderer` による `include` + `ob_start()` ベースのレンダリング
- **自動エスケープ** — テンプレート内で `\$view->e()` による安全な出力（`wppack/escaper` に委譲）
- **レイアウト継承** — Plates パターンの `\$view->layout()` / `\$view->section()` でテンプレート構造を定義
- **セクション定義** — `\$view->start()` / `\$view->stop()` でコンテンツブロックをキャプチャ
- **パーシャルインクルード** — `\$view->include()` で部品テンプレートを再利用
- **テンプレート検索** — `TemplateLocator` が WordPress テーマディレクトリ + カスタムパスを検索
- **WordPress 完全互換** — テンプレート内で `get_header()` / `the_content()` 等をそのまま呼べる
- **複数エンジン対応** — `ChainRenderer` で PhpRenderer + 将来の TwigRenderer を共存可能
- **DI 統合** — `TemplatingServiceProvider` で自動登録

## 基本コンセプト

### Before（従来の WordPress）

```php
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
```

### After（WpPack）

```php
use WpPack\Component\Templating\PhpRenderer;

$renderer = $container->get(PhpRenderer::class);

echo $renderer->render('content/single', [
    'title' => get_the_title(),
    'author' => get_the_author(),
    'date' => get_the_date(),
    'content' => get_the_content(),
]);
```

テンプレートファイル（`content/single.php`）:

```php
<?php \$view->layout('layouts/base'); ?>
<article>
    <h1><?= \$view->e($title) ?></h1>
    <div class="meta">
        By <?= \$view->e($author) ?> on <?= \$view->e($date) ?>
    </div>
    <div class="content"><?= \$view->raw($content) ?></div>
</article>
```

## クイックスタート

### PhpRenderer の基本使用法

```php
use WpPack\Component\Templating\PhpRenderer;

// テンプレート検索パスを指定してレンダラーを作成
$renderer = new PhpRenderer([
    get_template_directory() . '/templates',
]);

// テンプレートをレンダリング
$html = $renderer->render('partials/card', [
    'title' => 'Hello World',
    'body' => 'This is a card.',
]);

// 直接出力
$renderer->display('partials/card', ['title' => 'Hello']);

// テンプレートの存在チェック
if ($renderer->exists('partials/card')) {
    // ...
}
```

## テンプレート変数とエスケープ

テンプレート内では `$view` が `TemplateContext` インスタンスを参照します。コンテキスト配列のキーがそのまま変数として展開されます。

### エスケープ出力 `\$view->e()`

```php
<!-- HTML エスケープ（デフォルト） -->
<h1><?= \$view->e($title) ?></h1>

<!-- 属性エスケープ -->
<input value="<?= \$view->e($value, 'attr') ?>">

<!-- URL エスケープ -->
<a href="<?= \$view->e($url, 'url') ?>">Link</a>

<!-- JavaScript エスケープ -->
<script>var name = '<?= \$view->e($name, 'js') ?>';</script>
```

`\$view->e()` は `mixed` 型を受け付けます:

| 型 | 変換 |
|---|---|
| `null` | `''`（空文字列） |
| `string` | そのまま |
| `int` / `float` | `(string)` キャスト |
| `bool` | `true→'1'`, `false→''` |
| `Stringable` | `(string)` キャスト |
| `array` / 非Stringableオブジェクト | `RenderingException` |

### 非エスケープ出力 `\$view->raw()`

事前にエスケープ済み、または信頼済みコンテンツの出力に使用します:

```php
<div class="content"><?= \$view->raw($trustedHtml) ?></div>
```

## レイアウト継承

Plates パターンの `\$view->layout()` でレイアウトを宣言し、子テンプレートの出力を `content` セクションとして注入します。

### レイアウトテンプレート（`layouts/base.php`）

```php
<html>
<head>
    <title><?= \$view->e($title ?? 'My Site') ?></title>
</head>
<body>
    <?= \$view->section('content') ?>
</body>
</html>
```

### 子テンプレート（`pages/about.php`）

```php
<?php \$view->layout('layouts/base'); ?>
<article>
    <h1><?= \$view->e($title) ?></h1>
    <p><?= \$view->e($description) ?></p>
</article>
```

レンダリング結果:

```html
<html>
<head>
    <title>About Us</title>
</head>
<body>
    <article>
        <h1>About Us</h1>
        <p>We are a team of developers.</p>
    </article>
</body>
</html>
```

### ネストされたレイアウト

レイアウトはさらに別のレイアウトを継承できます:

```php
<!-- layouts/two-column.php -->
<?php \$view->layout('layouts/base'); ?>
<div class="main"><?= \$view->section('content') ?></div>
<div class="sidebar"><?= \$view->section('sidebar', 'Default sidebar') ?></div>
```

## セクション定義

`\$view->start()` / `\$view->stop()` で名前付きセクションをキャプチャし、レイアウトの任意の場所に挿入します。

```php
<?php \$view->layout('layouts/two-column'); ?>

<?php \$view->start('sidebar'); ?>
<nav>
    <ul>
        <li><a href="/">Home</a></li>
        <li><a href="/about">About</a></li>
    </ul>
</nav>
<?php \$view->stop(); ?>

<article>
    <h1><?= \$view->e($title) ?></h1>
    <p><?= \$view->e($content) ?></p>
</article>
```

セクションのデフォルト値:

```php
<!-- セクションが定義されていない場合のフォールバック -->
<?= \$view->section('sidebar', '<p>No sidebar content.</p>') ?>
```

## パーシャルインクルード

`\$view->include()` で別のテンプレートをインクルードします。インクルードされたテンプレートは独自の `TemplateContext` を持ち、渡されたコンテキスト変数のみアクセスできます。

```php
<!-- 記事一覧ページ -->
<div class="cards">
    <?php foreach ($posts as $post): ?>
        <?= \$view->include('partials/card', [
            'title' => $post->title,
            'body' => $post->excerpt,
        ]) ?>
    <?php endforeach; ?>
</div>
```

パーシャルテンプレート（`partials/card.php`）:

```php
<div class="card">
    <h3><?= \$view->e($title) ?></h3>
    <p><?= \$view->e($body) ?></p>
</div>
```

## テンプレート検索（TemplateLocator）

`TemplateLocator` はテンプレート名からファイルパスを解決します。エンジン非依存で、PhpRenderer でも将来の TwigRenderer でも再利用可能です。

### 検索順序

1. **WordPress `locate_template()`**（利用可能な場合） — 子テーマ → 親テーマ
2. **カスタムパス** — 登録順に検索

### テンプレート名の解決

| テンプレート名 | variant | 候補ファイル |
|---|---|---|
| `partials/card` | `''` | `partials/card.php` |
| `partials/card` | `'featured'` | `partials/card-featured.php` → `partials/card.php` |
| `layouts/base` | `''` | `layouts/base.php` |

```php
use WpPack\Component\Templating\TemplateLocator;

$locator = new TemplateLocator([
    get_template_directory() . '/templates',
    __DIR__ . '/fallback-templates',
]);

// ファイルパスを取得（見つからなければ null）
$file = $locator->locate('partials/card');
$file = $locator->locate('partials/card', 'featured'); // variant 付き

// パスの追加
$locator->addPath('/additional/path');
```

## TemplatePart（get_template_part ラッパー）

WordPress の `get_template_part()` をラップし、出力をキャプチャする機能を追加します。

```php
use WpPack\Component\Templating\TemplatePart;

// 直接出力（get_template_part() と同等）
TemplatePart::render('template-parts/content', 'post');

// データを渡す
TemplatePart::render('template-parts/content', 'post', [
    'show_thumbnail' => true,
]);

// 出力を文字列として取得
$html = TemplatePart::capture('template-parts/card', 'product', [
    'product' => $product,
]);
```

## WordPress 互換性

PhpRenderer は `include` + `ob_start()` を使用するため、テンプレート内で WordPress のテンプレート関数をそのまま呼べます。

```php
<?php get_header(); ?>

<?php if (have_posts()): while (have_posts()): the_post(); ?>
    <article>
        <h2><?= \$view->e(get_the_title()) ?></h2>
        <?php the_content(); ?>
    </article>
<?php endwhile; endif; ?>

<?php dynamic_sidebar('main-sidebar'); ?>
<?php get_footer(); ?>
```

### 使い分けガイド

| WordPress 関数 | WpPack 対応 | 備考 |
|---|---|---|
| `get_header()` / `get_footer()` | `\$view->layout()` | レイアウト継承で置換可能。直接呼び出しも可 |
| `get_template_part()` | `\$view->include()` | PhpRenderer パイプライン経由で `$view` 利用可能 |
| `get_sidebar()` / `dynamic_sidebar()` | そのまま呼び出し | output buffering でキャプチャ |
| `the_content()` / `the_title()` | そのまま呼び出し | 直接出力は ob でキャプチャ |
| `have_posts()` / `the_post()` | そのまま呼び出し | WordPress ループはそのまま |
| `wp_head()` / `wp_footer()` | そのまま呼び出し | レイアウト内で呼ぶ |

### 2 つのスタイルの共存

**スタイル A: WordPress ネイティブ**（既存テーマからの移行向け）

```php
<?php get_header(); ?>
<article><?= \$view->e($title) ?></article>
<?php get_footer(); ?>
```

**スタイル B: レイアウト継承**（モダン設計向け）

```php
<?php \$view->layout('layouts/base'); ?>
<article><?= \$view->e($title) ?></article>
```

どちらも PhpRenderer 内で動作します。段階的な移行が可能です。

## ChainRenderer（複数エンジン対応）

`ChainRenderer` は `supports()` メソッドで振り分けを行い、複数のテンプレートエンジンを統合します。

```php
use WpPack\Component\Templating\ChainRenderer;
use WpPack\Component\Templating\PhpRenderer;

$chain = new ChainRenderer([
    new PhpRenderer(['/templates']),
    // 将来: new TwigRenderer($twig),
]);

// supports() が true を返す最初のレンダラーに委譲
$html = $chain->render('pages/about', ['title' => 'About']);
```

現時点では `PhpRenderer` のみ。将来 `wppack/twig-templating` パッケージで `TwigRenderer` を追加する際に活用します。

## DI 統合

`TemplatingServiceProvider` で PhpRenderer を DI コンテナに登録します。

```php
use WpPack\Component\Templating\DependencyInjection\TemplatingServiceProvider;

$builder->addServiceProvider(new TemplatingServiceProvider(
    paths: [
        get_template_directory() . '/templates',
    ],
));

// コンテナから取得
$renderer = $container->get(PhpRenderer::class);

// インターフェース経由でも取得可能
$renderer = $container->get(TemplateRendererInterface::class);
```

### 登録されるサービス

| サービス | 説明 |
|---------|------|
| `Escaper` | 出力エスケープ（既に登録済みの場合はスキップ） |
| `TemplateLocator` | テンプレート検索（paths 引数付き） |
| `PhpRenderer` | PHP テンプレートエンジン（autowire） |
| `TemplateRendererInterface` | PhpRenderer へのエイリアス |

### 将来の Twig 拡張

Twig を追加する場合は `TwigTemplatingServiceProvider` が `TwigRenderer` を登録し、`ChainRenderer` でラップするか、`TemplateRendererInterface` のエイリアスを切り替えます。既存コードの変更は不要です。

## テスト

```php
use PHPUnit\Framework\TestCase;
use WpPack\Component\Templating\PhpRenderer;

final class TemplateTest extends TestCase
{
    private PhpRenderer $renderer;

    protected function setUp(): void
    {
        \$this->renderer = new PhpRenderer([
            __DIR__ . '/Fixtures/templates',
        ]);
    }

    public function testRendersTemplateWithData(): void
    {
        $html = \$this->renderer->render('simple', [
            'title' => 'Test Title',
        ]);

        self::assertStringContainsString('Test Title', $html);
    }

    public function testAutoEscapesOutput(): void
    {
        $html = \$this->renderer->render('simple', [
            'title' => '<script>alert("xss")</script>',
        ]);

        self::assertStringNotContainsString('<script>', $html);
    }

    public function testRendersWithLayout(): void
    {
        $html = \$this->renderer->render('with-layout', [
            'title' => 'Page Title',
        ]);

        self::assertStringContainsString('<html><body>', $html);
        self::assertStringContainsString('Page Title', $html);
    }
}
```

## アーキテクチャ

```
TemplateRendererInterface         ← エンジン非依存の契約
├── PhpRenderer                   ← PHP テンプレートエンジン
│   ├── TemplateLocator           ← テンプレート検索（locate_template + カスタムパス）
│   ├── TemplateContext           ← テンプレート内の $view（e, raw, layout, section, include）
│   └── Escaper                   ← 出力エスケープ（wppack/escaper）
├── ChainRenderer                 ← 複数エンジンへのデリゲート
└── [将来] TwigRenderer           ← Twig ブリッジ（wppack/twig-templating）

TemplatePart                      ← WordPress get_template_part() ラッパー
```

### レンダリングフロー

```
PhpRenderer::render()
  ↓ TemplateLocator::locate() でファイルパス解決
  ↓ TemplateContext 生成（Escaper + PhpRenderer 注入）
  ↓ $view 変数として TemplateContext を渡す
  ↓ extract() でコンテキスト変数展開
  ↓ ob_start() → include $file → ob_get_clean()
  ↓ layoutTemplate がセットされていればレイアウトを再帰レンダリング
  ↓ 結果返却
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/templating.md) を参照してください。
## 例外

| 例外 | 説明 |
|-----|------|
| `TemplateNotFoundException` | テンプレートファイルが見つからない |
| `RenderingException` | レンダリング中のエラー（循環レイアウト検出、型変換エラー等） |

## 依存関係

### 必須
- **wppack/escaper** — 出力エスケープ

### 推奨
- **wppack/dependency-injection** — DI コンテナ統合
- **wppack/hook** — Named Hook 属性（dev 依存）
