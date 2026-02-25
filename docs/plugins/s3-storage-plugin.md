# S3StoragePlugin

WordPress のメディアストレージを Amazon S3 に置き換えるプラグイン。Pre-signed URL によるブラウザ直接アップロードと、SQS 経由の非同期 attachment 登録を提供する。

## 概要

S3StoragePlugin は WordPress のメディア管理を S3 ベースに変更し、以下を実現します:

- **ブラウザ直接アップロード**: Pre-signed URL で S3 に直接アップロード（サーバー負荷なし）
- **非同期 attachment 登録**: S3 イベント → SQS → Lambda でメタデータ生成
- **S3 Stream Wrapper**: `s3://` プロトコルで PHP 標準ファイル関数から透過的に S3 にアクセス
- **CDN 対応**: CloudFront 等の CDN 経由でメディア配信
- **WordPress 互換**: 既存のメディアライブラリ UI をそのまま使用可能

## アーキテクチャ

```
┌─ アップロード ──────────────────────────────┐
│                                              │
│  Browser                                     │
│    → REST API (PreSignedUrlController)        │
│    → Pre-signed PUT URL 取得                  │
│    → Direct upload to S3                     │
│                                              │
└──────────────────────────────────────────────┘
            ↓ S3 Event Notification
┌─ メッセージキュー ─────────────────────────┐
│ Amazon SQS                                   │
└──────────────────────────────────────────────┘
            ↓ WpPack\Component\Messenger
┌─ 非同期処理 ────────────────────────────────┐
│ Lambda (Bref WordPress)                      │
│                                              │
│ S3ObjectCreatedHandler                       │
│   → wp_insert_attachment()                   │
│   → attachment メタデータ生成                 │
│                                              │
│ GenerateThumbnailsHandler                    │
│   → サムネイル生成（各サイズ）                 │
│   → S3 にアップロード                         │
│                                              │
└──────────────────────────────────────────────┘
```

### 配信フロー

```
Browser → CDN (CloudFront) → S3
         or
Browser → S3 直接
```

### ファイルアクセスフロー

```
WordPress コア / プラグイン
    │
    ├─ get_attached_file($id)
    │     → GetAttachedFileFilter
    │     → s3://bucket/uploads/2024/01/photo.jpg
    │
    ├─ file_exists() / file_get_contents() / fopen()
    │     → S3StreamWrapper (s3:// プロトコル)
    │     → AsyncAWS S3Client
    │     → Amazon S3
    │
    └─ wp_get_attachment_url($id)
          → AttachmentUrlFilter
          → https://cdn.example.com/uploads/2024/01/photo.jpg
```

## 依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| wppack/hook | WordPress フック統合 |
| wppack/media | メディア処理ユーティリティ |
| wppack/filesystem | ファイルシステム操作 |
| wppack/messenger | メッセージバス・ハンドラ基盤（SQS 経由の非同期処理） |
| async-aws/s3 | S3 API（Pre-signed URL 生成含む） |

## 名前空間

```
WpPack\Plugin\S3StoragePlugin\
```

## 主要クラス

### PreSignedUrl\PreSignedUrlGenerator

AsyncAWS S3 を使用して Pre-signed PUT URL を生成する。

```php
namespace WpPack\Plugin\S3StoragePlugin\PreSignedUrl;

final class PreSignedUrlGenerator
{
    public function __construct(
        private readonly S3Client $s3Client,
        private readonly string $bucket,
        private readonly string $prefix,
    ) {}

    public function generate(
        string $filename,
        string $contentType,
        int $contentLength,
        int $expiresIn = 3600,
    ): PreSignedUrlResult;
}
```

### PreSignedUrl\PreSignedUrlController

REST API エンドポイント。認証済みユーザーに Pre-signed URL を発行する。

```php
namespace WpPack\Plugin\S3StoragePlugin\PreSignedUrl;

final class PreSignedUrlController
{
    public function register(): void;  // register_rest_route()

    /**
     * POST /wp-json/wppack/v1/s3/presigned-url
     *
     * Request: { "filename": "photo.jpg", "content_type": "image/jpeg", "content_length": 1048576 }
     * Response: { "url": "https://...", "key": "uploads/2024/01/photo.jpg", "expires_in": 3600 }
     */
    public function handleRequest(\WP_REST_Request $request): \WP_REST_Response;
}
```

### PreSignedUrl\UploadPolicy

ファイルタイプ・サイズの制限ポリシー。WordPress の許可ファイルタイプと連動する。

```php
namespace WpPack\Plugin\S3StoragePlugin\PreSignedUrl;

final class UploadPolicy
{
    public function isAllowedType(string $contentType): bool;
    public function isAllowedSize(int $contentLength): bool;
    public function getMaxFileSize(): int;
    /** @return list<string> */
    public function getAllowedMimeTypes(): array;
}
```

