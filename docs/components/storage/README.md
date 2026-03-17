# Storage コンポーネント

**パッケージ:** `wppack/storage`
**名前空間:** `WpPack\Component\Storage\`
**レイヤー:** Abstraction

WordPress のアップロードストレージ（`wp-content/uploads/`）を S3・GCS・Azure Blob 等のオブジェクトストレージに差し替えるための抽象化レイヤーです。Cache / Mailer と同じ Adapter / Bridge パターンを採用し、コアパッケージはストレージプロバイダに依存しません。

## Filesystem コンポーネントとの違い

| | Storage | Filesystem |
|---|---------|------------|
| **対象** | オブジェクトストレージ（S3, GCS, Azure Blob 等） | ローカルファイルシステム |
| **概念モデル** | フラットなキーバリューストア | ディレクトリ階層・パーミッション |
| **API** | `write` / `read` / `delete` / `publicUrl` / `temporaryUrl` | `get_contents` / `put_contents` / `mkdir` / `chmod` |
| **WordPress API** | なし（独自抽象化） | `WP_Filesystem_Base` ラッパー |
| **ユースケース** | メディアファイル、アップロード、アセット配信 | ローカル設定ファイル、テンプレート、ログ |

**使い分けの指針:**
- メディアファイルを S3 等に保存したい → **Storage**
- ローカルファイルの読み書き → **Filesystem**

## インストール

```bash
composer require wppack/storage
```

バックエンドごとの Bridge パッケージ:

```bash
composer require wppack/s3-storage      # Amazon S3
composer require wppack/azure-storage   # Azure Blob Storage
composer require wppack/gcs-storage     # Google Cloud Storage
```

## 基本コンセプト

### Before（S3 直接利用）

```php
// 従来 — async-aws/s3 を直接利用、プロバイダに強く依存
$s3Client = new S3Client(['region' => 'ap-northeast-1']);
$s3Client->putObject(new PutObjectRequest([
    'Bucket' => 'my-bucket',
    'Key' => 'uploads/2024/01/photo.jpg',
    'Body' => $contents,
    'ContentType' => 'image/jpeg',
]));
```

### After（WpPack Storage）

```php
use WpPack\Component\Storage\Adapter\Storage;

// DSN は S3 の実際の URL 形式をそのまま利用
$adapter = Storage::fromDsn('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads');

// プロバイダ非依存の API
$adapter->write('2024/01/photo.jpg', $contents, ['Content-Type' => 'image/jpeg']);
```

## StorageAdapterInterface

ストレージ操作のコントラクト。すべてのアダプタ（S3, GCS, Azure 等）がこのインターフェースを実装します。

```php
interface StorageAdapterInterface
{
    public function getName(): string;

    // 書き込み
    public function write(string $key, string $contents, array $metadata = []): void;
    public function writeStream(string $key, mixed $resource, array $metadata = []): void;

    // 読み取り
    public function read(string $key): string;           // ObjectNotFoundException
    public function readStream(string $key): mixed;      // ObjectNotFoundException

    // 削除
    public function delete(string $key): void;
    public function deleteMultiple(array $keys): void;

    // 存在確認
    public function exists(string $key): bool;

    // コピー / 移動
    public function copy(string $sourceKey, string $destinationKey): void;
    public function move(string $sourceKey, string $destinationKey): void;

    // メタデータ
    public function metadata(string $key): ObjectMetadata;  // ObjectNotFoundException

    // URL
    public function publicUrl(string $key): string;
    public function temporaryUrl(string $key, \DateTimeInterface $expiration): string;  // UnsupportedOperationException

