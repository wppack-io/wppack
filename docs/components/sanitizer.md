# Sanitizer コンポーネント

**パッケージ:** `wppack/sanitizer`
**名前空間:** `WpPack\Component\Sanitizer\`
**レイヤー:** Abstraction

WordPress のサニタイズ関数を型安全にラップし、サニタイズ関連の WordPress フックを Named Hook アトリビュートとして提供するコンポーネントです。

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

### After（WpPack）

```php
use WpPack\Component\Sanitizer\Sanitizer;

$sanitizer = $container->get(Sanitizer::class);

$title = $sanitizer->text($_POST['title'] ?? '');
$content = $sanitizer->html($_POST['content'] ?? '');
$email = $sanitizer->email($_POST['email'] ?? '');
$url = $sanitizer->url($_POST['url'] ?? '');
$filename = $sanitizer->filename($_POST['filename'] ?? '');
```

## Sanitizer クラス

WordPress のサニタイズ関数を型安全にラップするサービスクラスです。

```php
use WpPack\Component\Sanitizer\Sanitizer;

$sanitizer = new Sanitizer();

// テキスト - sanitize_text_field() をラップ
$sanitizer->text('  Hello <script>alert("XSS")</script>  ');  // "Hello"

// HTML - wp_kses_post() をラップ
$sanitizer->html('<p>Hello</p><script>alert("XSS")</script>');  // "<p>Hello</p>"

// メール - sanitize_email() をラップ
$sanitizer->email('JOHN@EXAMPLE.COM');  // "john@example.com"

// URL - esc_url_raw() をラップ
$sanitizer->url('http://example.com/<script>');  // "http://example.com/"

// ファイル名 - sanitize_file_name() をラップ
$sanitizer->filename('my file (1).pdf');  // "my-file-1.pdf"

// キー - sanitize_key() をラップ
$sanitizer->key('My_Option-KEY!');  // "my_option-key"

// タイトル - sanitize_title() をラップ
$sanitizer->title('Hello World!');  // "hello-world"

// スラッグ - sanitize_title_with_dashes() をラップ
$sanitizer->slug('Hello World!');  // "hello-world"
```

### Sanitizer メソッド一覧

| メソッド | WordPress API | 説明 |
|---------|--------------|------|
| `text(string $value): string` | `sanitize_text_field()` | テキストフィールドをサニタイズ |
| `html(string $value): string` | `wp_kses_post()` | 投稿に許可された HTML タグのみ保持 |
| `email(string $value): string` | `sanitize_email()` | メールアドレスをサニタイズ |
| `url(string $value): string` | `esc_url_raw()` | URL をサニタイズ（DB 保存用） |
| `filename(string $value): string` | `sanitize_file_name()` | ファイル名をサニタイズ |
| `key(string $value): string` | `sanitize_key()` | キー文字列をサニタイズ |
| `title(string $value): string` | `sanitize_title()` | タイトルをサニタイズ |
| `slug(string $value): string` | `sanitize_title_with_dashes()` | スラッグをサニタイズ |

## Named Hook Attributes

### メタサニタイズフック

#### `#[SanitizePostMetaFilter]`

**WordPress Hook:** `sanitize_post_meta_{$meta_key}`

投稿メタの保存時にサニタイズ処理を適用します。

```php
use WpPack\Component\Sanitizer\Attribute\SanitizePostMetaFilter;

final class PostMetaSanitizer
{
    public function __construct(private readonly Sanitizer $sanitizer) {}

    #[SanitizePostMetaFilter(metaKey: 'price')]
    public function sanitizePrice(mixed $meta_value, string $meta_key, string $object_type): float
    {
        return (float) $meta_value;
    }

    #[SanitizePostMetaFilter(metaKey: 'email')]
    public function sanitizeEmail(mixed $meta_value, string $meta_key, string $object_type): string
    {
        return $this->sanitizer->email((string) $meta_value);
    }
}
```

### フィールドサニタイズフック

#### `#[SanitizeTextFieldFilter]`

**WordPress Hook:** `sanitize_text_field`

