# S3StoragePlugin

WordPress のメディアストレージを Amazon S3 に置き換えるプラグイン。Pre-signed URL によるブラウザ直接アップロードと、SQS 経由の非同期 attachment 登録を提供する。

## 概要

S3StoragePlugin は S3 固有の機能を提供する薄いレイヤーです。汎用的な機能は下位コンポーネントに分離されています:

| 機能 | 責務の所在 |
|------|-----------|
| Stream Wrapper (`s3://` プロトコル) | [Storage コンポーネント](../components/storage/stream-wrapper.md) `StorageStreamWrapper` |
| WordPress フック統合（upload_dir, attachment URL 等） | [Media コンポーネント](../components/media/storage.md) Subscriber 群 |
| S3 アダプタ | [S3Storage Bridge](../components/storage/s3-storage.md) `S3StorageAdapter` |
| Pre-signed URL 生成 (`temporaryUploadUrl`) | [Storage コンポーネント](../components/storage/README.md) `StorageAdapterInterface` |
| Pre-signed URL REST API / ポリシー | S3StoragePlugin |
| S3 イベント処理 | S3StoragePlugin |
| S3 固有の設定 | S3StoragePlugin |
| プラグインブートストラップ | S3StoragePlugin (`PluginInterface`) |
| Attachment 同期登録 REST API | S3StoragePlugin (`RegisterAttachmentController`) |
| ブラウザ直接 S3 アップロード JS | S3StoragePlugin (`s3-upload.js` + `AdminAssetSubscriber`) |

S3StoragePlugin が担うのは以下の S3 固有機能のみです:

- **Pre-signed URL REST API**: Storage コンポーネントの `temporaryUploadUrl()` を利用し、REST エンドポイントとアップロードポリシーを提供
- **S3 イベント処理**: S3 イベント → SQS → Lambda での非同期 attachment 登録
- **S3 固有設定**: `S3StorageConfiguration`（環境変数から S3 接続設定を生成）
- **サービス組み立て**: DI コンテナで Storage / Media コンポーネントを S3 向けに組み立て
- **プラグインブートストラップ**: `PluginInterface` 実装によるエントリポイント（サービス登録・コンパイラパス提供）
- **Attachment 同期登録**: ブラウザアップロード後の REST API 経由での即時 attachment 登録
- **ブラウザ直接アップロード JS**: `wp.Uploader` をインターセプトし、Pre-signed URL → S3 PUT → attachment 登録を自動化

## アーキテクチャ

### レイヤー構成

```
S3StoragePlugin（薄いレイヤー）
├── S3StoragePlugin.php              ← PluginInterface 実装（エントリポイント）
├── Attachment/
│   ├── AttachmentRegistrar          ← 冪等な attachment 登録（同期・非同期共用）
│   └── RegisterAttachmentController ← 同期登録 REST API
├── Subscriber/
│   └── AdminAssetSubscriber         ← 管理画面 JS エンキュー
├── assets/js/
│   └── s3-upload.js                 ← wp.Uploader インターセプター
├── Configuration/
│   └── S3StorageConfiguration       ← S3 固有設定、環境変数から生成
├── PreSignedUrl/
│   ├── PreSignedUrlGenerator        ← StorageAdapterInterface::temporaryUploadUrl() ラッパー
│   ├── PreSignedUrlResult           ← Pre-signed URL 結果 VO
│   ├── PreSignedUrlController       ← REST API エンドポイント（__invoke）
│   └── UploadPolicy                 ← ファイルタイプ・サイズ制限
├── Message/
│   ├── S3ObjectCreatedMessage       ← S3 イベント DTO
│   ├── GenerateThumbnailsMessage    ← サムネイル生成メッセージ
│   └── S3EventNormalizer            ← S3 Event Notification パーサー
├── Handler/
│   ├── S3ObjectCreatedHandler       ← AttachmentRegistrar に委譲する薄いアダプタ
│   └── GenerateThumbnailsHandler    ← サムネイル非同期生成
└── DependencyInjection/
    └── S3StoragePluginServiceProvider ← サービス組み立て

Storage コンポーネント（プロバイダ非依存）
├── Adapter\StorageAdapterInterface    ← ストレージコントラクト（temporaryUploadUrl 含む）
├── Adapter\Storage                    ← ファサード
├── StreamWrapper\StorageStreamWrapper ← stream_wrapper_register
└── StreamWrapper\StatCache            ← url_stat キャッシュ

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

#### ブラウザ直接アップロード（同期）

```
Browser (s3-upload.js)
  → REST API (PreSignedUrlController) → Pre-signed PUT URL 取得
  → Direct PUT to S3
  → REST API (RegisterAttachmentController) → 同期 attachment 登録
  → wp_prepare_attachment_for_js() レスポンス → メディアモーダル更新
