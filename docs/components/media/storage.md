# Media Storage 連携

**パッケージ:** `wppack/media`
**名前空間:** `WPPack\Component\Media\Storage\`

WordPress のメディアアップロード（`wp-content/uploads/`）をオブジェクトストレージ（S3, Azure Blob, GCS 等）に差し替えるための仕組みです。Storage コンポーネントの `StorageAdapterInterface` と `StorageStreamWrapper` を利用し、ストレージプロバイダに依存しない形で WordPress のメディア管理を統合します。

## 概要

Media Storage 連携は、WordPress のアップロードフローを以下のように書き換えます:

```
WordPress コアのアップロード処理
    │
    ├─ upload_dir フィルタ
    │     → UploadDirSubscriber
    │     → path/basedir を stream wrapper パスに書き換え
    │     → 例: s3://my-bucket/uploads/2024/01
    │
    ├─ file_put_contents() / fopen() + fwrite()
    │     → StorageStreamWrapper (s3:// プロトコル)
    │     → StorageAdapterInterface
    │     → オブジェクトストレージ
    │
    ├─ wp_get_attachment_url()
    │     → AttachmentSubscriber
    │     → CDN URL または publicUrl() に変換
    │
    └─ wp_generate_attachment_metadata()
          → StorageImageEditor
          → ローカル temp にダウンロード → Imagick 処理
          → stream wrapper 経由で書き戻し
```

## Subscriber 群

Media Storage 連携は、WordPress フックを介して動作する 3 つの Subscriber で構成されます。すべて `#[AsHookSubscriber]` アトリビュートを持ち、Hook コンポーネントにより自動登録されます。

### UploadDirSubscriber

`upload_dir` フィルタをフックし、WordPress のアップロードパスを stream wrapper パスに書き換えます。

```php
use WPPack\Component\Media\Storage\Subscriber\UploadDirSubscriber;
```

**フィルタ:** `upload_dir`

| キー | 書き換え前 | 書き換え後 |
|------|-----------|-----------|
| `path` | `/var/www/html/wp-content/uploads/2024/01` | `s3://my-bucket/uploads/2024/01` |
| `basedir` | `/var/www/html/wp-content/uploads` | `s3://my-bucket/uploads` |
| `url` | `https://example.com/wp-content/uploads/2024/01` | `https://cdn.example.com/uploads/2024/01` |
| `baseurl` | `https://example.com/wp-content/uploads` | `https://cdn.example.com/uploads` |

`subdir`（日付ベースのサブディレクトリ: `/2024/01`）は WordPress コアの設定をそのまま維持します。

### AttachmentSubscriber

WordPress のアタッチメント関連フックを包括的にハンドリングします。

```php
use WPPack\Component\Media\Storage\Subscriber\AttachmentSubscriber;
```

| フック | メソッド | 動作 |
|--------|---------|------|
| `wp_get_attachment_url` | `filterAttachmentUrl()` | URL を CDN URL またはストレージ公開 URL に変換 |
| `get_attached_file` | `filterGetAttachedFile()` | ローカルパスを stream wrapper パスに変換 |
| `delete_attachment` | `onDeleteAttachment()` | 元ファイルとサムネイルをストレージから削除 |
| `wp_generate_attachment_metadata` | `setFilesizeInMeta()` | リモートファイルの filesize をメタデータに設定 |
| `wp_read_image_metadata` | `filterReadImageMetadata()` | stream wrapper パスの EXIF 読み取りをスキップ |
| `wp_resource_hints` | `filterResourceHints()` | CDN ドメインを dns-prefetch に追加 |
| `pre_wp_unique_filename_file_list` | `filterUniqueFilenameFileList()` | ストレージ上のファイル一覧でユニークファイル名を生成 |

### ImageEditorSubscriber

WordPress の画像エディタリストに `StorageImageEditor` を優先的に差し込みます。

```php
use WPPack\Component\Media\Storage\Subscriber\ImageEditorSubscriber;
```

**フック:** `wp_image_editors`（priority: 9）

WordPress が画像をリサイズする際に `StorageImageEditor` が選択され、stream wrapper パスの画像を処理できるようになります。

## StorageImageEditor

`WP_Image_Editor_Imagick` を拡張し、stream wrapper パスの画像を処理するエディタです。

```php
use WPPack\Component\Media\Storage\ImageEditor\StorageImageEditor;
```

### 処理フロー

```
1. load()
   ├─ stream wrapper パスの画像を検出
   ├─ file_get_contents() でローカル一時ファイルにダウンロード
   └─ Imagick で一時ファイルをロード

2. resize() / crop() 等
   └─ Imagick が一時ファイル上で処理（親クラスの処理をそのまま利用）

3. _save()
   ├─ Imagick が一時ファイルに保存
   ├─ 一時ファイルの内容を読み取り
   └─ file_put_contents() で stream wrapper 経由でストレージに書き戻し

4. __destruct()
   └─ 一時ファイルをクリーンアップ
```

> [!NOTE]
> Imagick は stream wrapper パスを直接開けないため、一時ファイルを経由する必要があります。

## StorageConfiguration

ストレージ接続の設定を保持する Value Object です。

```php
use WPPack\Component\Media\Storage\StorageConfiguration;

$config = new StorageConfiguration(
    protocol: 's3',           // stream wrapper のプロトコル
    bucket: 'my-bucket',      // バケット名
    prefix: 'uploads',        // キープレフィックス
    cdnUrl: 'https://cdn.example.com',  // CDN URL（オプション）
);
```

プロバイダ固有の設定クラス（例: `S3StorageConfiguration`）から `toStorageConfiguration()` で変換できます:

```php
use WPPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;

$s3Config = S3StorageConfiguration::fromEnvironmentOrOptions();
$storageConfig = $s3Config->toStorageConfiguration();
```

`fromEnvironmentOrOptions()` は以下の優先順位で設定を読み込みます:

1. `STORAGE_DSN` 定数または環境変数
2. `wp_options`（`wppack_storage`）のプライマリストレージ DSN

## UrlResolver

ストレージキーを公開 URL に変換するリゾルバです。CDN URL が設定されている場合はそちらを優先します。

```php
use WPPack\Component\Media\Storage\UrlResolver;

$resolver = new UrlResolver(
    adapter: $storageAdapter,
    cdnUrl: 'https://cdn.example.com',
);

// CDN URL が設定されていれば:
$resolver->resolve('uploads/2024/01/photo.jpg');
// => https://cdn.example.com/uploads/2024/01/photo.jpg

// CDN URL が null の場合:
$resolver->resolve('uploads/2024/01/photo.jpg');
// => $adapter->publicUrl('uploads/2024/01/photo.jpg')
```

## マルチサイト対応

マルチサイト環境では、`UploadDirSubscriber` がブログ ID に基づいてサブディレクトリを自動的に付与します。

| ブログ ID | パス |
|-----------|------|
| 1（メインサイト） | `s3://my-bucket/uploads/2024/01/photo.jpg` |
| 2 | `s3://my-bucket/uploads/sites/2/2024/01/photo.jpg` |
| 3 | `s3://my-bucket/uploads/sites/3/2024/01/photo.jpg` |

WordPress のマルチサイトデフォルトのパス構造（`/sites/{blog_id}/`）をそのまま踏襲します。メインサイト（blog_id = 1）にはサブディレクトリは付与されません。

## MigrateCommand

ローカルファイルシステムからオブジェクトストレージへメディアファイルを移行する WP-CLI コマンドです。

```bash
# 全メディアを移行
wp media:migrate-storage

# ドライランで移行対象を確認
wp media:migrate-storage --dry-run

# バッチサイズを指定して移行
wp media:migrate-storage --batch-size=100
```

### 動作

1. `get_posts()` で全 attachment を取得（バッチ単位）
2. 各 attachment の元ファイルとサムネイルを収集
3. ローカルに存在し、ストレージに未存在のファイルのみ転送
4. `writeStream()` でストリーム転送（メモリ効率的）

### オプション

| オプション | 説明 | デフォルト |
|-----------|------|----------|
| `--dry-run` | 移行をシミュレート（ファイルはコピーしない） | 無効 |
| `--batch-size` | バッチあたりの処理 attachment 数 | 100 |

## 関連ドキュメント

- [Media コンポーネント](./README.md)
- [Storage コンポーネント](../storage/README.md)
- [StorageStreamWrapper](../storage/stream-wrapper.md)
- [S3StoragePlugin](../../plugins/s3-storage-plugin.md)
