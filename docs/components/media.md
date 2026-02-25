# Media コンポーネント

**パッケージ:** `wppack/media`
**名前空間:** `WpPack\Component\Media\`
**レイヤー:** Application

型安全なアタッチメント、アトリビュートベースの設定、画像処理機能を備えた、WordPress メディア管理のためのモダンなオブジェクト指向フレームワークです。

## インストール

```bash
composer require wppack/media
```

## コアコンセプト

### 従来の WordPress コード

```php
// 手動でエラーが発生しやすいメディア処理
if (!function_exists('wp_handle_upload')) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
}

$uploaded_file = $_FILES['upload_file'];

// ファイルを手動でバリデーション
$allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
$file_info = pathinfo($uploaded_file['name']);
$extension = strtolower($file_info['extension']);

if (!in_array($extension, $allowed_types)) {
    wp_die('File type not allowed');
}

// ファイルアップロード
$upload = wp_handle_upload($uploaded_file, ['test_form' => false]);

if (isset($upload['error'])) {
    wp_die('Upload failed: ' . $upload['error']);
}

// アタッチメント作成
$attachment = [
    'post_mime_type' => $upload['type'],
    'post_title' => sanitize_file_name($file_info['filename']),
    'post_content' => '',
    'post_status' => 'inherit'
];

$attachment_id = wp_insert_attachment($attachment, $upload['file']);

// メタデータを手動で生成
require_once(ABSPATH . 'wp-admin/includes/image.php');
$attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
wp_update_attachment_metadata($attachment_id, $attachment_data);

// アタッチメントメタを手動で更新
update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Alt text');
update_post_meta($attachment_id, 'custom_field', 'value');

add_image_size('custom-thumb', 300, 200, true);

$url = wp_get_attachment_url($attachment_id);
$srcset = wp_get_attachment_image_srcset($attachment_id, 'large');
```

### WpPack コード

```php
use WpPack\Component\Media\AbstractAttachment;
use WpPack\Component\Media\Attribute\Attachment;
use WpPack\Component\Media\Attribute\AttachmentMeta;
use WpPack\Component\Media\Attribute\ImageSizes;

#[Attachment(
    allowedTypes: ['image/jpeg', 'image/png', 'image/gif'],
    maxSize: '5MB',
    generateThumbnails: true
)]
#[ImageSizes([
    'thumbnail' => [150, 150, true],
    'medium' => [300, 300, false],
    'large' => [800, 600, false]
])]
class ImageAttachment extends AbstractAttachment
{
    #[AttachmentMeta('alt_text')]
    public string $altText = '';

    #[AttachmentMeta('photographer')]
    public ?string $photographer = null;

    #[AttachmentMeta('is_featured', type: 'boolean')]
    public bool $isFeatured = false;

    public function validate(): array
    {
        $errors = [];

        if (!$this->isImage()) {
            $errors[] = 'File must be an image';
        }

        if ($this->getFileSize() > 5 * 1024 * 1024) {
            $errors[] = 'Image must be smaller than 5MB';
        }

        if (empty($this->altText) && $this->isPublic()) {
            $errors[] = 'Alt text is required for public images';
        }

        return $errors;
    }

    public function optimize(): void
    {
        if (!$this->isImage()) {
            return;
        }

        $this->processor->optimize($this->getFilePath(), [
            'quality' => 85,
            'strip_metadata' => true,
            'progressive' => true
        ]);

        $this->regenerateThumbnails();
    }
}

// 依存性注入を使用した利用例
$uploader = $container->get(FileUploader::class);

// 画像のアップロードと処理
$image = $uploader->upload($_FILES['image'], ImageAttachment::class);

// メタデータの設定
$image->altText = 'Beautiful landscape';
$image->photographer = 'John Doe';