```

`s3-upload.js` は `wp.Uploader.prototype.init` をラップし、WordPress 標準のアップロードフローをインターセプトします。アップロードチェーンは以下の順序で実行されます:

1. `getPresignedUrl()` — Pre-signed PUT URL を取得
2. `putToS3()` — XMLHttpRequest で S3 に直接 PUT（プログレスイベント付き）
3. `registerAttachment()` — REST API で attachment を同期登録
4. `completeUpload()` — plupload の `FileUploaded` イベントを発火
5. `startNextFile()` — キュー内の次のファイルを処理

#### S3 イベント経由の非同期登録

```
S3 Event Notification
  → Amazon SQS
  → WPPack\Component\Messenger
  → S3ObjectCreatedHandler → AttachmentRegistrar.register()
    → リサイズ画像の判定（スキップ）
    → マルチサイト対応 (switch_to_blog)
    → 既存 attachment の重複検出（冪等）
    → wp_insert_attachment()
    → GenerateThumbnailsMessage をディスパッチ

GenerateThumbnailsHandler
  → get_attached_file() → stream wrapper 経由
  → StorageImageEditor でサムネイル生成
  → wp_update_attachment_metadata()
```

ブラウザ直接アップロード後に S3 Event Notification が到達した場合、`AttachmentRegistrar` の重複検出（`_wp_attached_file` メタ検索）により冪等にスキップされます。

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
| wppack/storage | ストレージ抽象化（`StorageAdapterInterface`, `temporaryUploadUrl`, `StorageStreamWrapper`） |
| wppack/s3-storage | S3 アダプタ（`S3StorageAdapter`） |
| wppack/media | WordPress メディア統合（Subscriber 群, `StorageImageEditor`） |
| wppack/hook | WordPress フック統合 |
| wppack/messenger | メッセージバス・ハンドラ基盤（SQS 経由の非同期処理） |
| wppack/kernel | プラグインブートストラップ（`PluginInterface`, `Kernel`） |
| wppack/role | 認可（`#[IsGranted]`） |
| wppack/nonce | CSRF トークン管理（REST API nonce 生成） |
| wppack/rest | REST URL 生成（`RestUrlGenerator`） |
| wppack/asset | アセット管理（`AssetManager`） |
| async-aws/s3 | S3 API（S3StorageAdapter 経由で使用） |

## 名前空間

```
WPPack\Plugin\S3StoragePlugin\
```

## 主要クラス

### S3StoragePlugin

`PluginInterface` 実装。プラグインのエントリポイントとして、サービス登録とコンパイラパス提供を行う。

```php
namespace WPPack\Plugin\S3StoragePlugin;

final class S3StoragePlugin extends AbstractPlugin
{
    public function __construct(string $pluginFile);
    public function register(ContainerBuilder $builder): void;
    public function getCompilerPasses(): array;  // RegisterHookSubscribersPass, RegisterRestControllersPass
}
```

### Attachment 登録

#### AttachmentRegistrar

S3 オブジェクトを WordPress の attachment として冪等に登録するコアロジック。同期（REST API 経由）・非同期（S3 イベント経由）の両方から使用される。

