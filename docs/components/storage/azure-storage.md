# Azure Storage

**パッケージ:** `wppack/azure-storage`
**名前空間:** `WpPack\Component\Storage\Bridge\Azure\`
**レイヤー:** Abstraction（Bridge）

[Storage コンポーネント](./README.md) の Azure Blob Storage アダプタです。`azure-oss/storage` を利用して Azure Blob Storage にアクセスします。

## インストール

```bash
composer require wppack/azure-storage
```

## DSN 形式

Azure Blob Storage の URL 形式をそのまま DSN として使えます:

```
azure://{account}.blob.core.windows.net/{container}/{prefix}
```

```php
// デフォルト認証
'azure://myaccount.blob.core.windows.net/mycontainer/uploads'

// 明示的な認証情報（アカウント名 + アクセスキー）
'azure://myaccount:ACCOUNT_KEY@myaccount.blob.core.windows.net/mycontainer'

// 公開 URL 付き
'azure://myaccount.blob.core.windows.net/mycontainer/uploads?public_url=https://cdn.example.com'

// 接続文字列
'azure://myaccount.blob.core.windows.net/mycontainer?connection_string=DefaultEndpointsProtocol%3Dhttps%3B...'
```

### ホスト形式

| 形式 | 例 | 説明 |
|------|------|------|
| 標準ホスト | `myaccount.blob.core.windows.net` | アカウント名を自動抽出 |
| プレーンホスト | `myaccount` | ホスト = アカウント名 |

### パス形式

パスの最初のセグメントがコンテナ名、残りがプレフィックスになります:

```
/mycontainer              → container: mycontainer, prefix: (なし)
/mycontainer/uploads      → container: mycontainer, prefix: uploads
/mycontainer/wp/uploads   → container: mycontainer, prefix: wp/uploads
```

### クエリオプション

| オプション | 説明 | 例 |
|-----------|------|------|
| `public_url` | 公開ベース URL（`publicUrl()` で使用） | `https://cdn.example.com` |
| `connection_string` | Azure 接続文字列 | `DefaultEndpointsProtocol=https;...` |

## 使い方

### DSN 経由

```php
use WpPack\Component\Storage\Adapter\Storage;

$adapter = Storage::fromDsn('azure://myaccount.blob.core.windows.net/mycontainer/uploads');

$adapter->write('images/photo.jpg', $contents, ['Content-Type' => 'image/jpeg']);
$url = $adapter->publicUrl('images/photo.jpg');
```

### 直接インスタンス化

```php
use AzureOss\Storage\Blob\BlobServiceClient;
use WpPack\Component\Storage\Bridge\Azure\AzureBlobClient;
use WpPack\Component\Storage\Bridge\Azure\AzureStorageAdapter;

$serviceClient = BlobServiceClient::fromConnectionString(
    'DefaultEndpointsProtocol=https;AccountName=myaccount;AccountKey=...',
);
$blobClient = new AzureBlobClient($serviceClient, 'mycontainer');

$adapter = new AzureStorageAdapter(
    client: $blobClient,
    prefix: 'uploads',
    publicUrl: 'https://cdn.example.com',
);
```

## コンストラクタパラメータ

| パラメータ | 型 | 説明 |
|-----------|------|------|
| `$client` | `AzureBlobClientInterface` | Azure Blob クライアント |
| `$prefix` | `string` | パスプレフィックス（デフォルト: `''`） |
| `$publicUrl` | `?string` | 公開ベース URL（デフォルト: `null`） |

## URL 生成

### 公開 URL

```php
// 公開 URL が設定されている場合
$adapter = new AzureStorageAdapter($blobClient, 'uploads', 'https://cdn.example.com');
$adapter->publicUrl('images/photo.jpg');
// => 'https://cdn.example.com/uploads/images/photo.jpg'

// 公開 URL なしの場合（Azure Blob 直接 URL）
$adapter = new AzureStorageAdapter($blobClient, 'uploads');
$adapter->publicUrl('images/photo.jpg');
// => 'https://myaccount.blob.core.windows.net/mycontainer/uploads/images/photo.jpg'
```

### SAS トークン付き一時 URL

SAS（Shared Access Signature）トークン付きの一時 URL を生成します。プライベート Blob への一時的なアクセスに使用します。

```php
$url = $adapter->temporaryUrl('private/document.pdf', new \DateTimeImmutable('+1 hour'));
// => 'https://myaccount.blob.core.windows.net/mycontainer/uploads/private/document.pdf?sv=...&sig=...'
```

## Azure Blob API マッピング

| StorageAdapterInterface | Azure Blob API |
|------------------------|----------------|
| `write()` / `writeStream()` | `upload()` |
| `read()` / `readStream()` | `downloadStreaming()` |
| `delete()` | `delete()` |
| `deleteMultiple()` | ループ削除（`delete()` × N） |
| `fileExists()` | `getProperties()` で 404 判定 |
| `directoryExists()` | `getBlobsByHierarchy()` で prefix 存在判定 |
| `createDirectory()` | 空マーカー Blob をアップロード |
| `deleteDirectory()` | `getBlobsByHierarchy()` + ループ削除 |
| `copy()` | `copyFromUrl()` |
| `move()` | `copyFromUrl()` + `delete()` |
| `metadata()` | `getProperties()` |
| `publicUrl()` | URL 構築（API 呼び出しなし） |
| `temporaryUrl()` | SAS トークン生成 |
| `listContents()` | `getBlobsByHierarchy()` |

## 内部メソッドマッピング

| 内部メソッド | 説明 |
|-------------|------|
| `prefixPath(string $path)` | パスにプレフィックスを付与 |
| `stripPath(string $path)` | パスからプレフィックスを除去 |

## 例外処理

Azure 固有のエラーは `AbstractStorageAdapter` の `execute()` ラッパーにより `StorageException` に変換されます。ただし、404 系のエラーは `ObjectNotFoundException` として伝播します。

```php
use WpPack\Component\Storage\Exception\ObjectNotFoundException;
use WpPack\Component\Storage\Exception\StorageException;

try {
    $contents = $adapter->read('nonexistent.txt');
} catch (ObjectNotFoundException $e) {
    // Blob が存在しない
} catch (StorageException $e) {
    // 接続エラー、認証エラー等
}
```

## 依存関係

- `wppack/storage` — Storage コアパッケージ
- `azure-oss/storage` — Azure Blob Storage SDK
