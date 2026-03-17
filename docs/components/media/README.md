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

## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Media/Subscriber/`

### アップロードフック

#### #[WpHandleUploadFilter]

**WordPress Hook:** `wp_handle_upload`

```php
use WpPack\Component\Media\Attribute\WpHandleUploadFilter;

class UploadHandler
{
    #[WpHandleUploadFilter]
    public function processUpload(array $file): array
    {
        // ファイルタイプをより厳密にバリデーション
        if (!$this->isAllowedFileType($file['type'], $file['file'])) {
            $file['error'] = __('This file type is not allowed.', 'wppack');
            return $file;
        }

        // アップロード時に画像を最適化
        if (str_starts_with($file['type'], 'image/')) {
            $this->optimizeImage($file['file']);
        }

        return $file;
    }
}
```

#### #[WpHandleUploadPrefilterFilter]

**WordPress Hook:** `wp_handle_upload_prefilter`

```php
use WpPack\Component\Media\Attribute\WpHandleUploadPrefilterFilter;

class UploadValidator
{
    #[WpHandleUploadPrefilterFilter]
    public function validateBeforeUpload(array $file): array
    {
        // ユーザーロールごとのファイルサイズ制限をチェック
        $max_size = $this->getMaxUploadSize();
        if ($file['size'] > $max_size) {
            $file['error'] = sprintf(
                __('File size exceeds maximum allowed size of %s.', 'wppack'),
                size_format($max_size)
            );
            return $file;
        }

        // ファイル名のバリデーション
        if (!$this->isValidFilename($file['name'])) {
            $file['error'] = __('Invalid filename. Please use only letters, numbers, hyphens, and underscores.', 'wppack');
            return $file;
        }

        // ストレージ容量のチェック
        if (!$this->hasStorageSpace($file['size'])) {
            $file['error'] = __('Storage quota exceeded.', 'wppack');
            return $file;
        }

        // 必要に応じてファイル名を変更
        $file['name'] = $this->sanitizeFilename($file['name']);

        return $file;
    }

    private function getMaxUploadSize(): int
    {
        $role_limits = [
            'administrator' => 100 * MB_IN_BYTES,
            'editor' => 50 * MB_IN_BYTES,
            'author' => 25 * MB_IN_BYTES,
            'contributor' => 10 * MB_IN_BYTES,
        ];

        $user = wp_get_current_user();
        foreach ($role_limits as $role => $limit) {
            if (in_array($role, $user->roles)) {
                return $limit;
            }
        }

        return 5 * MB_IN_BYTES;
    }
}
```

#### #[UploadMimesFilter]

**WordPress Hook:** `upload_mimes`

```php
use WpPack\Component\Media\Attribute\UploadMimesFilter;

class MimeTypeManager
{
    #[UploadMimesFilter]
    public function customizeMimeTypes(array $mimes): array
    {
        // SVG を許可
        $mimes['svg'] = 'image/svg+xml';

        // WebP を許可
        $mimes['webp'] = 'image/webp';

        // 実行ファイルを禁止
        unset($mimes['exe']);

        return $mimes;
    }
}
```

### 画像処理フック

#### #[WpGenerateAttachmentMetadataFilter]

**WordPress Hook:** `wp_generate_attachment_metadata`

```php
use WpPack\Component\Media\Attribute\WpGenerateAttachmentMetadataFilter;

class AttachmentMetadataProcessor
{
    #[WpGenerateAttachmentMetadataFilter]
    public function enhanceMetadata(array $metadata, int $attachment_id): array
    {
        // EXIF データの追加
        if (wp_attachment_is_image($attachment_id)) {
            $exif = $this->extractExifData($attachment_id);
            if ($exif) {
                $metadata['image_meta'] = array_merge($metadata['image_meta'] ?? [], $exif);
            }
        }

        return $metadata;
    }
}
```

#### #[IntermediateSizesAdvancedFilter]

**WordPress Hook:** `intermediate_image_sizes_advanced`