```php
namespace WPPack\Plugin\S3StoragePlugin\Attachment;

final readonly class AttachmentRegistrar
{
    public function __construct(
        private MessageBusInterface $bus,
        private string $prefix,
        private BlogSwitcherInterface $blogSwitcher,
        private AttachmentManager $attachment,
        ?MimeTypesInterface $mimeTypes = null,
        private ?LoggerInterface $logger = null,
    ) {}

    public function register(string $key, ?int $userId = null): ?int;
    public function isResizedImage(string $key): bool;
    public function parseBlogId(string $key): int;
}
```

**`register()` のフロー:**

1. `isResizedImage()` でリサイズ画像をスキップ
2. `parseBlogId()` でマルチサイトのブログ ID を解析
3. `switch_to_blog()` でブログコンテキストを切り替え（マルチサイト時）
4. `findExistingAttachment()` で `_wp_attached_file` メタ検索 → 既存 attachment があれば冪等にスキップ
5. MIME タイプを推定し、`wp_insert_attachment()` で登録
6. `GenerateThumbnailsMessage` をメッセージバスにディスパッチ
7. `restore_current_blog()` で元のコンテキストに復帰（`finally` ブロック）

**リサイズ画像のスキップ:** S3 Event Notification はサムネイル生成時にも発火するため、`isResizedImage()` でリサイズ画像（派生ファイル）を検出してスキップします。以下のパターンにマッチする場合、新しい attachment は作成されません:

| パターン | 例 | 説明 |
|---------|------|------|
| `-{width}x{height}` | `photo-100x200.jpg` | WordPress サムネイル |
| `-scaled` | `photo-scaled.jpg` | WordPress の大画像スケーリング |
| `-rotated` | `photo-rotated.png` | 画像回転 |
| `-e{timestamp}` | `photo-e1234567890.jpg` | 画像編集のタイムスタンプ |

#### RegisterAttachmentController

ブラウザ直接アップロード後の同期 attachment 登録 REST API。

```php
namespace WPPack\Plugin\S3StoragePlugin\Attachment;

#[RestRoute(route: '/s3/register-attachment', methods: HttpMethod::POST, namespace: 'wppack/v1')]
#[IsGranted('upload_files')]
final class RegisterAttachmentController extends AbstractRestController
{
    public function __construct(
        private readonly AttachmentRegistrar $registrar,
        private readonly StorageAdapterInterface $adapter,
        private readonly AttachmentManager $attachment,
    ) {}

    /**
     * POST /wp-json/wppack/v1/s3/register-attachment
     *
     * Request: { "key": "uploads/2024/01/abc123def-photo.jpg" }
     * Response: wp_prepare_attachment_for_js() 形式（201 Created）
     *
     * Errors:
     *   400 — key パラメータ未指定
     *   404 — S3 上にファイルが存在しない
     *   500 — attachment 登録失敗
     */
    public function __invoke(Request $request): JsonResponse;
}
```

### Pre-signed URL

#### PreSignedUrlGenerator

StorageAdapterInterface の `temporaryUploadUrl()` を使用して Pre-signed PUT URL を生成する。
プロバイダ非依存のため、S3 / Azure / GCS いずれのアダプタでも動作する。

```php
namespace WPPack\Plugin\S3StoragePlugin\PreSignedUrl;

final class PreSignedUrlGenerator
{
    public function __construct(
        private readonly StorageAdapterInterface $storage,
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
namespace WPPack\Plugin\S3StoragePlugin\PreSignedUrl;

final class PreSignedUrlController
{
    /**
     * POST /wp-json/wppack/v1/s3/presigned-url
     *
     * Request: { "filename": "photo.jpg", "content_type": "image/jpeg", "content_length": 1048576 }
     * Response: { "url": "https://...", "key": "2024/01/a1b2c3d4-photo.jpg", "expires_in": 3600 }
     */
    public function __invoke(Request $request): JsonResponse;
}
```

#### UploadPolicy

ファイルタイプ・サイズの制限ポリシー。WordPress の許可ファイルタイプと連動する。

