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

## ユーティリティクラス

### AttachmentManager

WordPress の attachment 操作関数（`wp_insert_attachment()`、`wp_delete_attachment()`、`wp_prepare_attachment_for_js()` 等）をラップするクラス。`AttachmentManagerInterface` を実装しており、DI コンテナではインターフェースを型宣言に使用します。

```php
use WpPack\Component\Media\AttachmentManager;
use WpPack\Component\Media\AttachmentManagerInterface;
use WpPack\Component\Media\Exception\AttachmentException;
use WpPack\Component\PostType\PostRepository;

$attachment = new AttachmentManager(new PostRepository());

// Attachment の登録（失敗時は AttachmentException をスロー）
try {
    $id = $attachment->insert([
        'post_title' => 'My Image',
        'post_mime_type' => 'image/jpeg',
        'post_status' => 'inherit',
    ], '2024/01/photo.jpg');
} catch (AttachmentException $e) {
    // 登録失敗時の処理
}

// JavaScript 向けデータの準備
$data = $attachment->prepareForJs($id);

// Attachment ファイルパスの取得
$file = $attachment->getFile($id);

// メタデータの生成・更新
$metadata = $attachment->generateMetadata($id, $file);
$attachment->updateMetadata($id, $metadata);

// メタキーによる検索
$existingId = $attachment->findByMeta('_wp_attached_file', '2024/01/photo.jpg');

// Attachment の削除
$attachment->delete($id, force: true);
```

**主なメソッド:**

| メソッド | WordPress 関数 | 戻り値 | 説明 |
|---------|---------------|--------|------|
| `insert()` | `wp_insert_attachment()` | `int` (throws `AttachmentException`) | Attachment 登録 |
| `delete()` | `wp_delete_attachment()` | `?\WP_Post` | Attachment 削除 |
| `prepareForJs()` | `wp_prepare_attachment_for_js()` | `?array` | JS 向けデータ準備 |
| `getFile()` | `get_attached_file()` | `?string` | ファイルパス取得 |
| `generateMetadata()` | `wp_generate_attachment_metadata()` | `array` | メタデータ生成 |
| `updateMetadata()` | `wp_update_attachment_metadata()` | `bool` | メタデータ更新 |
| `getMetadata()` | `wp_get_attachment_metadata()` | `?array` | メタデータ取得 |
| `findByMeta()` | `PostRepositoryInterface::findOneByMeta()` | `?int` | メタキーで attachment 検索 |

## Hook アトリビュート

→ 詳細は [Hook コンポーネント — Media](../hook/media.md) を参照してください。

## 依存関係

### 必須
- **PostType コンポーネント** - `AttachmentManager` が `PostRepositoryInterface` に依存

### 推奨
- **Filesystem コンポーネント** - ファイル操作用
- **DependencyInjection コンポーネント** - サービス注入用

## 関連ドキュメント

- [Storage 連携](./storage.md) - オブジェクトストレージとの連携