    // 一覧
    /** @return iterable<ObjectMetadata> */
    public function listContents(string $prefix = '', bool $recursive = true): iterable;
}
```

### Cache との設計上の違い

| 観点 | Cache (`AdapterInterface`) | Storage (`StorageAdapterInterface`) |
|------|---------------------------|-------------------------------------|
| 読み取りミス | `false` を返す | `ObjectNotFoundException` をスロー |
| ストリーム | なし | `writeStream` / `readStream` で大ファイル対応 |
| URL 生成 | なし | `publicUrl()` / `temporaryUrl()` |
| メタデータ | なし | `ObjectMetadata`（size, lastModified, mimeType） |
| 一覧 | なし | `listContents()` |

## 基本操作

### ファイルの保存と取得

```php
use WpPack\Component\Storage\Adapter\Storage;

$adapter = Storage::fromDsn('s3://my-bucket.s3.ap-northeast-1.amazonaws.com');

// コンテンツを直接保存
$adapter->write('documents/report.pdf', $pdfContents, [
    'Content-Type' => 'application/pdf',
]);

// コンテンツを取得
$contents = $adapter->read('documents/report.pdf');
```

### ストリーム操作（大ファイル）

```php
// ストリームで保存（メモリ効率が良い）
$stream = fopen('/path/to/large-video.mp4', 'r');
$adapter->writeStream('videos/large-video.mp4', $stream, [
    'Content-Type' => 'video/mp4',
]);

// ストリームで取得
$stream = $adapter->readStream('videos/large-video.mp4');
// $stream を直接レスポンスに渡す等
```

### 存在確認とメタデータ

```php
if ($adapter->exists('images/photo.jpg')) {
    $metadata = $adapter->metadata('images/photo.jpg');
    echo $metadata->size;         // バイト数
    echo $metadata->mimeType;     // 'image/jpeg'
    echo $metadata->lastModified; // DateTimeImmutable
}
```

### URL 生成

```php
// 公開 URL（CDN URL が設定されていればそちらを返す）
$publicUrl = $adapter->publicUrl('images/photo.jpg');

// 署名付き一時 URL（有効期限付き）
$signedUrl = $adapter->temporaryUrl('private/document.pdf', new \DateTimeImmutable('+1 hour'));
```

### コピー・移動・削除

```php
$adapter->copy('source.txt', 'backup/source.txt');
$adapter->move('temp/upload.jpg', 'images/photo.jpg');
$adapter->delete('temp/upload.jpg');
$adapter->deleteMultiple(['old-1.txt', 'old-2.txt', 'old-3.txt']);
```

### 一覧取得

```php
// 再帰的に全オブジェクトを列挙
foreach ($adapter->listContents('uploads/2024/') as $object) {
    echo $object->key;      // 'uploads/2024/01/photo.jpg'
    echo $object->size;     // 1234567
}

// 直下のオブジェクトのみ（非再帰）
foreach ($adapter->listContents('uploads/', recursive: false) as $object) {
    echo $object->key;
}
```

## アダプタアーキテクチャ

Cache / Mailer コンポーネントと同じ **Adapter / Bridge パターン** を採用しています。

### 全体構成

```
wppack/storage（コア）
├── Adapter/
│   ├── StorageAdapterInterface      ← ストレージコントラクト
│   ├── AbstractStorageAdapter       ← テンプレートメソッド基底クラス
│   ├── StorageAdapterFactoryInterface ← ファクトリコントラクト
│   ├── LocalStorageAdapter           ← ローカルファイルシステムアダプタ
│   ├── LocalStorageAdapterFactory   ← ローカルファクトリ
│   ├── Storage                      ← ファクトリレジストリ（DSN → アダプタ）
│   └── Dsn                          ← DSN パーサー
├── ObjectMetadata                   ← メタデータ VO
├── Exception/
└── Test/
    └── InMemoryStorageAdapter       ← テスト用アダプタ

wppack/s3-storage（Bridge）
├── S3StorageAdapter                 ← AbstractStorageAdapter 実装
└── S3StorageAdapterFactory          ← StorageAdapterFactoryInterface 実装

wppack/azure-storage（Bridge）
├── AzureStorageAdapter              ← AbstractStorageAdapter 実装
└── AzureStorageAdapterFactory       ← StorageAdapterFactoryInterface 実装