// バリデーションと保存
$errors = $image->validate();
if (empty($errors)) {
    $image->optimize();
    $image->save();
}
```

## 機能一覧

- **オブジェクト指向のメディア管理** - クラスベースのアタッチメント
- **アトリビュートベースのファイルアップロード処理** - 型安全な操作
- **型安全なアタッチメント操作** - 強く型付けされたプロパティ
- **アトリビュートによるメディアメタ管理** - 構造化データ
- **画像処理と最適化** - パフォーマンス向上
- **ファイルバリデーションとセキュリティ** - アプリケーションの保護
- **メディアライブラリの拡張** - 整理の改善
- **メディアクエリ** - アタッチメントの検索
- **アップロードディレクトリ管理** - UploadDir による制御
- **EXIF / 画像メタデータ** - ImageMeta による抽出

## クイックスタート

### プロジェクト構成

```
my-plugin/
├── src/
│   ├── Media/
│   │   ├── ImageAttachment.php
│   │   ├── DocumentAttachment.php
│   │   ├── ProductImage.php
│   │   └── MediaUploadHandler.php
│   └── ServiceProvider.php
└── composer.json
```

### 最初のアタッチメントクラスの作成

```php
<?php
declare(strict_types=1);

namespace MyPlugin\Media;

use WpPack\Component\Media\AbstractAttachment;
use WpPack\Component\Media\Attribute\Attachment;
use WpPack\Component\Media\Attribute\AttachmentMeta;

#[Attachment(
    allowedTypes: ['image/jpeg', 'image/png', 'image/gif'],
    maxSize: '5MB'
)]
class ImageAttachment extends AbstractAttachment
{
    #[AttachmentMeta('alt_text')]
    public string $altText = '';

    #[AttachmentMeta('caption')]
    public string $caption = '';

    public function validate(): array
    {
        $errors = [];

        if (empty($this->altText)) {
            $errors[] = 'Alt text is required for accessibility';
        }

        return $errors;
    }
}
```

### アップロードハンドラー

```php
<?php
declare(strict_types=1);

namespace MyPlugin\Media;

use WpPack\Component\Media\FileUploader;

class MediaUploadHandler
{
    public function __construct(
        private FileUploader $uploader,
        private MediaRepository $media
    ) {}

    public function handleUpload(array $uploadedFile): ImageAttachment
    {
        // アタッチメントインスタンスの作成
        $image = new ImageAttachment();

        // ファイルのアップロード
        $result = $this->uploader->upload($uploadedFile, $image);

        if ($result->isSuccess()) {
            // メタデータの設定
            $image->altText = $_POST['alt_text'] ?? '';
            $image->caption = $_POST['caption'] ?? '';

            // データベースに保存
            $this->media->save($image);

            return $image;
        }

        throw new UploadException($result->getError());
    }
}
```

### フォームアップロードの処理

```php
// フォームハンドラー内
if (isset($_FILES['image'])) {
    $handler = $container->get(MediaUploadHandler::class);

    try {
        $image = $handler->handleUpload($_FILES['image']);

        echo "Image uploaded successfully!";
        echo "URL: " . $image->getUrl();
        echo "ID: " . $image->getId();

    } catch (UploadException $e) {
        echo "Upload failed: " . $e->getMessage();
    }
}
```

### アップロードフォーム

```php
<form method="post" enctype="multipart/form-data">
    <?php wp_nonce_field('upload_image', 'upload_nonce'); ?>

    <div>
        <label for="image">Select Image:</label>
        <input type="file" name="image" id="image" accept="image/*" required>
    </div>

    <div>
        <label for="alt_text">Alt Text:</label>
        <input type="text" name="alt_text" id="alt_text" required>
    </div>

    <div>
        <label for="caption">Caption:</label>
        <textarea name="caption" id="caption"></textarea>
    </div>

    <button type="submit">Upload Image</button>
</form>
```

## MediaManager

メディア操作のエントリーポイントです。

```php
use WpPack\Component\Media\MediaManager;

$mediaManager = new MediaManager();

// アップロード
$attachment = $mediaManager->upload($uploadedFile);
$attachment = $mediaManager->upload($uploadedFile, parentPostId: $postId);

