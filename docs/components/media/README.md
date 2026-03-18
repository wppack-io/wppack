# Media コンポーネント

**パッケージ:** `wppack/media`
**名前空間:** `WpPack\Component\Media\`
**レイヤー:** Application

WordPress メディア関連フックを Named Hook アトリビュートで型安全に利用するためのコンポーネントです。

## インストール

```bash
composer require wppack/media
```

## 基本コンセプト

### Before（従来の WordPress）

```php
add_filter('wp_handle_upload', 'process_upload');
function process_upload(array $file): array {
    // Optimize image on upload
    return $file;
}

add_filter('wp_handle_upload_prefilter', 'validate_upload');
function validate_upload(array $file): array {
    if ($file['size'] > 5 * MB_IN_BYTES) {
        $file['error'] = 'File too large.';
    }
    return $file;
}

add_filter('wp_get_attachment_image_attributes', 'enhance_image_attrs', 10, 3);
function enhance_image_attrs(array $attr, WP_Post $attachment, $size): array {
    $attr['loading'] = 'lazy';
    return $attr;
}
```

### After（WpPack）

```php
use WpPack\Component\Media\Attribute\WpHandleUploadFilter;
use WpPack\Component\Media\Attribute\WpHandleUploadPrefilterFilter;
use WpPack\Component\Media\Attribute\WpGetAttachmentImageAttributesFilter;

class MediaHandler
{
    #[WpHandleUploadFilter]
    public function processUpload(array $file): array
    {
        // Optimize image on upload
        return $file;
    }

    #[WpHandleUploadPrefilterFilter]
    public function validateUpload(array $file): array
    {
        if ($file['size'] > 5 * MB_IN_BYTES) {
            $file['error'] = 'File too large.';
        }
        return $file;
    }

    #[WpGetAttachmentImageAttributesFilter]
    public function enhanceImageAttributes(array $attr, \WP_Post $attachment, $size): array
    {
        $attr['loading'] = 'lazy';
        return $attr;
    }
}
```

## Hook アトリビュート

→ 詳細は [Hook コンポーネント — Media](../hook/media.md) を参照してください。

## 依存関係

### 推奨
- **Filesystem コンポーネント** - ファイル操作用
- **DependencyInjection コンポーネント** - サービス注入用

## 関連ドキュメント

- [Storage 連携](./storage.md) - オブジェクトストレージとの連携