```php
use WpPack\Component\Media\Attribute\IntermediateSizesAdvancedFilter;

class ImageSizeManager
{
    #[IntermediateSizesAdvancedFilter]
    public function customizeImageSizes(array $sizes, array $metadata): array
    {
        // Retina バージョンの追加
        foreach ($sizes as $name => $size) {
            $sizes[$name . '_2x'] = [
                'width' => $size['width'] * 2,
                'height' => $size['height'] * 2,
                'crop' => $size['crop'] ?? false,
            ];
        }

        // レスポンシブサイズの追加
        $sizes['responsive_small'] = ['width' => 480, 'height' => 9999, 'crop' => false];
        $sizes['responsive_medium'] = ['width' => 768, 'height' => 9999, 'crop' => false];
        $sizes['responsive_large'] = ['width' => 1024, 'height' => 9999, 'crop' => false];

        return $sizes;
    }
}
```

#### #[WpImageEditorsFilter]

**WordPress Hook:** `wp_image_editors`

```php
use WpPack\Component\Media\Attribute\WpImageEditorsFilter;

class ImageEditorSelector
{
    #[WpImageEditorsFilter]
    public function selectImageEditor(array $editors): array
    {
        // Imagick を優先
        array_unshift($editors, \WP_Image_Editor_Imagick::class);

        return $editors;
    }
}
```

### メディアライブラリフック

#### #[AjaxQueryAttachmentsArgsFilter]

**WordPress Hook:** `ajax_query_attachments_args`

```php
use WpPack\Component\Media\Attribute\AjaxQueryAttachmentsArgsFilter;

class MediaLibraryFilter
{
    #[AjaxQueryAttachmentsArgsFilter]
    public function filterMediaQuery(array $query): array
    {
        // 管理者以外は自分のアップロードのみにフィルタリング
        if (!current_user_can('manage_options')) {
            $query['author'] = get_current_user_id();
        }

        // カスタムタクソノミーによるフィルタリング
        if (!empty($_REQUEST['media_category'])) {
            $query['tax_query'] = [
                [
                    'taxonomy' => 'media_category',
                    'field' => 'term_id',
                    'terms' => intval($_REQUEST['media_category']),
                ],
            ];
        }

        return $query;
    }
}
```

#### #[MediaUploadTabsFilter]

**WordPress Hook:** `media_upload_tabs`

```php
use WpPack\Component\Media\Attribute\MediaUploadTabsFilter;

class MediaTabManager
{
    #[MediaUploadTabsFilter]
    public function addCustomTabs(array $tabs): array
    {
        // カスタムタブの追加
        $tabs['my_custom_tab'] = __('My Custom Media', 'my-plugin');

        return $tabs;
    }
}
```

#### #[AttachmentFieldsToEditFilter]

**WordPress Hook:** `attachment_fields_to_edit`

```php
use WpPack\Component\Media\Attribute\AttachmentFieldsToEditFilter;

class AttachmentFieldEditor
{
    #[AttachmentFieldsToEditFilter]
    public function addCustomFields(array $form_fields, \WP_Post $post): array
    {
        $form_fields['photographer'] = [
            'label' => __('Photographer', 'wppack'),
            'input' => 'text',
            'value' => get_post_meta($post->ID, 'photographer', true),
            'helps' => __('Credit the photographer.', 'wppack'),
        ];

        return $form_fields;
    }
}
```

### アタッチメント表示フック

#### #[WpGetAttachmentImageAttributesFilter]

**WordPress Hook:** `wp_get_attachment_image_attributes`

```php
use WpPack\Component\Media\Attribute\WpGetAttachmentImageAttributesFilter;

class ImageAttributeManager
{
    #[WpGetAttachmentImageAttributesFilter]
    public function enhanceImageAttributes(array $attr, \WP_Post $attachment, $size): array
    {
        // ブラウザネイティブの遅延読み込みを追加
        if (!isset($attr['loading'])) {
            $attr['loading'] = 'lazy';
        }

        // レスポンシブ srcset が存在しない場合に追加
        if (!isset($attr['srcset'])) {
            $attr['srcset'] = wp_get_attachment_image_srcset($attachment->ID, $size);
        }

        // CSS 用のアスペクト比を追加
        $metadata = wp_get_attachment_metadata($attachment->ID);
        if ($metadata && isset($metadata['width'], $metadata['height']) && $metadata['height'] > 0) {
            $ratio = $metadata['width'] / $metadata['height'];
            $attr['style'] = sprintf('aspect-ratio: %s;', $ratio);
        }

        return $attr;
    }
}
```

#### #[WpGetAttachmentUrlFilter]

**WordPress Hook:** `wp_get_attachment_url`