### StreamWrapper\S3StreamWrapper

PHP の stream wrapper（`s3://` プロトコル）を実装し、標準ファイル関数から S3 オブジェクトに透過的にアクセスする。WordPress コアや他プラグインが `file_exists()`、`file_get_contents()`、`fopen()` 等で添付ファイルにアクセスする際の互換性を確保する。

```php
namespace WpPack\Plugin\S3StoragePlugin\StreamWrapper;

final class S3StreamWrapper
{
    private const PROTOCOL = 's3';

    public static function register(S3Client $s3Client): void;
    public static function unregister(): void;

    // PHP StreamWrapper interface
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool;
    public function stream_read(int $count): string|false;
    public function stream_write(string $data): int;
    public function stream_close(): void;
    public function stream_eof(): bool;
    public function stream_stat(): array|false;
    public function stream_seek(int $offset, int $whence = SEEK_SET): bool;
    public function stream_tell(): int;
    public function url_stat(string $path, int $flags): array|false;
    public function unlink(string $path): bool;
    public function rename(string $pathFrom, string $pathTo): bool;
    public function mkdir(string $path, int $mode, int $options): bool;
}
```

stream wrapper の登録により、以下の PHP 標準関数が S3 上で動作する:

```php
// s3://bucket/uploads/2024/01/photo.jpg に対して
file_exists('s3://my-bucket/uploads/2024/01/photo.jpg');  // S3 HEAD リクエスト
file_get_contents('s3://my-bucket/uploads/2024/01/photo.jpg');  // S3 GET
file_put_contents('s3://my-bucket/uploads/2024/01/photo.jpg', $data);  // S3 PUT
is_readable('s3://my-bucket/uploads/2024/01/photo.jpg');  // S3 HEAD
filesize('s3://my-bucket/uploads/2024/01/photo.jpg');  // S3 HEAD (Content-Length)
unlink('s3://my-bucket/uploads/2024/01/photo.jpg');  // S3 DELETE

// fopen/fread/fwrite も動作
$fp = fopen('s3://my-bucket/uploads/2024/01/photo.jpg', 'r');
$content = fread($fp, 1024);
fclose($fp);
```

### Storage\S3StorageAdapter

AsyncAWS S3 クライアントのラッパー。オブジェクトの CRUD 操作を提供する。

```php
namespace WpPack\Plugin\S3StoragePlugin\Storage;

final class S3StorageAdapter
{
    public function put(string $key, string $body, string $contentType): void;
    public function get(string $key): string;
    public function delete(string $key): void;
    public function exists(string $key): bool;
    public function copy(string $sourceKey, string $destinationKey): void;
    public function getUrl(string $key): string;
}
```

### Storage\S3UrlResolver

S3 キーを CDN URL または S3 直接 URL に変換する。

```php
namespace WpPack\Plugin\S3StoragePlugin\Storage;

final class S3UrlResolver
{
    public function __construct(
        private readonly string $bucket,
        private readonly string $region,
        private readonly ?string $cdnUrl = null,
    ) {}

    public function resolve(string $key): string;
}
```

### WordPress\GetAttachedFileFilter

`get_attached_file` フィルタをフックし、ローカルパスを `s3://` パスに書き換える。これにより WordPress コアや他プラグインが `get_attached_file()` 経由でファイルにアクセスする際、stream wrapper を通じて S3 から透過的に読み取れる。

```php
namespace WpPack\Plugin\S3StoragePlugin\WordPress;

final class GetAttachedFileFilter
{
    public function __construct(
        private readonly string $bucket,
        private readonly string $prefix,
    ) {}

    public function register(): void;

    /**
     * ローカルパスを s3:// パスに変換する。
     *
     * /var/www/html/wp-content/uploads/2024/01/photo.jpg
     *   → s3://my-bucket/uploads/2024/01/photo.jpg
     */
    public function filter(string $file, int $attachmentId): string;
}
```

### WordPress\UploadDirFilter

`upload_dir` フィルタをフックし、アップロードパスを S3 キーに変更する。

```php
namespace WpPack\Plugin\S3StoragePlugin\WordPress;

final class UploadDirFilter
{
    public function register(): void;
    /** @param array<string, mixed> $uploads */
    public function filter(array $uploads): array;
}
```

### WordPress\AttachmentUrlFilter

添付ファイル URL を S3/CDN URL に書き換える。

```php
namespace WpPack\Plugin\S3StoragePlugin\WordPress;

final class AttachmentUrlFilter
{
    public function register(): void;
    public function filter(string $url, int $postId): string;
}
```