// 検索
$attachment = $mediaManager->find($attachmentId);

// 削除
$mediaManager->delete($attachmentId, forceDelete: true);

// URL で検索
$attachment = $mediaManager->findByUrl('https://example.com/wp-content/uploads/2024/01/image.jpg');
```

## Attachment

アタッチメントを表すオブジェクトです。メタデータと URL へのアクセスを提供します。

```php
use WpPack\Component\Media\Attachment;

$attachment = $mediaManager->find($attachmentId);

// 基本情報
$id = $attachment->getId();
$title = $attachment->getTitle();
$mimeType = $attachment->getMimeType();
$fileSize = $attachment->getFileSize();

// URL
$url = $attachment->getUrl();
$url = $attachment->getUrl('thumbnail');
$url = $attachment->getUrl('custom-thumb');

// HTML
$html = $attachment->toHtml('large', ['class' => 'featured-image']);
$srcset = $attachment->getSrcset('large');

// メタデータ
$metadata = $attachment->getMetadata();
$width = $metadata->getWidth();
$height = $metadata->getHeight();
$sizes = $metadata->getSizes();
```

## ImageSize

カスタム画像サイズの登録を行います。

```php
use WpPack\Component\Media\ImageSize;

// 固定サイズ（クロップ）
$mediaManager->addImageSize(new ImageSize(
    name: 'card-thumbnail',
    width: 400,
    height: 300,
    crop: true,
));

// アスペクト比を維持（リサイズ）
$mediaManager->addImageSize(new ImageSize(
    name: 'article-hero',
    width: 1200,
    height: 630,
    crop: false,
));

// 幅のみ指定
$mediaManager->addImageSize(new ImageSize(
    name: 'content-width',
    width: 800,
    height: 0,
));
```

## アトリビュートによる型安全なアタッチメント

### カスタムサイズ付き画像アタッチメント

```php
use WpPack\Component\Media\Attribute\ImageSizes;

#[Attachment(
    allowedTypes: ['image/jpeg', 'image/png'],
    maxSize: '5MB',
    generateThumbnails: true
)]
#[ImageSizes([
    'thumbnail' => [150, 150, true],
    'medium' => [300, 300, false],
    'large' => [1024, 768, false],
    'hero' => [1920, 600, true]
])]
class ProductImage extends ImageAttachment
{
    #[AttachmentMeta('product_id', type: 'integer')]
    public ?int $productId = null;

    #[AttachmentMeta('is_primary', type: 'boolean')]
    public bool $isPrimary = false;

    public function optimize(): void
    {
        parent::optimize();

        // 商品画像の追加最適化
        if ($this->getWidth() > 2000 || $this->getHeight() > 2000) {
            $this->resize(2000, 2000, false);
        }
    }
}
```

### ドキュメントアタッチメント

```php
#[Attachment(
    allowedTypes: ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    maxSize: '10MB',
    directory: 'documents'
)]
class DocumentAttachment extends AbstractAttachment
{
    #[AttachmentMeta('document_type')]
    public string $documentType = 'general';

    #[AttachmentMeta('author')]
    public ?string $author = null;

    #[AttachmentMeta('version')]
    public string $version = '1.0';

    #[AttachmentMeta('download_count', type: 'integer')]
    public int $downloadCount = 0;

    #[AttachmentMeta('is_confidential', type: 'boolean')]
    public bool $isConfidential = false;

    #[AttachmentMeta('expiry_date')]
    public ?DateTime $expiryDate = null;

    public function isExpired(): bool
    {
        return $this->expiryDate && $this->expiryDate < new DateTime();
    }

    public function canDownload(User $user): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        if ($this->isConfidential && !$user->hasCapability('access_confidential_documents')) {
            return false;
        }

