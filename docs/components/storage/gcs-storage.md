# GCS Storage

**パッケージ:** `wppack/gcs-storage`
**名前空間:** `WpPack\Component\Storage\Bridge\Gcs\`
**レイヤー:** Abstraction（Bridge）

[Storage コンポーネント](./README.md) の Google Cloud Storage アダプタです。`google/cloud-storage` を利用して GCS にアクセスします。

## インストール

```bash
composer require wppack/gcs-storage
```

## DSN 形式

GCS の URL 形式をそのまま DSN として使えます:

```
gcs://{bucket}.storage.googleapis.com/{prefix}
```

```php
// デフォルト認証（Application Default Credentials）
'gcs://my-bucket.storage.googleapis.com/uploads'

// プロジェクト ID 指定
'gcs://my-bucket?project=my-project-id'

// 公開 URL 付き
'gcs://my-bucket.storage.googleapis.com/uploads?public_url=https://cdn.example.com'

// サービスアカウントキーファイル
'gcs://my-bucket?key_file=/path/to/service-account.json'
```

### ホスト形式

| 形式 | 例 | 説明 |
|------|------|------|
| 標準ホスト | `my-bucket.storage.googleapis.com` | バケット名を自動抽出 |
| プレーンホスト | `my-bucket` | ホスト = バケット名 |

### クエリオプション

| オプション | 説明 | 例 |
|-----------|------|------|
| `public_url` | 公開ベース URL（`publicUrl()` で使用） | `https://cdn.example.com` |
| `project` | GCP プロジェクト ID | `my-project-id` |
| `key_file` | サービスアカウント JSON キーファイルパス | `/path/to/key.json` |

## 使い方

### DSN 経由

```php
use WpPack\Component\Storage\Adapter\Storage;

$adapter = Storage::fromDsn('gcs://my-bucket.storage.googleapis.com/uploads');

$adapter->write('images/photo.jpg', $contents, ['Content-Type' => 'image/jpeg']);
$url = $adapter->publicUrl('images/photo.jpg');
```

### 直接インスタンス化

```php
use Google\Cloud\Storage\StorageClient;
use WpPack\Component\Storage\Bridge\Gcs\GcsStorageAdapter;

$storageClient = new StorageClient(['projectId' => 'my-project-id']);
$bucket = $storageClient->bucket('my-bucket');

$adapter = new GcsStorageAdapter(
    bucket: $bucket,
    prefix: 'uploads',
    publicUrl: 'https://cdn.example.com',
);
```

## コンストラクタパラメータ

| パラメータ | 型 | 説明 |
|-----------|------|------|
| `$bucket` | `Bucket` | GCS Bucket オブジェクト |
| `$prefix` | `string` | パスプレフィックス（デフォルト: `''`） |
| `$publicUrl` | `?string` | 公開ベース URL（デフォルト: `null`） |

## URL 生成

### 公開 URL

```php
// 公開 URL が設定されている場合
$adapter = new GcsStorageAdapter($bucket, 'uploads', 'https://cdn.example.com');
$adapter->publicUrl('images/photo.jpg');
// => 'https://cdn.example.com/uploads/images/photo.jpg'

// 公開 URL なしの場合（GCS 直接 URL）
$adapter = new GcsStorageAdapter($bucket, 'uploads');
$adapter->publicUrl('images/photo.jpg');
// => 'https://storage.googleapis.com/my-bucket/uploads/images/photo.jpg'
```

### 署名付き一時 URL（V4）

GCS の V4 署名付き URL を生成します。プライベートオブジェクトへの一時的なアクセスに使用します。

```php
$url = $adapter->temporaryUrl('private/document.pdf', new \DateTimeImmutable('+1 hour'));
// => 'https://storage.googleapis.com/my-bucket/uploads/private/document.pdf?X-Goog-Signature=...'
```

### 署名付き一時アップロード URL（V4 PUT）

GCS の V4 署名付き PUT URL を生成します。クライアントからサーバーを経由せずに直接 GCS にファイルをアップロードする場合に使用します。

```php
$url = $adapter->temporaryUploadUrl('uploads/photo.jpg', new \DateTimeImmutable('+1 hour'), [
    'Content-Type' => 'image/jpeg',
    'Content-Length' => 1024000,
]);
// => 'https://storage.googleapis.com/my-bucket/uploads/uploads/photo.jpg?X-Goog-Signature=...'
```

`$options` に `Content-Type` や `Content-Length` を指定すると、アップロード時にその値が強制されます。

## パスプレフィックス

`prefix` を指定すると、すべての操作が自動的にプレフィックス付きのパスで行われます。DSN のパス部分がプレフィックスになります。

```php
// /uploads がプレフィックスになる
$adapter = Storage::fromDsn('gcs://my-bucket.storage.googleapis.com/uploads');

$adapter->write('2024/01/photo.jpg', $contents);
// 実際の GCS オブジェクト名: 'uploads/2024/01/photo.jpg'

$adapter->read('2024/01/photo.jpg');
// 実際の GCS オブジェクト名: 'uploads/2024/01/photo.jpg'

$adapter->listContents('2024/01/');
// 実際のプレフィックス: 'uploads/2024/01/'
// 返却されるパスからはプレフィックスが除去される
```

## GCS API マッピング

| StorageAdapterInterface | GCS API |
|------------------------|---------|
| `write()` / `writeStream()` | `Bucket::upload()` |
| `read()` | `StorageObject::downloadAsString()` |
| `readStream()` | `StorageObject::downloadAsStream()` |
| `delete()` | `StorageObject::delete()` |
| `deleteMultiple()` | ループ削除（`delete()` × N） |
| `fileExists()` | `StorageObject::exists()` |
| `directoryExists()` | `Bucket::objects()` で prefix 存在判定 |
| `createDirectory()` | `Bucket::upload()`（空マーカー） |
| `deleteDirectory()` | `Bucket::objects()` + ループ削除 |
| `copy()` | `StorageObject::copy()` |
| `move()` | `StorageObject::copy()` + `StorageObject::delete()` |
| `metadata()` | `StorageObject::info()` |
| `publicUrl()` | URL 構築（API 呼び出しなし） |
| `temporaryUrl()` | `StorageObject::signedUrl()` (V4, GET) |
| `temporaryUploadUrl()` | `StorageObject::signedUrl()` (V4, PUT) |
| `listContents()` | `Bucket::objects()` |

## 内部メソッドマッピング

| 内部メソッド | 説明 |
|-------------|------|
| `prefixPath(string $path)` | パスにプレフィックスを付与 |
| `stripPath(string $path)` | パスからプレフィックスを除去 |

## 例外処理

GCS 固有のエラーは `AbstractStorageAdapter` の `execute()` ラッパーにより `StorageException` に変換されます。ただし、404 系のエラーは `ObjectNotFoundException` として伝播します。

```php
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\Exception\StorageException;

try {
    $contents = $adapter->read('nonexistent.txt');
} catch (ObjectNotFoundException $e) {
    // オブジェクトが存在しない
} catch (StorageException $e) {
    // 接続エラー、認証エラー等
}
```

## 依存関係

- `wppack/storage` — Storage コアパッケージ
- `google/cloud-storage` — Google Cloud Storage SDK