```php
use WpPack\Component\Sanitizer\Attribute\SanitizeTextFieldFilter;

final class TextFieldSanitizer
{
    #[SanitizeTextFieldFilter(priority: 10)]
    public function sanitizeTextField(string $filtered, string $str): string
    {
        // 追加のサニタイズ処理
        return trim($filtered);
    }
}
```

#### `#[SanitizeTitleFilter]`

**WordPress Hook:** `sanitize_title`

```php
use WpPack\Component\Sanitizer\Attribute\SanitizeTitleFilter;

final class TitleSanitizer
{
    #[SanitizeTitleFilter(priority: 10)]
    public function sanitizeTitle(string $title, string $raw_title, string $context): string
    {
        // 日本語スラッグ対応
        if (preg_match('/[\x{3000}-\x{9FFF}]/u', $raw_title)) {
            return urlencode($raw_title);
        }

        return $title;
    }
}
```

#### `#[SanitizeFileNameFilter]`

**WordPress Hook:** `sanitize_file_name`

```php
use WpPack\Component\Sanitizer\Attribute\SanitizeFileNameFilter;

final class FileNameSanitizer
{
    #[SanitizeFileNameFilter(priority: 10)]
    public function sanitizeFileName(string $filename, string $filename_raw): string
    {
        // ファイル名を小文字に統一
        return strtolower($filename);
    }
}
```

#### `#[SanitizeEmailFilter]`

**WordPress Hook:** `sanitize_email`

```php
use WpPack\Component\Sanitizer\Attribute\SanitizeEmailFilter;

final class EmailSanitizer
{
    #[SanitizeEmailFilter(priority: 10)]
    public function sanitizeEmail(string $sanitized_email, string $email, string $message): string
    {
        return strtolower($sanitized_email);
    }
}
```

### 出力エスケープフック

#### `#[EscHtmlFilter]`

**WordPress Hook:** `esc_html`

```php
use WpPack\Component\Sanitizer\Attribute\EscHtmlFilter;

final class HtmlEscaper
{
    #[EscHtmlFilter(priority: 10)]
    public function escapeHtml(string $safe_text, string $text): string
    {
        return $safe_text;
    }
}
```

#### `#[CleanUrlFilter]`

**WordPress Hook:** `clean_url`

URL エスケープ（`esc_url()`）のフィルターです。

```php
use WpPack\Component\Sanitizer\Attribute\CleanUrlFilter;

final class UrlCleaner
{
    #[CleanUrlFilter(priority: 10)]
    public function cleanUrl(string $good_protocol_url, string $original_url, string $_context): string
    {
        return $good_protocol_url;
    }
}
```

## Hook Attribute リファレンス

| Attribute | WordPress Hook | 説明 |
|-----------|---------------|------|
| `#[SanitizePostMetaFilter(metaKey: 'key')]` | `sanitize_post_meta_{$meta_key}` | 投稿メタをサニタイズ |
| `#[SanitizeTextFieldFilter(priority: 10)]` | `sanitize_text_field` | テキストフィールドをサニタイズ |
| `#[SanitizeTitleFilter(priority: 10)]` | `sanitize_title` | タイトルをサニタイズ |
| `#[SanitizeFileNameFilter(priority: 10)]` | `sanitize_file_name` | ファイル名をサニタイズ |
| `#[SanitizeEmailFilter(priority: 10)]` | `sanitize_email` | メールアドレスをサニタイズ |
| `#[EscHtmlFilter(priority: 10)]` | `esc_html` | HTML をエスケープ |
| `#[CleanUrlFilter(priority: 10)]` | `clean_url` | URL をエスケープ（`esc_url`） |

## WordPress 統合

Sanitizer コンポーネントは WordPress のサニタイズ関数を直接呼び出すため、WordPress のフィルターフックが適用された結果が返されます。例えば `$sanitizer->text()` は内部で `sanitize_text_field()` を呼び出すので、`sanitize_text_field` フィルターに登録された処理が自動的に適用されます。

Named Hook Attributes を使用するには、Hook コンポーネントによるアトリビュート解析が必要です。

## 依存関係

### 必須
なし -- WordPress のサニタイズ関数をそのまま利用

### 推奨
- **Hook コンポーネント** -- Attribute ベースのフック登録
