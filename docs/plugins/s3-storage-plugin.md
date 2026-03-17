# S3StoragePlugin

WordPress のメディアストレージを Amazon S3 に置き換えるプラグイン。Pre-signed URL によるブラウザ直接アップロードと、SQS 経由の非同期 attachment 登録を提供する。

## 概要

S3StoragePlugin は S3 固有の機能を提供する薄いレイヤーです。汎用的な機能は下位コンポーネントに分離されています:

| 機能 | 責務の所在 |
|------|-----------|
| Stream Wrapper (`s3://` プロトコル) | [Storage コンポーネント](../components/storage/stream-wrapper.md) `StorageStreamWrapper` |
| WordPress フック統合（upload_dir, attachment URL 等） | [Media コンポーネント](../components/media/storage.md) Subscriber 群 |
| S3 アダプタ | [S3Storage Bridge](../components/storage/s3-storage.md) `S3StorageAdapter` |
| Pre-signed URL | S3StoragePlugin |
| S3 イベント処理 | S3StoragePlugin |
| S3 固有の設定 | S3StoragePlugin |

S3StoragePlugin が担うのは以下の S3 固有機能のみです:

- **Pre-signed URL**: ブラウザから S3 に直接アップロード（サーバー負荷なし）
- **S3 イベント処理**: S3 イベント → SQS → Lambda での非同期 attachment 登録
- **S3 固有設定**: `S3StorageConfiguration`（環境変数から S3 接続設定を生成）
- **サービス組み立て**: DI コンテナで Storage / Media コンポーネントを S3 向けに組み立て

## アーキテクチャ

### レイヤー構成

```
S3StoragePlugin（薄いレイヤー）
├── Configuration/
│   └── S3StorageConfiguration         ← S3 固有設定、環境変数から生成
├── PreSignedUrl/
│   ├── PreSignedUrlGenerator          ← Pre-signed PUT URL 生成
│   ├── PreSignedUrlController         ← REST API エンドポイント
│   └── UploadPolicy                   ← ファイルタイプ・サイズ制限
├── Message/
│   ├── S3ObjectCreatedMessage         ← S3 イベント DTO
│   ├── GenerateThumbnailsMessage      ← サムネイル生成メッセージ
│   └── S3EventNormalizer              ← S3 Event Notification パーサー
├── Handler/
│   ├── S3ObjectCreatedHandler         ← attachment 登録（リサイズ画像スキップ付き）
│   └── GenerateThumbnailsHandler      ← サムネイル非同期生成
└── DependencyInjection/
    └── S3StoragePluginServiceProvider ← サービス組み立て

Storage コンポーネント（プロバイダ非依存）
├── StreamWrapper\StorageStreamWrapper ← stream_wrapper_register
├── StreamWrapper\StatCache            ← url_stat キャッシュ
├── Adapter\StorageAdapterInterface    ← ストレージコントラクト
└── Bridge\S3\S3StorageAdapter         ← S3 アダプタ実装

Media コンポーネント（WordPress 統合）
├── Storage\Subscriber\UploadDirSubscriber      ← upload_dir フィルタ
├── Storage\Subscriber\AttachmentSubscriber     ← attachment 関連フック
├── Storage\Subscriber\ImageEditorSubscriber    ← StorageImageEditor 差し込み
├── Storage\ImageEditor\StorageImageEditor      ← stream wrapper 対応画像エディタ
├── Storage\Command\MigrateCommand              ← 移行コマンド
├── Storage\StorageConfiguration                ← ストレージ設定 VO
└── Storage\UrlResolver                         ← URL 解決
```

### アップロードフロー

```
┌─ ブラウザ直接アップロード ────────────────────┐
│                                                │
│  Browser                                       │
│    → REST API (PreSignedUrlController)          │
│    → Pre-signed PUT URL 取得                    │
│    → Direct upload to S3                       │
│                                                │
└────────────────────────────────────────────────┘
            ↓ S3 Event Notification
┌─ メッセージキュー ───────────────────────────┐
│ Amazon SQS                                     │
└────────────────────────────────────────────────┘
            ↓ WpPack\Component\Messenger
┌─ 非同期処理 (Lambda) ────────────────────────┐
│                                                │
│ S3ObjectCreatedHandler                         │
│   → リサイズ画像の判定（スキップ）              │
│   → マルチサイト対応 (switch_to_blog)           │
│   → wp_insert_attachment()                     │
│   → GenerateThumbnailsMessage をディスパッチ    │
│                                                │
│ GenerateThumbnailsHandler                      │
│   → get_attached_file() → stream wrapper 経由   │
│   → StorageImageEditor でサムネイル生成         │
│   → wp_update_attachment_metadata()            │
│                                                │
└────────────────────────────────────────────────┘
```

### ファイルアクセスフロー

```
WordPress コア / プラグイン
    │
    ├─ get_attached_file($id)
    │     → Media: AttachmentSubscriber
    │     → s3://bucket/uploads/2024/01/photo.jpg
    │
    ├─ file_exists() / file_get_contents() / fopen()
    │     → Storage: StorageStreamWrapper (s3:// プロトコル)
    │     → Storage: S3StorageAdapter
    │     → Amazon S3
    │
    └─ wp_get_attachment_url($id)
          → Media: AttachmentSubscriber
          → https://cdn.example.com/uploads/2024/01/photo.jpg
```

