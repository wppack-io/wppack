# Sanitizer コンポーネント

**パッケージ:** `wppack/sanitizer`
**名前空間:** `WpPack\Component\Sanitizer\`
**レイヤー:** Abstraction

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

### After（WpPack）

```php
use WpPack\Component\Sanitizer\Sanitizer;

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
use WpPack\Component\Sanitizer\Sanitizer;

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

## Named Hook Attributes

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Sanitizer/Subscriber/`

### メタサニタイズフック

#### `#[SanitizePostMetaFilter]`

**WordPress Hook:** `sanitize_post_meta_{$meta_key}`

投稿メタの保存時にサニタイズ処理を適用します。

```php
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizePostMetaFilter;

final class PostMetaSanitizer
{
    public function __construct(private readonly Sanitizer $sanitizer) {}

    #[SanitizePostMetaFilter(metaKey: 'price')]
    public function sanitizePrice(mixed $metaValue, string $metaKey, string $objectType): float
    {
        return (float) $metaValue;
    }

    #[SanitizePostMetaFilter(metaKey: 'email')]
    public function sanitizeEmail(mixed $metaValue, string $metaKey, string $objectType): string
    {
        return $this->sanitizer->email((string) $metaValue);
    }
}
```

#### `#[SanitizeCommentMetaFilter]`

**WordPress Hook:** `sanitize_comment_meta_{$meta_key}`

コメントメタの保存時にサニタイズ処理を適用します。

```php
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeCommentMetaFilter;

final class CommentMetaSanitizer
{
    #[SanitizeCommentMetaFilter(metaKey: 'rating')]
    public function sanitizeRating(mixed $metaValue, string $metaKey, string $objectType): int
    {
        return max(1, min(5, (int) $metaValue));
    }
}
```

#### `#[SanitizeTermMetaFilter]`

**WordPress Hook:** `sanitize_term_meta_{$meta_key}`

タームメタの保存時にサニタイズ処理を適用します。

```php
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeTermMetaFilter;

final class TermMetaSanitizer
{
    #[SanitizeTermMetaFilter(metaKey: 'color')]
    public function sanitizeColor(mixed $metaValue, string $metaKey, string $objectType): string
    {
        return (new Sanitizer())->hexColor((string) $metaValue);
    }
}
```

#### `#[SanitizeUserMetaFilter]`

**WordPress Hook:** `sanitize_user_meta_{$meta_key}`

ユーザーメタの保存時にサニタイズ処理を適用します。

```php
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeUserMetaFilter;

final class UserMetaSanitizer
{
    #[SanitizeUserMetaFilter(metaKey: 'nickname')]
    public function sanitizeNickname(mixed $metaValue, string $metaKey, string $objectType): string
    {
        return (new Sanitizer())->text((string) $metaValue);
    }
}
```

### フィールドサニタイズフック

#### `#[SanitizeTextFieldFilter]`

**WordPress Hook:** `sanitize_text_field`

```php
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeTextFieldFilter;

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
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeTitleFilter;

final class TitleSanitizer
{
    #[SanitizeTitleFilter(priority: 10)]
    public function sanitizeTitle(string $title, string $rawTitle, string $context): string
    {
        // 日本語スラッグ対応
        if (preg_match('/[\x{3000}-\x{9FFF}]/u', $rawTitle)) {
            return urlencode($rawTitle);
        }

        return $title;
    }
}
```

#### `#[SanitizeFileNameFilter]`

**WordPress Hook:** `sanitize_file_name`

```php
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeFileNameFilter;

final class FileNameSanitizer
{
    #[SanitizeFileNameFilter(priority: 10)]
    public function sanitizeFileName(string $filename, string $filenameRaw): string
    {
        // ファイル名を小文字に統一
        return strtolower($filename);
    }
}
```

#### `#[SanitizeEmailFilter]`

**WordPress Hook:** `sanitize_email`

```php
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeEmailFilter;

final class EmailSanitizer
{
    #[SanitizeEmailFilter(priority: 10)]
    public function sanitizeEmail(string $sanitizedEmail, string $email, string $message): string
    {
        return strtolower($sanitizedEmail);
    }
}
```

#### `#[SanitizeKeyFilter]`

**WordPress Hook:** `sanitize_key`

```php
use WpPack\Component\Sanitizer\Attribute\Filter\SanitizeKeyFilter;

final class KeySanitizer
{
    #[SanitizeKeyFilter(priority: 10)]
    public function sanitizeKey(string $sanitizedKey, string $rawKey): string
    {
        return $sanitizedKey;
    }
}
```

### その他のフック

#### `#[PreInsertTermFilter]`

**WordPress Hook:** `pre_insert_term`

ターム挿入前のフィルターです。

```php
use WpPack\Component\Sanitizer\Attribute\Filter\PreInsertTermFilter;

final class TermValidator
{
    #[PreInsertTermFilter(priority: 10)]
    public function validateTerm(string $term, string $taxonomy): string
    {
        return trim($term);
    }
}
```

#### `#[PreUserLoginFilter]`

**WordPress Hook:** `pre_user_login`

ユーザーログイン名の保存前フィルターです。

```php
use WpPack\Component\Sanitizer\Attribute\Filter\PreUserLoginFilter;

final class UserLoginSanitizer
{
    #[PreUserLoginFilter(priority: 10)]
    public function sanitizeLogin(string $userLogin): string
    {
        return strtolower($userLogin);
    }
}
```

## Hook Attribute リファレンス

| Attribute | WordPress Hook | 説明 |
|-----------|---------------|------|
| `#[SanitizePostMetaFilter(metaKey: 'key')]` | `sanitize_post_meta_{$meta_key}` | 投稿メタをサニタイズ |
| `#[SanitizeCommentMetaFilter(metaKey: 'key')]` | `sanitize_comment_meta_{$meta_key}` | コメントメタをサニタイズ |
| `#[SanitizeTermMetaFilter(metaKey: 'key')]` | `sanitize_term_meta_{$meta_key}` | タームメタをサニタイズ |
| `#[SanitizeUserMetaFilter(metaKey: 'key')]` | `sanitize_user_meta_{$meta_key}` | ユーザーメタをサニタイズ |
| `#[SanitizeTextFieldFilter(priority: 10)]` | `sanitize_text_field` | テキストフィールドをサニタイズ |
| `#[SanitizeTitleFilter(priority: 10)]` | `sanitize_title` | タイトルをサニタイズ |
| `#[SanitizeFileNameFilter(priority: 10)]` | `sanitize_file_name` | ファイル名をサニタイズ |
| `#[SanitizeEmailFilter(priority: 10)]` | `sanitize_email` | メールアドレスをサニタイズ |
| `#[SanitizeKeyFilter(priority: 10)]` | `sanitize_key` | キー文字列をサニタイズ |
| `#[PreInsertTermFilter(priority: 10)]` | `pre_insert_term` | ターム挿入前にフィルター |
| `#[PreUserLoginFilter(priority: 10)]` | `pre_user_login` | ユーザーログイン名をフィルター |

## WordPress 統合

Sanitizer コンポーネントは WordPress のサニタイズ関数を直接呼び出すため、WordPress のフィルターフックが適用された結果が返されます。例えば `$sanitizer->text()` は内部で `sanitize_text_field()` を呼び出すので、`sanitize_text_field` フィルターに登録された処理が自動的に適用されます。

Named Hook Attributes を使用するには、Hook コンポーネントによるアトリビュート解析が必要です。

## 依存関係

### 必須
なし — WordPress のサニタイズ関数をそのまま利用

### 推奨
- **Hook コンポーネント** — Attribute ベースのフック登録
