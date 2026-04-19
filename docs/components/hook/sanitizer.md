## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Sanitizer/Subscriber/`

### メタサニタイズフック

#### `#[SanitizePostMetaFilter]`

**WordPress Hook:** `sanitize_post_meta_{$meta_key}`

投稿メタの保存時にサニタイズ処理を適用します。

```php
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizePostMetaFilter;

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
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeCommentMetaFilter;

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
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeTermMetaFilter;

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
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeUserMetaFilter;

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
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeTextFieldFilter;

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
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeTitleFilter;

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
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeFileNameFilter;

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
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeEmailFilter;

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
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\SanitizeKeyFilter;

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
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\PreInsertTermFilter;

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
use WPPack\Component\Hook\Attribute\Sanitizer\Filter\PreUserLoginFilter;

final class UserLoginSanitizer
{
    #[PreUserLoginFilter(priority: 10)]
    public function sanitizeLogin(string $userLogin): string
    {
        return strtolower($userLogin);
    }
}
```

## クイックリファレンス

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
```
