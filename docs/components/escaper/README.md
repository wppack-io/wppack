# Escaper コンポーネント

**パッケージ:** `wppack/escaper`
**名前空間:** `WPPack\Component\Escaper\`
**レイヤー:** Abstraction

WordPress の出力エスケープ関数を型安全にラップし、エスケープ関連の WordPress フックを Named Hook アトリビュートとして提供するコンポーネントです。

出力のエスケープを担当します。入力のサニタイズについては [Sanitizer コンポーネント](../sanitizer/README.md) を参照してください。

## インストール

```bash
composer require wppack/escaper
```

## 基本コンセプト

WordPress のセキュリティ原則 "sanitize input, escape output" に基づき、出力時のエスケープ処理を提供します。

### Before（従来の WordPress）

```php
// 個別のエスケープ関数を直接呼び出し
echo '<p>' . esc_html($title) . '</p>';
echo '<input value="' . esc_attr($value) . '">';
echo '<a href="' . esc_url($url) . '">Link</a>';
echo '<script>var name = "' . esc_js($name) . '";</script>';
```

### After（WPPack）

```php
use WPPack\Component\Escaper\Escaper;

$escaper = $container->get(Escaper::class);

echo '<p>' . $escaper->html($title) . '</p>';
echo '<input value="' . $escaper->attr($value) . '">';
echo '<a href="' . $escaper->url($url) . '">Link</a>';
echo '<script>var name = "' . $escaper->js($name) . '";</script>';
```

## Escaper クラス

WordPress の出力エスケープ関数を型安全にラップするサービスクラスです。

```php
use WPPack\Component\Escaper\Escaper;

$escaper = new Escaper();

// HTML - esc_html() をラップ
$escaper->html('<script>alert("XSS")</script>');
// "&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;"

// HTML 属性 - esc_attr() をラップ
$escaper->attr('" onclick="alert(1)');
// "&quot; onclick=&quot;alert(1)"

// URL - esc_url() をラップ（HTML出力用）
$escaper->url('http://example.com/?a=1&b=2');
// "http://example.com/?a=1&#038;b=2"

// JavaScript - esc_js() をラップ
$escaper->js("He said \"hello\"");
// "He said \\&quot;hello\\&quot;"
```

### Escaper メソッド一覧

| メソッド | WordPress API | 具体的な動作 |
|---------|--------------|-------------|
| `html(string): string` | `esc_html()` | `&`, `<`, `>`, `"`, `'` を HTML エンティティに変換。HTML テキストコンテンツの出力に使用 |
| `attr(string): string` | `esc_attr()` | `&`, `<`, `>`, `"`, `'` を HTML エンティティに変換。HTML 属性値の出力に使用 |
| `url(string): string` | `esc_url()` | URL スキーム検証 + HTML エンティティ変換（`&` → `&#038;`）。`href`, `src` 属性の出力に使用 |
| `js(string): string` | `esc_js()` | クォート、バックスラッシュ等をエスケープ。インライン JavaScript の文字列リテラルに使用 |

### `Escaper::url()` と `Sanitizer::url()` の違い

| メソッド | WordPress API | HTML エンティティ変換 | 用途 |
|---------|--------------|---------------------|------|
| `Escaper::url()` | `esc_url()` | あり（`&` → `&#038;`） | HTML 出力（`href`, `src` 属性） |
| `Sanitizer::url()` | `esc_url_raw()` | なし | DB 保存、リダイレクト、API リクエスト |

```php
$url = 'http://example.com/?a=1&b=2';

// HTML 出力時はエスケーパーを使用（HTML エンティティ変換あり）
$escaper->url($url);    // "http://example.com/?a=1&#038;b=2"

// DB保存時はサニタイザーを使用（生の URL 文字列）
$sanitizer->url($url);  // "http://example.com/?a=1&b=2"
```

## Hook アトリビュート

→ 詳細は [Hook コンポーネント — Escaper](../hook/escaper.md) を参照してください。

## 依存関係

### 必須
なし — WordPress のエスケープ関数をそのまま利用

### 推奨
- **Hook コンポーネント** — Attribute ベースのフック登録