        return true;
    }

    public function recordDownload(): void
    {
        $this->downloadCount++;
        $this->save();
    }

    public function getDownloadUrl(): string
    {
        return add_query_arg([
            'action' => 'download_document',
            'id' => $this->getId(),
            'nonce' => wp_create_nonce('download_' . $this->getId())
        ], admin_url('admin-ajax.php'));
    }
}
```

### ドキュメントダウンロードハンドラー

```php
class DocumentDownloadHandler
{
    public function __construct(
        private MediaRepository $media
    ) {}

    #[Action('wp_ajax_download_document')]
    #[Action('wp_ajax_nopriv_download_document')]
    public function onWpAjaxDownloadDocument(): void
    {
        $id = intval($_GET['id'] ?? 0);
        $nonce = $_GET['nonce'] ?? '';

        if (!wp_verify_nonce($nonce, 'download_' . $id)) {
            wp_die('Invalid request');
        }

        $document = $this->media->find($id, DocumentAttachment::class);

        if (!$document) {
            wp_die('Document not found');
        }

        // ダウンロードを記録
        $document->recordDownload();

        // ファイルを送信
        $this->sendFile($document);
    }

    private function sendFile(DocumentAttachment $document): void
    {
        $filePath = $document->getFilePath();

        if (!file_exists($filePath)) {
            wp_die('File not found');
        }

        header('Content-Type: ' . $document->getMimeType());
        header('Content-Disposition: attachment; filename="' . $document->originalName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');

        readfile($filePath);
        exit;
    }
}
```

## アップロード処理

### UploadedFile

```php
use WpPack\Component\Media\Upload\UploadedFile;

// $_FILES からの変換
$file = UploadedFile::fromGlobals('my_file_input');

// 手動作成
$file = new UploadedFile(
    path: '/tmp/phpXXXXXX',
    originalName: 'photo.jpg',
    mimeType: 'image/jpeg',
);

$attachment = $mediaManager->upload($file);
```

### アップロードディレクトリ

```php
use WpPack\Component\Media\Upload\UploadDir;

$uploadDir = new UploadDir();

$basePath = $uploadDir->getBasePath();     // /var/www/html/wp-content/uploads
$baseUrl = $uploadDir->getBaseUrl();       // https://example.com/wp-content/uploads
$currentPath = $uploadDir->getCurrentPath(); // /var/www/html/wp-content/uploads/2024/01
```

## AttachmentMetadata

型安全なアタッチメントメタデータの処理を行います。

```php
$metadata = $attachment->getMetadata();

$width = $metadata->getWidth();
$height = $metadata->getHeight();
$file = $metadata->getFile();            // 2024/01/image.jpg

// 登録済みサイズ
$sizes = $metadata->getSizes();
foreach ($sizes as $sizeName => $sizeData) {
    $sizeData->getWidth();
    $sizeData->getHeight();
    $sizeData->getFile();
    $sizeData->getMimeType();
}

// EXIF データ（画像の場合）
$exif = $metadata->getImageMeta();
$camera = $exif?->getCamera();
$aperture = $exif?->getAperture();
```

## 画像処理

WordPress の `WP_Image_Editor` をラップした画像操作機能を提供します。

```php
class ImageProcessor
{
    public function optimize(string $filePath, int $quality = 85): void
    {
        $image = wp_get_image_editor($filePath);

        if (is_wp_error($image)) {
            return;
        }

        // 品質の設定
        $image->set_quality($quality);

        // 最適化された画像を保存
        $image->save($filePath);
    }
}
```

### 商品画像の処理

```php
class ImageProcessingService
{
    public function __construct(
        private ImageProcessor $processor,
        private MediaRepository $media
    ) {}