## 依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| wppack/storage | ストレージ抽象化（`StorageAdapterInterface`, `StorageStreamWrapper`） |
| wppack/s3-storage | S3 アダプタ（`S3StorageAdapter`） |
| wppack/media | WordPress メディア統合（Subscriber 群, `StorageImageEditor`） |
| wppack/hook | WordPress フック統合 |
| wppack/messenger | メッセージバス・ハンドラ基盤（SQS 経由の非同期処理） |
| async-aws/s3 | S3 API（Pre-signed URL 生成含む） |

## 名前空間

```
WpPack\Plugin\S3StoragePlugin\
```

## 主要クラス

### Pre-signed URL

#### PreSignedUrlGenerator

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

#### PreSignedUrlController

REST API エンドポイント。認証済みユーザーに Pre-signed URL を発行する。

```php
namespace WpPack\Plugin\S3StoragePlugin\PreSignedUrl;

final class PreSignedUrlController
{
    /**
     * POST /wp-json/wppack/v1/s3/presigned-url
     *
     * Request: { "filename": "photo.jpg", "content_type": "image/jpeg", "content_length": 1048576 }
     * Response: { "url": "https://...", "key": "uploads/2024/01/photo.jpg", "expires_in": 3600 }
     */
    public function handleRequest(\WP_REST_Request $request): \WP_REST_Response;
}
```

#### UploadPolicy

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

### S3 イベント処理

#### S3ObjectCreatedHandler

S3 オブジェクト作成イベントを処理し、WordPress の attachment として登録する。

```php
namespace WpPack\Plugin\S3StoragePlugin\Handler;

#[AsMessageHandler]
final readonly class S3ObjectCreatedHandler
{
    public function __invoke(S3ObjectCreatedMessage $message): void;
    public function isResizedImage(string $key): bool;
    public function parseBlogId(string $key): int;
}
```

**リサイズ画像のスキップ:** S3 Event Notification はサムネイル生成時にも発火するため、`isResizedImage()` でリサイズ画像（派生ファイル）を検出してスキップします。以下のパターンにマッチする場合、新しい attachment は作成されません:

| パターン | 例 | 説明 |
|---------|------|------|
| `-{width}x{height}` | `photo-100x200.jpg` | WordPress サムネイル |
| `-scaled` | `photo-scaled.jpg` | WordPress の大画像スケーリング |
| `-rotated` | `photo-rotated.png` | 画像回転 |
| `-e{timestamp}` | `photo-e1234567890.jpg` | 画像編集のタイムスタンプ |

#### GenerateThumbnailsHandler

Lambda 上でサムネイル画像を生成する。`get_attached_file()` → stream wrapper 経由で S3 からダウンロード、`StorageImageEditor` でリサイズ、stream wrapper 経由で S3 に書き戻します。

```php
namespace WpPack\Plugin\S3StoragePlugin\Handler;

#[AsMessageHandler]
final class GenerateThumbnailsHandler
{
    public function __invoke(GenerateThumbnailsMessage $message): void;
}
```

#### S3EventNormalizer

S3 Event Notification JSON を `S3ObjectCreatedMessage` にパースする。

```php
namespace WpPack\Plugin\S3StoragePlugin\Message;

final class S3EventNormalizer
{
    /** @return list<S3ObjectCreatedMessage> */
    public function normalize(array $event): array;
}
```

### マルチサイト対応

S3ObjectCreatedHandler はマルチサイト環境を自動検出します:

1. S3 キーのパスから `/sites/{blog_id}/` パターンを解析
2. `switch_to_blog()` でブログコンテキストを切り替え
3. 該当ブログの attachment として登録
4. `restore_current_blog()` で元に戻す

```
uploads/2024/01/photo.jpg         → blog_id: 1（メインサイト）
uploads/sites/2/2024/01/photo.jpg → blog_id: 2
uploads/sites/3/2024/01/photo.jpg → blog_id: 3
```

### S3StorageConfiguration

S3 固有の設定。環境変数から生成し、`StorageConfiguration`（Media コンポーネント）に変換可能。

```php
namespace WpPack\Plugin\S3StoragePlugin\Configuration;

final readonly class S3StorageConfiguration
{
    public static function fromEnvironment(): self;
    public function toStorageConfiguration(): StorageConfiguration;
}
```

### Admin\SettingsPage

管理画面の設定ページ。S3 バケット設定、CDN URL、同期状態を表示する。

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
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;

$adapter = $container->get(StorageAdapterInterface::class);
$adapter->write(
    key: 'uploads/2024/01/document.pdf',
    contents: file_get_contents($localPath),
    metadata: ['Content-Type' => 'application/pdf'],
);
```

### CDN URL の解決

```php
use WpPack\Component\Media\Storage\UrlResolver;

$resolver = $container->get(UrlResolver::class);

// CDN_URL が設定されていれば CDN URL を返す
// 未設定の場合は S3 直接 URL を返す
$url = $resolver->resolve('uploads/2024/01/photo.jpg');
// => https://cdn.example.com/uploads/2024/01/photo.jpg
```

## 関連ドキュメント

- [Storage コンポーネント](../components/storage/README.md)
- [StorageStreamWrapper](../components/storage/stream-wrapper.md)
- [S3Storage Bridge](../components/storage/s3-storage.md)
- [Media Storage 連携](../components/media/storage.md)
