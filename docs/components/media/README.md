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

→ [Hook コンポーネントのドキュメント](../hook/media.md) を参照してください。
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