wppack/gcs-storage（Bridge）
├── GcsStorageAdapter                ← AbstractStorageAdapter 実装
└── GcsStorageAdapterFactory         ← StorageAdapterFactoryInterface 実装
```

### AbstractStorageAdapter

テンプレートメソッドパターンの基底クラス。`execute()` ラッパーでプロバイダ固有の例外を `StorageException` に変換します。

```php
abstract class AbstractStorageAdapter implements StorageAdapterInterface
{
    // サブクラスはこれらを実装
    abstract protected function doWrite(string $key, string $contents, array $metadata = []): void;
    abstract protected function doRead(string $key): string;
    abstract protected function doDelete(string $key): void;
    abstract protected function doExists(string $key): bool;
    abstract protected function doCopy(string $sourceKey, string $destinationKey): void;
    abstract protected function doMetadata(string $key): ObjectMetadata;
    abstract protected function doPublicUrl(string $key): string;
    abstract protected function doListContents(string $prefix, bool $recursive): iterable;
    // ...

    // デフォルト実装あり（オーバーライド可）
    protected function doDeleteMultiple(array $keys): void { /* 各キーを doDelete */ }
    protected function doMove(string $src, string $dst): void { /* copy + delete */ }
    protected function doTemporaryUrl(...): string { throw UnsupportedOperationException; }
}
```

### DSN 形式

各プロバイダの URL 形式をそのまま DSN として使えます:

```
local:///path/to/storage
s3://{bucket}.s3.{region}.amazonaws.com/{prefix}
azure://{account}.blob.core.windows.net/{container}/{prefix}
gcs://{bucket}.storage.googleapis.com/{prefix}
```

```php
// ローカルファイルシステム
'local:///var/www/html/wp-content/uploads'

// Amazon S3
's3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads'

// Azure Blob Storage
'azure://myaccount.blob.core.windows.net/mycontainer/uploads'

// Google Cloud Storage
'gcs://my-bucket.storage.googleapis.com/uploads'
```

### Storage（レジストリ）

```php
// 1. 静的メソッド（FACTORY_CLASSES から自動検出）
$adapter = Storage::fromDsn('local:///var/www/uploads');
$adapter = Storage::fromDsn('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads');

// 2. コンストラクタ注入（カスタムファクトリを追加可能）
$storage = new Storage([
    new LocalStorageAdapterFactory(),
    new S3StorageAdapterFactory(),
    new MyCustomStorageAdapterFactory(),
]);
$adapter = $storage->fromString('custom://...');
```

## テスト

### InMemoryStorageAdapter

テスト用のインメモリ実装。`StorageAdapterInterface` を完全に実装しており、ユニットテストで外部サービスに依存せずに利用できます。

```php
use WpPack\Component\Storage\Test\InMemoryStorageAdapter;

$adapter = new InMemoryStorageAdapter();

$adapter->write('test.txt', 'hello world');
assert($adapter->read('test.txt') === 'hello world');
assert($adapter->exists('test.txt') === true);

$adapter->delete('test.txt');
assert($adapter->exists('test.txt') === false);
```

> [!NOTE]
> `InMemoryStorageAdapter` は `temporaryUrl()` をサポートしません（`UnsupportedOperationException` をスロー）。

## 例外設計

```
ExceptionInterface                   extends \Throwable
├── StorageException                 extends \RuntimeException
│   └── ObjectNotFoundException      extends StorageException
├── UnsupportedOperationException    extends \LogicException
├── InvalidArgumentException         extends \InvalidArgumentException
└── UnsupportedSchemeException       extends \LogicException
```

| 例外 | 発生条件 |
|------|---------|
| `ObjectNotFoundException` | `read()` / `readStream()` / `metadata()` で対象が存在しない |
| `StorageException` | プロバイダ固有のエラー（接続失敗、認証エラー等） |
| `UnsupportedOperationException` | `temporaryUrl()` を非対応アダプタで呼び出した |
| `InvalidArgumentException` | 不正な DSN や必須パラメータの欠落 |
| `UnsupportedSchemeException` | DSN のスキームに対応するファクトリが見つからない |

## 対応バックエンド

| バックエンド | Bridge パッケージ | スキーム | 依存 |
|------------|-----------------|---------|------|
| ローカルファイルシステム | `wppack/storage`（コア） | `local://` | なし |
| Amazon S3 | [`wppack/s3-storage`](s3-storage.md) | `s3://` | `async-aws/s3` |
| Azure Blob Storage | [`wppack/azure-storage`](azure-storage.md) | `azure://` | `azure-oss/storage` |
| Google Cloud Storage | [`wppack/gcs-storage`](gcs-storage.md) | `gcs://` | `google/cloud-storage` |

