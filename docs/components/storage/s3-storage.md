# S3 Storage

**パッケージ:** `wppack/s3-storage`
**名前空間:** `WpPack\Component\Storage\Bridge\S3\`
**レイヤー:** Abstraction（Bridge）

[Storage コンポーネント](./README.md) の Amazon S3 アダプタです。`async-aws/s3` を利用して S3 互換のオブジェクトストレージにアクセスします。

## インストール

```bash
composer require wppack/s3-storage
```

## DSN 形式

S3 の仮想ホスト形式の URL をそのまま DSN として使えます:

```
s3://{bucket}.s3.{region}.amazonaws.com/{prefix}
```

```php
// デフォルト認証（IAM ロール、環境変数等）
's3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads'

// 明示的な認証情報
's3://ACCESS_KEY:SECRET_KEY@my-bucket.s3.us-east-1.amazonaws.com'

// CDN URL 付き
's3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads?cdn_url=https://cdn.example.com'

// カスタムエンドポイント（MinIO, LocalStack 等）
's3://my-bucket?endpoint=http://localhost:9000'
```

### ホスト形式

| 形式 | 例 | 説明 |
|------|------|------|
| 仮想ホスト（リージョンあり） | `my-bucket.s3.ap-northeast-1.amazonaws.com` | バケット名・リージョンを自動抽出 |
| 仮想ホスト（リージョンなし） | `my-bucket.s3.amazonaws.com` | バケット名を自動抽出、リージョンは SDK デフォルト |
| プレーンホスト | `my-bucket` | ホスト = バケット名（カスタムエンドポイント向け） |

### クエリオプション

| オプション | 説明 | 例 |
|-----------|------|------|
| `cdn_url` | CDN ベース URL（`url()` で使用） | `https://cdn.example.com` |
| `endpoint` | カスタムエンドポイント（MinIO, R2 等） | `http://localhost:9000` |
| `region` | リージョン上書き（プレーンホスト時） | `ap-northeast-1` |

## 使い方

### DSN 経由

```php
use WpPack\Component\Storage\Adapter\Storage;

$adapter = Storage::fromDsn('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads');

$adapter->put('images/photo.jpg', $contents, ['Content-Type' => 'image/jpeg']);
$url = $adapter->url('images/photo.jpg');
```

### 直接インスタンス化

```php
use AsyncAws\S3\S3Client;
use WpPack\Component\Storage\Bridge\S3\S3StorageAdapter;

$s3Client = new S3Client(['region' => 'ap-northeast-1']);

$adapter = new S3StorageAdapter(
    s3Client: $s3Client,
    bucket: 'my-bucket',
    prefix: 'uploads',
    cdnUrl: 'https://cdn.example.com',
);
```

## コンストラクタパラメータ

| パラメータ | 型 | 説明 |
|-----------|------|------|
| `$s3Client` | `S3Client` | async-aws S3 クライアント |
| `$bucket` | `string` | バケット名 |
| `$prefix` | `string` | キープレフィックス（デフォルト: `''`） |
| `$cdnUrl` | `?string` | CDN ベース URL（デフォルト: `null`） |

## URL 生成

### 公開 URL

```php
// CDN URL が設定されている場合
$adapter = new S3StorageAdapter($s3Client, 'my-bucket', 'uploads', 'https://cdn.example.com');
$adapter->url('images/photo.jpg');
// => 'https://cdn.example.com/uploads/images/photo.jpg'

// CDN URL なしの場合（S3 直接 URL）
$adapter = new S3StorageAdapter($s3Client, 'my-bucket', 'uploads');
$adapter->url('images/photo.jpg');
// => 'https://my-bucket.s3.amazonaws.com/uploads/images/photo.jpg'
```

### 署名付き一時 URL

S3 のプリサイン URL を生成します。プライベートオブジェクトへの一時的なアクセスに使用します。

```php
$url = $adapter->temporaryUrl('private/document.pdf', new \DateTimeImmutable('+1 hour'));
// => 'https://my-bucket.s3.amazonaws.com/uploads/private/document.pdf?X-Amz-Signature=...'
```

## キープレフィックス

`prefix` を指定すると、すべての操作が自動的にプレフィックス付きのキーで行われます。DSN のパス部分がプレフィックスになります。

```php
// /uploads がプレフィックスになる
$adapter = Storage::fromDsn('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads');

$adapter->put('2024/01/photo.jpg', $contents);
// 実際の S3 キー: 'uploads/2024/01/photo.jpg'

$adapter->get('2024/01/photo.jpg');
// 実際の S3 キー: 'uploads/2024/01/photo.jpg'

$adapter->listContents('2024/01/');
// 実際のプレフィックス: 'uploads/2024/01/'
// 返却されるキーからはプレフィックスが除去される
```

## バッチ削除

`deleteMultiple()` は S3 の `DeleteObjects` API を使用してバッチ削除を行います。1 リクエストあたり最大 1,000 オブジェクトを処理し、それを超える場合は自動的に分割されます。

```php
$adapter->deleteMultiple(['old-1.txt', 'old-2.txt', 'old-3.txt']);
```

## S3 互換ストレージ

カスタムエンドポイントを指定することで、S3 互換のオブジェクトストレージ（MinIO, Cloudflare R2 等）でも利用できます。

```php
// MinIO
$adapter = Storage::fromDsn('s3://minioadmin:minioadmin@my-bucket?endpoint=http://localhost:9000');

// Cloudflare R2
$adapter = Storage::fromDsn('s3://my-bucket?endpoint=https://<account-id>.r2.cloudflarestorage.com');
```

## S3 API マッピング

| StorageAdapterInterface | S3 API |
|------------------------|--------|
| `put()` / `putStream()` | `PutObject` |
| `get()` / `getStream()` | `GetObject` |
| `delete()` | `DeleteObject` |
| `deleteMultiple()` | `DeleteObjects` |
| `exists()` | `HeadObject` |
| `copy()` | `CopyObject` |
| `move()` | `CopyObject` + `DeleteObject` |
| `metadata()` | `HeadObject` |
| `url()` | URL 構築（API 呼び出しなし） |
| `temporaryUrl()` | プリサイン URL 生成 |
| `listContents()` | `ListObjectsV2`（ページネーション対応） |

## 例外処理

S3 固有のエラーは `AbstractStorageAdapter` の `execute()` ラッパーにより `StorageException` に変換されます。ただし、404 系のエラーは `ObjectNotFoundException` として伝播します。

```php
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\Exception\StorageException;

try {
    $contents = $adapter->get('nonexistent.txt');
} catch (ObjectNotFoundException $e) {
    // オブジェクトが存在しない
} catch (StorageException $e) {
    // 接続エラー、認証エラー等
}
```

## 依存関係

- `wppack/storage` — Storage コアパッケージ
- `async-aws/s3` — AWS S3 SDK