```php
use WpPack\Component\Media\Attribute\WpGetAttachmentUrlFilter;

class AttachmentUrlModifier
{
    #[WpGetAttachmentUrlFilter]
    public function modifyAttachmentUrl(string $url, int $attachment_id): string
    {
        // CDN URL に置換
        if (defined('CDN_URL') && CDN_URL) {
            $upload_dir = wp_get_upload_dir();
            $url = str_replace($upload_dir['baseurl'], CDN_URL, $url);
        }

        return $url;
    }
}
```

#### #[WpGetAttachmentLinkFilter]

**WordPress Hook:** `wp_get_attachment_link`

```php
use WpPack\Component\Media\Attribute\WpGetAttachmentLinkFilter;

class AttachmentLinkModifier
{
    #[WpGetAttachmentLinkFilter]
    public function modifyAttachmentLink(string $link, int $id, $size, bool $permalink): string
    {
        // ライトボックス用のデータ属性を追加
        $link = str_replace('<a ', '<a data-lightbox="gallery" ', $link);

        return $link;
    }
}
```

### アタッチメント管理フック

#### #[AddAttachmentAction]

**WordPress Hook:** `add_attachment`

```php
use WpPack\Component\Media\Attribute\AddAttachmentAction;

class AttachmentCreationHandler
{
    #[AddAttachmentAction]
    public function onAddAttachment(int $attachment_id): void
    {
        // デフォルトメタデータを設定
        update_post_meta($attachment_id, 'uploaded_by', get_current_user_id());
        update_post_meta($attachment_id, 'upload_date', current_time('mysql'));
    }
}
```

#### #[EditAttachmentAction]

**WordPress Hook:** `edit_attachment`

```php
use WpPack\Component\Media\Attribute\EditAttachmentAction;

class AttachmentEditHandler
{
    #[EditAttachmentAction]
    public function onEditAttachment(int $attachment_id): void
    {
        // 更新日時を記録
        update_post_meta($attachment_id, 'last_modified_by', get_current_user_id());

        // キャッシュをクリア
        clean_post_cache($attachment_id);
    }
}
```

#### #[DeleteAttachmentAction]

**WordPress Hook:** `delete_attachment`

```php
use WpPack\Component\Media\Attribute\DeleteAttachmentAction;

class AttachmentDeletionHandler
{
    #[DeleteAttachmentAction]
    public function onDeleteAttachment(int $attachment_id): void
    {
        // 関連データをクリーンアップ
        $this->removeFromGalleries($attachment_id);
        $this->clearCdnCache($attachment_id);
        $this->logDeletion($attachment_id);
    }
}
```

## Hook アトリビュートリファレンス

```php
// アップロード処理
#[WpHandleUploadFilter(priority?: int = 10)]              // アップロードされたファイルの処理
#[WpHandleUploadPrefilterFilter(priority?: int = 10)]     // アップロード前のバリデーション
#[UploadMimesFilter(priority?: int = 10)]                 // 許可される MIME タイプ

// 画像処理
#[WpGenerateAttachmentMetadataFilter(priority?: int = 10)] // アタッチメントメタデータの変更
#[IntermediateSizesAdvancedFilter(priority?: int = 10)]    // カスタム画像サイズの生成
#[WpImageEditorsFilter(priority?: int = 10)]              // 画像エディターの選択

// メディアライブラリ
#[AjaxQueryAttachmentsArgsFilter(priority?: int = 10)]    // メディアクエリのフィルタリング
#[MediaUploadTabsFilter(priority?: int = 10)]             // アップロードタブの追加
#[AttachmentFieldsToEditFilter(priority?: int = 10)]      // カスタムアタッチメントフィールド

// 表示
#[WpGetAttachmentImageAttributesFilter(priority?: int = 10)] // 画像属性
#[WpGetAttachmentUrlFilter(priority?: int = 10)]          // アタッチメント URL
#[WpGetAttachmentLinkFilter(priority?: int = 10)]         // アタッチメントリンク

// 管理
#[AddAttachmentAction(priority?: int = 10)]               // アタッチメント追加後
#[EditAttachmentAction(priority?: int = 10)]              // アタッチメント編集後
#[DeleteAttachmentAction(priority?: int = 10)]            // アタッチメント削除前
```

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress フック登録用

### 推奨
- **Filesystem コンポーネント** - ファイル操作用
- **DependencyInjection コンポーネント** - サービス注入用

## 関連ドキュメント

- [Storage 連携](./storage.md) - オブジェクトストレージとの連携