```php
namespace WPPack\Plugin\S3StoragePlugin\PreSignedUrl;

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

S3 オブジェクト作成イベントを処理し、`AttachmentRegistrar` に委譲する薄いアダプタ。

```php
namespace WPPack\Plugin\S3StoragePlugin\Handler;

#[AsMessageHandler]
final readonly class S3ObjectCreatedHandler
{
    public function __construct(
        private AttachmentRegistrar $registrar,
    ) {}

    public function __invoke(S3ObjectCreatedMessage $message): void
    {
        $this->registrar->register($message->key);
    }
}
```

#### GenerateThumbnailsHandler

Lambda 上でサムネイル画像を生成する。`get_attached_file()` → stream wrapper 経由で S3 からダウンロード、`StorageImageEditor` でリサイズ、stream wrapper 経由で S3 に書き戻します。

```php
namespace WPPack\Plugin\S3StoragePlugin\Handler;

#[AsMessageHandler]
final readonly class GenerateThumbnailsHandler
{
    public function __construct(
        private BlogSwitcherInterface $blogSwitcher,
        private AttachmentManager $attachment,
        private ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(GenerateThumbnailsMessage $message): void;
}
```

#### S3EventNormalizer

S3 Event Notification JSON を `S3ObjectCreatedMessage` にパースする。

```php
namespace WPPack\Plugin\S3StoragePlugin\Message;

final class S3EventNormalizer
{
    /** @return list<S3ObjectCreatedMessage> */
    public function normalize(array $event): array;
}
```

### 管理画面アセット

#### AdminAssetSubscriber

管理画面で `s3-upload.js` をエンキューし、フロントエンドに必要な設定を注入する。`media-upload` または `media-views` スクリプトが読み込まれている場合のみエンキューされる。

```php
namespace WPPack\Plugin\S3StoragePlugin\Subscriber;

#[AsHookSubscriber]
final readonly class AdminAssetSubscriber
{
    public function __construct(
        private string $pluginUrl,
        private UploadPolicy $policy,
        private AssetManager $asset,
        private NonceManager $nonce,
        private RestUrlGenerator $restUrl,
    ) {}

    #[AdminEnqueueScriptsAction]
    public function enqueueScripts(): void;
}
```

`wp_add_inline_script` で以下の設定オブジェクトを注入します:

```javascript
var wppS3Upload = {
    presignedUrl: "/wp-json/wppack/v1/s3/presigned-url",
    registerUrl: "/wp-json/wppack/v1/s3/register-attachment",
    nonce: "...",
    maxFileSize: 104857600,
    allowedTypes: ["image/jpeg", "image/png", ...]
};
```

#### s3-upload.js

`wp.Uploader.prototype.init` をラップし、WordPress 標準のアップロードフローを S3 直接アップロードにインターセプトする。

**主要関数:**

| 関数 | 役割 |
|------|------|
| `getPresignedUrl(nativeFile)` | Pre-signed PUT URL を fetch で取得 |
| `putToS3(up, file, nativeFile, presigned)` | XMLHttpRequest で S3 に PUT（プログレストラッキング付き） |
| `registerAttachment(key)` | REST API で attachment を同期登録 |
| `completeUpload(up, file, attachment)` | plupload の `FileUploaded` イベント発火・次ファイル処理 |
| `triggerError(up, file, message)` | plupload の `Error` イベント発火 |
| `startNextFile(up)` | キュー内の次ファイル処理または `UploadComplete` 発火 |

### マルチサイト対応

AttachmentRegistrar はマルチサイト環境を自動検出します:

1. S3 キーのパスから `/sites/{blog_id}/` パターンを解析
2. `switch_to_blog()` でブログコンテキストを切り替え
3. 該当ブログの attachment として登録
4. `restore_current_blog()` で元に戻す（`finally` ブロックで保証）

```
uploads/2024/01/photo.jpg         → blog_id: 1（メインサイト）
uploads/sites/2/2024/01/photo.jpg → blog_id: 2
uploads/sites/3/2024/01/photo.jpg → blog_id: 3
```

### S3StorageConfiguration

ストレージ接続の設定。DSN 文字列または wp_options から設定を読み込み、`StorageConfiguration`（Media コンポーネント）に変換可能。

```php
namespace WPPack\Plugin\S3StoragePlugin\Configuration;

final readonly class S3StorageConfiguration
{
    public const OPTION_NAME = 'wppack_storage';

    public static function fromEnvironmentOrOptions(): self;
    public static function hasConfiguration(): bool;
    public static function parseDsn(string $dsn): array;
    public static function maskDsn(string $dsn): string;
    public static function buildUri(string $bucket): string;
    public function toStorageConfiguration(): StorageConfiguration;
}
```

**設定の優先順位:**

1. `STORAGE_DSN` 定数または環境変数（最優先）
2. `wp_options`（`wppack_storage`）のプライマリストレージ DSN

**DSN 形式:**

```
s3://accessKey:secretKey@bucket?region=ap-northeast-1
```

- `accessKey:secretKey` — AWS 認証情報（省略可。省略時は IAM ロール等を使用）
- `bucket` — S3 バケット名
- `region` — AWS リージョン（省略時は `us-east-1`）

## 設定

### 設定 UI

Settings → Storage（`/wp-admin/options-general.php?page=wppack-storage`）から GUI で設定できます。

- **マルチプロバイダ対応**: S3, Azure Blob Storage, Google Cloud Storage, Local Filesystem
- **プライマリストレージ**: WordPress メディアアップロードに使用するストレージを選択
- **アップロードパス**: ファイルのキープレフィックス（デフォルト: `wp-content/uploads`）
- **CDN URL**: ファイルの公開 URL に使用する CDN ベース URL

定数/環境変数で設定されたストレージは読み取り専用として表示され、UI から編集できません。

### 環境変数 / 定数

```bash
# ストレージ DSN（定数または環境変数）
STORAGE_DSN=s3://AKIAIOSFODNN7EXAMPLE:wJalrXUtnFEMI@my-bucket?region=ap-northeast-1

# アップロードパス（オプション）
WPPACK_STORAGE_UPLOADS_PATH=wp-content/uploads
```

環境変数が設定されている場合、wp_options の設定より優先されます。AWS 認証情報は IAM ロールを使用する場合は DSN から省略できます:

```bash
STORAGE_DSN=s3://my-bucket?region=ap-northeast-1
```

## S3 CORS 設定

ブラウザから S3 への直接 PUT アップロードを行うには、S3 バケットに CORS 設定が必要です:

```json
[
    {
        "AllowedOrigins": ["https://example.com"],
        "AllowedMethods": ["PUT"],
        "AllowedHeaders": ["Content-Type"],
        "MaxAgeSeconds": 3600
    }
]
```

> **注意**: `AllowedOrigins` には WordPress サイトのオリジンを指定してください。`"*"` はセキュリティ上推奨しません。

AWS CLI での設定例:

```bash
aws s3api put-bucket-cors --bucket my-wordpress-media --cors-configuration file://cors.json
```

## 使用例

### ブラウザ直接アップロード（JavaScript）

`s3-upload.js` が自動的に WordPress のアップロードフローをインターセプトするため、通常は手動実装は不要です。カスタム実装が必要な場合:

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

// 3. Attachment を同期登録
const regResponse = await fetch('/wp-json/wppack/v1/s3/register-attachment', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce,
    },
    body: JSON.stringify({ key }),
});

const attachment = await regResponse.json();
// attachment は wp_prepare_attachment_for_js() 形式
// { id, title, filename, url, type, subtype, width, height, ... }
```

### PHP からのアップロード

```php
use WPPack\Component\Storage\Adapter\StorageAdapterInterface;

$adapter = $container->get(StorageAdapterInterface::class);
$adapter->write(
    path: 'uploads/2024/01/document.pdf',
    contents: file_get_contents($localPath),
    metadata: ['Content-Type' => 'application/pdf'],
);
```

### CDN URL の解決

```php
use WPPack\Component\Media\Storage\UrlResolver;

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