### WordPress\ImageEditorAdapter

WordPress の WP_Image_Editor を S3 対応にするアダプタ。サムネイル生成時に S3 への書き込みを行う。

```php
namespace WpPack\Plugin\S3StoragePlugin\WordPress;

final class ImageEditorAdapter
{
    public function register(): void;
}
```

### WordPress\DeleteAttachmentHandler

WordPress で添付ファイルが削除された際に、対応する S3 オブジェクトを削除する。

```php
namespace WpPack\Plugin\S3StoragePlugin\WordPress;

final class DeleteAttachmentHandler
{
    public function register(): void;
    public function handle(int $postId): void;
}
```

### Message\S3ObjectCreatedMessage

S3 にオブジェクトが作成された際の SQS メッセージ。マーカーアトリビュートを持たないプレーンな DTO クラス。

```php
namespace WpPack\Plugin\S3StoragePlugin\Message;

final class S3ObjectCreatedMessage
{
    public function __construct(
        public readonly string $bucket,
        public readonly string $key,
        public readonly int $size,
        public readonly string $contentType,
    ) {}
}
```

### Handler\S3ObjectCreatedHandler

S3 オブジェクト作成イベントを処理し、WordPress の attachment として登録する。

```php
namespace WpPack\Plugin\S3StoragePlugin\Handler;

use WpPack\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class S3ObjectCreatedHandler
{
    public function __invoke(S3ObjectCreatedMessage $message): void
    {
        // 1. S3 キーからファイル情報を取得
        // 2. wp_insert_attachment() で attachment を登録
        // 3. attachment メタデータを生成・保存
        // 4. サムネイル生成メッセージをディスパッチ
    }
}
```

### Handler\GenerateThumbnailsHandler

Lambda 上でサムネイル画像を生成し、S3 にアップロードする。

```php
namespace WpPack\Plugin\S3StoragePlugin\Handler;

use WpPack\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateThumbnailsHandler
{
    public function __invoke(GenerateThumbnailsMessage $message): void
    {
        // 1. S3 から元画像を取得
        // 2. WordPress の画像サイズ定義に基づきリサイズ
        // 3. 各サイズを S3 にアップロード
        // 4. attachment メタデータを更新
    }
}
```

### Admin\SettingsPage

管理画面の設定ページ。S3 バケット設定、CDN URL、同期状態を表示する。

```php
namespace WpPack\Plugin\S3StoragePlugin\Admin;

final class SettingsPage
{
    public function register(): void;
    public function render(): void;
}
```

### Command\MigrateCommand

ローカルファイルシステムから S3 へメディアを移行する WP-CLI コマンド。

```bash
# 全メディアを S3 に移行
wp wppack s3 migrate

# ドライランで移行対象を確認
wp wppack s3 migrate --dry-run

# バッチサイズを指定して移行
wp wppack s3 migrate --batch-size=100
```

## 環境変数

```bash
# AWS 認証情報
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_REGION=ap-northeast-1

# S3 設定
S3_BUCKET=my-wordpress-media
S3_REGION=ap-northeast-1          # 省略時は AWS_REGION を使用
S3_PREFIX=uploads                  # S3 キーのプレフィックス

# CDN 設定（オプション）
CDN_URL=https://cdn.example.com
```

## 使用例

### Pre-signed URL によるアップロード

```javascript
// 1. Pre-signed URL を取得
const response = await fetch('/wp-json/wppack/v1/s3/presigned-url', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce,
    },
    body: JSON.stringify({
        filename: file.name,
        content_type: file.type,
        content_length: file.size,
    }),
});

const { url, key } = await response.json();

// 2. S3 に直接アップロード
await fetch(url, {
    method: 'PUT',
    headers: { 'Content-Type': file.type },
    body: file,
});

// 3. S3 Event → SQS → Lambda で自動的に attachment が登録される
```

### PHP からのアップロード

```php
use WpPack\Plugin\S3StoragePlugin\Storage\S3StorageAdapter;

// S3 に直接アップロード
$adapter = $container->get(S3StorageAdapter::class);
$adapter->put(
    key: 'uploads/2024/01/document.pdf',
    body: file_get_contents($localPath),
    contentType: 'application/pdf',
);
```

### CDN URL の解決

```php
use WpPack\Plugin\S3StoragePlugin\Storage\S3UrlResolver;

$resolver = $container->get(S3UrlResolver::class);

// CDN_URL が設定されていれば CDN URL を返す
// 未設定の場合は S3 直接 URL を返す
$url = $resolver->resolve('uploads/2024/01/photo.jpg');
// => https://cdn.example.com/uploads/2024/01/photo.jpg
```