## 主要クラス一覧

| クラス | パッケージ | 説明 |
|-------|-----------|------|
| `Adapter\StorageAdapterInterface` | wppack/storage | ストレージコントラクト |
| `Adapter\AbstractStorageAdapter` | wppack/storage | テンプレートメソッド基底（例外統一） |
| `Adapter\StorageAdapterFactoryInterface` | wppack/storage | ファクトリコントラクト |
| `Adapter\LocalStorageAdapter` | wppack/storage | ローカルファイルシステムアダプタ |
| `Adapter\LocalStorageAdapterFactory` | wppack/storage | ローカルファクトリ |
| `Adapter\Storage` | wppack/storage | ファクトリレジストリ（DSN → アダプタ） |
| `Adapter\Dsn` | wppack/storage | DSN パーサー |
| `ObjectMetadata` | wppack/storage | メタデータ Value Object |
| `Test\InMemoryStorageAdapter` | wppack/storage | テスト用インメモリ実装 |
| `Bridge\S3\S3StorageAdapter` | wppack/s3-storage | S3 アダプタ |
| `Bridge\S3\S3StorageAdapterFactory` | wppack/s3-storage | S3 ファクトリ |
| `Bridge\Azure\AzureStorageAdapter` | wppack/azure-storage | Azure Blob アダプタ |
| `Bridge\Azure\AzureStorageAdapterFactory` | wppack/azure-storage | Azure Blob ファクトリ |
| `Bridge\Gcs\GcsStorageAdapter` | wppack/gcs-storage | GCS アダプタ |
| `Bridge\Gcs\GcsStorageAdapterFactory` | wppack/gcs-storage | GCS ファクトリ |
| `StreamWrapper\StorageStreamWrapper` | wppack/storage | PHP stream wrapper (`stream_wrapper_register`) |
| `StreamWrapper\StatCache` | wppack/storage | URL stat キャッシュ |

## Stream Wrapper

`StorageStreamWrapper` は PHP の `stream_wrapper_register` を使い、`StorageAdapterInterface` を介したオブジェクトストレージへのアクセスを PHP 標準ファイル関数（`file_exists`, `file_get_contents`, `fopen` 等）から透過的に行えるようにします。

```php
use WpPack\Component\Storage\StreamWrapper\StorageStreamWrapper;

$adapter = Storage::fromDsn('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads');
StorageStreamWrapper::register('s3', $adapter);

// PHP 標準関数がそのまま使える
file_put_contents('s3://path/to/file.txt', 'Hello, World!');
$contents = file_get_contents('s3://path/to/file.txt');
```

詳細は [Stream Wrapper ドキュメント](stream-wrapper.md) を参照してください。

## 依存関係

### 必須
- なし

### Bridge パッケージ利用時
- Amazon S3: `wppack/s3-storage`（`async-aws/s3` が必要。[詳細](s3-storage.md)）
- Azure Blob Storage: `wppack/azure-storage`（`azure-oss/storage` が必要。[詳細](azure-storage.md)）
- Google Cloud Storage: `wppack/gcs-storage`（`google/cloud-storage` が必要。[詳細](gcs-storage.md)）