    public function processProductImage(array $uploadedFile, int $productId): ProductImage
    {
        // 画像のアップロード
        $image = new ProductImage();
        $uploader = new FileUploader();
        $result = $uploader->upload($uploadedFile, $image);

        if (!$result->isSuccess()) {
            throw new UploadException($result->getError());
        }

        // 商品との関連付け
        $image->productId = $productId;

        // 画像の最適化
        $image->optimize();

        // 全サイズの生成
        $image->regenerateThumbnails();

        // データベースに保存
        $this->media->save($image);

        return $image;
    }
}
```

## クラウドストレージ統合

### S3StoragePlugin

`wppack/s3-storage-plugin` を使用すると、メディアファイルを Amazon S3 にオフロードできます。Media コンポーネントの API はそのままで、S3 へのアップロード/取得は内部的に処理されます。

```bash
composer require wppack/s3-storage-plugin
```

S3StoragePlugin が有効な場合：

- `$mediaManager->upload()` は S3 にアップロード
- `$attachment->getUrl()` は S3（または CloudFront）の URL を返却
- ローカルファイルの自動削除（オプション）
- 既存メディアの S3 への移行コマンド

## メディアクエリ

### アタッチメントのクエリ

```php
class MediaGalleryService
{
    public function __construct(
        private MediaRepository $media
    ) {}

    public function getProductImages(int $productId): array
    {
        return $this->media
            ->ofType(ProductImage::class)
            ->whereMeta('product_id', $productId)
            ->orderByMeta('is_primary', 'desc')
            ->orderBy('created', 'asc')
            ->get();
    }

