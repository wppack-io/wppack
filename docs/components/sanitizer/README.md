# Sanitizer コンポーネント

**パッケージ:** `wppack/sanitizer`
**名前空間:** `WPPack\Component\Sanitizer\`
**Category:** Presentation

WordPress のサニタイズ関数を型安全にラップし、サニタイズ関連の WordPress フックを Named Hook アトリビュートとして提供するコンポーネントです。

入力のサニタイズを担当します。出力のエスケープについては [Escaper コンポーネント](../escaper/README.md) を参照してください。

## インストール

```bash
composer require wppack/sanitizer
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// 個別のサニタイズ関数を直接呼び出し
$title = sanitize_text_field($_POST['title'] ?? '');
$content = wp_kses_post($_POST['content'] ?? '');
$email = sanitize_email($_POST['email'] ?? '');
$url = esc_url_raw($_POST['url'] ?? '');
$filename = sanitize_file_name($_POST['filename'] ?? '');
```

### After（WPPack）

```php
use WPPack\Component\Sanitizer\Sanitizer;

$sanitizer = $container->get(Sanitizer::class);

$title = $sanitizer->text($_POST['title'] ?? '');
$content = $sanitizer->ksesPost($_POST['content'] ?? '');
$email = $sanitizer->email($_POST['email'] ?? '');
$url = $sanitizer->url($_POST['url'] ?? '');
$filename = $sanitizer->filename($_POST['filename'] ?? '');
```

## Sanitizer クラス

WordPress のサニタイズ関数を型安全にラップするサービスクラスです。

```php
use WPPack\Component\Sanitizer\Sanitizer;

$sanitizer = new Sanitizer();

// テキスト - sanitize_text_field() をラップ
$sanitizer->text('  Hello <script>alert("XSS")</script>  ');  // "Hello"

// テキストエリア - sanitize_textarea_field() をラップ
$sanitizer->textarea("line1\nline2<script>XSS</script>");  // "line1\nline2"

// KSES（投稿用） - wp_kses_post() をラップ
$sanitizer->ksesPost('<p>Hello</p><script>alert("XSS")</script>');  // "<p>Hello</p>"

// KSES（カスタム） - wp_kses() をラップ
$sanitizer->kses('<p>Hello</p>', ['p' => []]);  // "<p>Hello</p>"
$sanitizer->kses('<p>Hello</p>', 'strip');       // "Hello"

// 全タグ除去 - wp_strip_all_tags() をラップ
$sanitizer->stripTags('<p>Hello</p>');  // "Hello"

// メール - sanitize_email() をラップ
$sanitizer->email('JOHN@EXAMPLE.COM');  // "john@example.com"

// URL - esc_url_raw() をラップ（DB保存・リダイレクト用）
$sanitizer->url('http://example.com/<script>');  // "http://example.com/"

// ファイル名 - sanitize_file_name() をラップ
$sanitizer->filename('my file (1).pdf');  // "my-file-1.pdf"

// キー - sanitize_key() をラップ
$sanitizer->key('My_Option-KEY!');  // "my_option-key"

// タイトル - sanitize_title() をラップ
$sanitizer->title('Hello World!');  // "hello-world"

// スラッグ - sanitize_title_with_dashes() をラップ
$sanitizer->slug('Hello World!');  // "hello-world"

// HTML クラス - sanitize_html_class() をラップ
$sanitizer->htmlClass('my-class!@#');  // "my-class"

// ユーザー名 - sanitize_user() をラップ
$sanitizer->user('john doe!');  // "john doe"

// MIME タイプ - sanitize_mime_type() をラップ
$sanitizer->mimeType('image/png');  // "image/png"

// 16進カラー - sanitize_hex_color() をラップ
$sanitizer->hexColor('#ff0000');  // "#ff0000"
$sanitizer->hexColor('invalid');  // ""
```

### Sanitizer メソッド一覧

| メソッド | WordPress API | 具体的な動作 |
|---------|--------------|-------------|
| `text(string): string` | `sanitize_text_field()` | HTML タグ除去、UTF-8 検証、余分な空白除去（単一行） |
| `textarea(string): string` | `sanitize_textarea_field()` | `text()` と同様だが改行を保持（複数行入力用） |
| `ksesPost(string): string` | `wp_kses_post()` | 投稿コンテンツに安全な HTML タグのみ保持（`<script>`, `<form>` 等を除去） |
| `kses(string, string\|array): string` | `wp_kses()` | 指定した許可タグのみ保持。第2引数はコンテキスト文字列またはタグ配列 |
| `stripTags(string): string` | `wp_strip_all_tags()` | 全 HTML タグを除去（`<script>`, `<style>` の中身も除去） |
| `email(string): string` | `sanitize_email()` | メールアドレスとして不正な文字を除去 |
| `url(string): string` | `esc_url_raw()` | URL スキーム検証、不正な文字を除去（HTML エンティティ変換なし） |
| `filename(string): string` | `sanitize_file_name()` | 特殊文字を除去、スペースをダッシュに変換 |
| `key(string): string` | `sanitize_key()` | 小文字英数字・ダッシュ・アンダースコアのみに制限 |
| `title(string): string` | `sanitize_title()` | URL に安全な文字列に変換、アクセント記号を除去 |
| `slug(string): string` | `sanitize_title_with_dashes()` | `title()` と同様 + スペースをダッシュに変換 |
| `htmlClass(string): string` | `sanitize_html_class()` | A-Z, a-z, 0-9, アンダースコア, ダッシュのみに制限 |
| `user(string): string` | `sanitize_user()` | ユーザー名として不正な文字を除去 |
| `mimeType(string): string` | `sanitize_mime_type()` | MIME タイプ形式を検証（例: `image/png`） |
| `hexColor(string): string` | `sanitize_hex_color()` | `#RRGGBB` または `#RGB` 形式を検証、不正な場合は空文字を返す |

### `url()` と `Escaper::url()` の違い

| メソッド | WordPress API | HTML エンティティ変換 | 用途 |
|---------|--------------|---------------------|------|
| `Sanitizer::url()` | `esc_url_raw()` | なし | DB 保存、リダイレクト、API リクエスト |
| `Escaper::url()` | `esc_url()` | あり（`&` → `&#038;`） | HTML 出力（`href`, `src` 属性） |

```php
$url = 'http://example.com/?a=1&b=2';

// DB保存時はサニタイザーを使用（生の URL 文字列）
$sanitizer->url($url);  // "http://example.com/?a=1&b=2"

// HTML 出力時はエスケーパーを使用（HTML エンティティ変換あり）
$escaper->url($url);    // "http://example.com/?a=1&#038;b=2"
```

## Hook アトリビュート

→ 詳細は [Hook コンポーネント — Sanitizer](../hook/sanitizer.md) を参照してください。

## WordPress 統合

Sanitizer コンポーネントは WordPress のサニタイズ関数を直接呼び出すため、WordPress のフィルターフックが適用された結果が返されます。例えば `$sanitizer->text()` は内部で `sanitize_text_field()` を呼び出すので、`sanitize_text_field` フィルターに登録された処理が自動的に適用されます。

Named Hook Attributes を使用するには、Hook コンポーネントによるアトリビュート解析が必要です。

## 依存関係

### 必須
なし — WordPress のサニタイズ関数をそのまま利用

### 推奨
- **Hook コンポーネント** — Attribute ベースのフック登録