    public function getFeaturedImages(int $limit = 10): array
    {
        return $this->media
            ->ofType(ImageAttachment::class)
            ->whereMeta('is_featured', true)
            ->whereImageSize('width', '>=', 1200)
            ->orderBy('created', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getRecentDocuments(int $days = 7): array
    {
        $since = new DateTime("-{$days} days");

        return $this->media
            ->ofType(DocumentAttachment::class)
            ->whereDate('created', '>=', $since)
            ->orderBy('created', 'desc')
            ->get();
    }
}
```

### ギャラリーの表示

```php
$galleryService = $container->get(MediaGalleryService::class);
$images = $galleryService->getProductImages($productId);

if (!empty($images)) : ?>
    <div class="product-gallery">
        <?php foreach ($images as $image) : ?>
            <div class="gallery-item <?php echo $image->isPrimary ? 'primary' : ''; ?>">
                <img src="<?php echo esc_url($image->getUrl('medium')); ?>"
                     alt="<?php echo esc_attr($image->altText); ?>"
                     data-full="<?php echo esc_url($image->getUrl()); ?>">
                <?php if ($image->caption) : ?>
                    <p class="caption"><?php echo esc_html($image->caption); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
```

## メディアライブラリの拡張

高度なクエリと整理機能を提供します。

```php
class MediaLibraryService
{
    public function getLibraryItems(array $filters = []): array
    {
        $query = $this->media->newQuery();

        if (isset($filters['type'])) {
            $query->whereType($filters['type']);
        }

        if (isset($filters['date_range'])) {
            $query->whereDateBetween('created', $filters['date_range']['start'], $filters['date_range']['end']);
        }

        if (isset($filters['search'])) {
            $query->whereSearch($filters['search']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }
}
```

## AJAX アップロード

### AJAX ハンドラー

```php
class AjaxUploadHandler
{
    public function __construct(
        private FileUploader $uploader,
        private MediaRepository $media
    ) {}

    #[Action('wp_ajax_upload_image')]
    public function onWpAjaxUploadImage(): void
    {
        check_ajax_referer('upload_image', 'nonce');

        if (!isset($_FILES['file'])) {
            wp_send_json_error(['message' => 'No file uploaded']);
        }

        try {
            $image = new ImageAttachment();
            $result = $this->uploader->upload($_FILES['file'], $image);

            if (!$result->isSuccess()) {
                wp_send_json_error(['message' => $result->getError()]);
            }

            // POST からメタデータを設定
            $image->altText = sanitize_text_field($_POST['alt_text'] ?? '');

            // 保存
            $this->media->save($image);

            // 成功レスポンスを返却
            wp_send_json_success([
                'id' => $image->getId(),
                'url' => $image->getUrl(),
                'thumbnail' => $image->getUrl('thumbnail'),
                'alt' => $image->altText
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
```

### JavaScript アップロード

```javascript
jQuery(document).ready(function($) {
    $('#upload-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData();
        formData.append('action', 'upload_image');
        formData.append('nonce', wppack_ajax.nonce);
        formData.append('file', $('#image-file')[0].files[0]);
        formData.append('alt_text', $('#alt-text').val());

        $.ajax({
            url: wppack_ajax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#preview').html(
                        '<img src="' + response.data.thumbnail + '" alt="' + response.data.alt + '">'
                    );
                } else {
                    alert('Upload failed: ' + response.data.message);
                }
            },
            error: function() {
                alert('Upload failed');
            }
        });
    });
});
```

## Named Hook アトリビュート

Media コンポーネントは、WordPress メディア処理機能のための Named Hook アトリビュートを提供します。

### アップロードフック

#### #[WpHandleUploadFilter(priority?: int = 10)]

**WordPress Hook:** `wp_handle_upload`

```php
use WpPack\Component\Media\Attribute\WpHandleUploadFilter;
use WpPack\Component\Media\MediaProcessor;

class UploadHandler
{
    private MediaProcessor $processor;

    public function __construct(MediaProcessor $processor)
    {
        $this->processor = $processor;
    }

    #[WpHandleUploadFilter]
    public function processUpload(array $file): array
    {
        // ファイルタイプをより厳密にバリデーション
        if (!$this->isAllowedFileType($file['type'], $file['file'])) {
            $file['error'] = __('This file type is not allowed.', 'wppack');
            return $file;
        }

        // アップロード時に画像を最適化
        if ($this->processor->isImage($file['type'])) {
            $optimized = $this->processor->optimizeImage($file['file']);
            if ($optimized) {
                $file['file'] = $optimized;
            }
        }

        return $file;
    }

    private function isAllowedFileType(string $mime_type, string $file_path): bool
    {
        $allowed_types = get_allowed_mime_types();
        $detected_type = $this->processor->detectMimeType($file_path);

        return isset($allowed_types[$detected_type]) && $mime_type === $detected_type;
    }
}
```

#### #[WpHandleUploadPrefilterFilter(priority?: int = 10)]

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

### 画像処理フック

#### #[WpGenerateAttachmentMetadataFilter(priority?: int = 10)]

**WordPress Hook:** `wp_generate_attachment_metadata`

```php
use WpPack\Component\Media\Attribute\WpGenerateAttachmentMetadataFilter;

class AttachmentMetadataProcessor
{
    #[WpGenerateAttachmentMetadataFilter]
    public function enhanceMetadata(array $metadata, int $attachment_id): array
    {
        // EXIF データの追加
        $exif = $this->extractExifData($attachment_id);
        if ($exif) {
            $metadata['image_meta'] = array_merge($metadata['image_meta'] ?? [], $exif);
        }

        return $metadata;
    }
}
```

#### #[IntermediateSizesAdvancedFilter(priority?: int = 10)]

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

        // WebP バージョンの追加
        if ($this->supportsWebP()) {
            foreach ($sizes as $name => $size) {
                $sizes[$name . '_webp'] = array_merge($size, ['mime_type' => 'image/webp']);
            }
        }

        // レスポンシブサイズの追加
        $sizes['responsive_small'] = ['width' => 480, 'height' => 9999, 'crop' => false];
        $sizes['responsive_medium'] = ['width' => 768, 'height' => 9999, 'crop' => false];
        $sizes['responsive_large'] = ['width' => 1024, 'height' => 9999, 'crop' => false];

        // アップロードコンテキストに基づいてサイズを削除
        if ($this->isProfileImage($metadata)) {
            foreach ($sizes as $name => $size) {
                if ($size['crop'] !== true || $size['width'] !== $size['height']) {
                    unset($sizes[$name]);
                }
            }
        }

        return $sizes;
    }

    private function supportsWebP(): bool
    {
        return function_exists('imagewebp') &&
               (imagetypes() & IMG_WEBP) === IMG_WEBP;
    }
}
```

### メディアライブラリフック

#### #[AjaxQueryAttachmentsArgsFilter(priority?: int = 10)]

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

#### #[MediaUploadTabsFilter(priority?: int = 10)]

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

### アタッチメント表示フック

#### #[WpGetAttachmentImageAttributesFilter(priority?: int = 10)]

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

        // ライトボックス用の data 属性を追加
        if ($this->shouldEnableLightbox($attachment)) {
            $attr['data-lightbox'] = 'gallery';
            $attr['data-title'] = $attachment->post_title;
            $attr['data-caption'] = $attachment->post_excerpt;
        }

        // CSS 用のアスペクト比を追加
        $metadata = wp_get_attachment_metadata($attachment->ID);
        if ($metadata) {
            $attr['data-aspect-ratio'] = $metadata['width'] / $metadata['height'];
            $attr['style'] = sprintf('aspect-ratio: %s;', $attr['data-aspect-ratio']);
        }

        return $attr;
    }

    private function shouldEnableLightbox(\WP_Post $attachment): bool
    {
        return !wp_is_mobile() &&
               in_array($attachment->post_mime_type, ['image/jpeg', 'image/png', 'image/gif']);
    }
}
```

## 実践例：完全なメディア管理システム

```php
use WpPack\Component\Hook\Attribute\InitAction;
use WpPack\Component\Media\Attribute\WpHandleUploadFilter;
use WpPack\Component\Media\Attribute\WpGenerateAttachmentMetadataFilter;
use WpPack\Component\Media\MediaService;
use WpPack\Component\Media\ImageOptimizer;

class WpPackMediaSystem
{
    private MediaService $service;
    private ImageOptimizer $optimizer;
    private Logger $logger;

    public function __construct(
        MediaService $service,
        ImageOptimizer $optimizer,
        Logger $logger
    ) {
        $this->service = $service;
        $this->optimizer = $optimizer;
        $this->logger = $logger;
    }

    #[InitAction]
    public function initializeMediaSystem(): void
    {
        $this->registerImageSizes();
        $this->registerMediaTaxonomies();
        $this->configureUploadPaths();
    }

    #[WpHandleUploadFilter]
    public function processMediaUpload(array $file): array
    {
        try {
            // セキュリティスキャン
            if (!$this->service->isSafeFile($file['file'])) {
                throw new \Exception('Security threat detected');
            }

            // ファイルタイプに基づいて処理
            $processor = $this->service->getProcessor($file['type']);
            $processed = $processor->process($file['file']);

            // 画像の場合は最適化
            if ($this->service->isImage($file['type'])) {
                $optimized = $this->optimizer->optimize($processed, [
                    'quality' => 85,
                    'strip_metadata' => false,
                    'convert_to_webp' => true,
                ]);

                if ($optimized) {
                    $file['file'] = $optimized;
                    $file['type'] = 'image/webp';
                }
            }

            // 一意のファイル名を生成
            $file['name'] = $this->generateUniqueFilename($file['name']);

            $this->logger->info('Media uploaded successfully', [
                'filename' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type'],
            ]);

        } catch (\Exception $e) {
            $file['error'] = $e->getMessage();
            $this->logger->error('Media upload failed', [
                'error' => $e->getMessage(),
                'file' => $file['name'],
            ]);
        }

        return $file;
    }

    #[WpGenerateAttachmentMetadataFilter]
    public function enrichMetadata(array $metadata, int $attachment_id): array
    {
        // EXIF データの追加処理
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

## アタッチメントアトリビュートクイックリファレンス

```php
// アタッチメント設定
#[Attachment(
    allowedTypes: ['image/jpeg', 'image/png'],
    maxSize: '5MB',
    directory: 'custom-dir',
    generateThumbnails: true
)]

// アタッチメントメタフィールド
#[AttachmentMeta('field_name', type: 'string|integer|boolean|array', required: false)]

// 画像サイズ
#[ImageSizes([
    'name' => [width, height, crop],
    'thumbnail' => [150, 150, true],
    'medium' => [300, 300, false]
])]
```

## 主要メソッド

```php
// アタッチメントメソッド
$attachment->getUrl();
$attachment->getFilePath();
$attachment->getMimeType();
$attachment->getFileSize();
$attachment->isImage();
$attachment->save();
$attachment->delete();

// 画像メソッド
$image->optimize();
$image->resize($width, $height, $crop);
$image->regenerateThumbnails();
$image->getUrl('thumbnail');

// クエリメソッド
$media->ofType(ImageAttachment::class)
      ->whereMeta('key', 'value')
      ->whereType('image/jpeg')
      ->orderBy('created', 'desc')
      ->limit(10)
      ->get();
```

## テスト

```php
use PHPUnit\Framework\TestCase;

class ImageAttachmentTest extends TestCase
{
    private MediaRepository $media;
    private FileUploader $uploader;

    protected function setUp(): void
    {
        $this->media = $this->createMock(MediaRepository::class);
        $this->uploader = $this->createMock(FileUploader::class);
    }

    public function testImageValidation(): void
    {
        $image = new ImageAttachment();
        $image->altText = '';

        $errors = $image->validate();

        $this->assertContains('Alt text is required for accessibility', $errors);
    }

    public function testImageUpload(): void
    {
        $uploadedFile = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/test.jpg',
            'error' => 0,
            'size' => 1024000
        ];

        $this->uploader->expects($this->once())
            ->method('upload')
            ->willReturn(new UploadResult(true));

        $handler = new MediaUploadHandler($this->uploader, $this->media);
        $image = $handler->handleUpload($uploadedFile);

        $this->assertInstanceOf(ImageAttachment::class, $image);
    }
}
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `MediaManager` | メディア操作のエントリーポイント |
| `Attachment` | アタッチメントオブジェクト |
| `AbstractAttachment` | 型付きアタッチメント定義の基底クラス |
| `AttachmentMetadata` | 型安全なメタデータ |
| `ImageSize` | カスタム画像サイズの定義 |
| `Upload\UploadedFile` | アップロードファイルのラッパー |
| `Upload\UploadDir` | アップロードディレクトリ情報 |
| `ImageMeta` | EXIF / 画像メタデータ |
| `FileUploader` | ファイルアップロードプロセッサ |
| `MediaRepository` | アタッチメントのクエリと永続化 |
| `ImageProcessor` | 画像操作と最適化 |

## このコンポーネントの使用場面

**最適な用途：**
- 複雑なメディア要件を持つアプリケーション
- 画像の最適化と処理が必要なプロジェクト
- 型安全なアタッチメントメタデータの管理
- メディアのバリデーションが必要なプロジェクト

**代替を検討すべき場合：**
- デフォルトの WordPress 動作を使用したシンプルなファイルアップロード
- カスタムメタなしの基本的なメディアライブラリの使用
- 画像処理が不要なプロジェクト
- WordPress のデフォルトで十分な場合

## WordPress 統合

- **メディアライブラリ** - wp_posts と postmeta を拡張
- **アップロード API** - wp_handle_upload をラップ
- **画像エディター** - wp_get_image_editor を使用
- **アタッチメント API** - wp_insert_attachment を拡張
- **ファイルシステム** - WordPress ファイルシステム API を使用
- **フック & フィルター** - すべてのメディア関連アクション/フィルター

## セキュリティ機能

- **ファイルタイプバリデーション** - 悪意のあるアップロードを防止
- **サイズ制限** - リソース使用量を制限
- **アクセス制御** - きめ細かな権限設定

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress フック登録用

### 推奨
- **Filesystem コンポーネント** - ファイル操作用
- **DependencyInjection コンポーネント** - サービス注入用
