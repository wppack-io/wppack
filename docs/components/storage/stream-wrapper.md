# StorageStreamWrapper

**名前空間:** `WPPack\Component\Storage\StreamWrapper\`
**パッケージ:** `wppack/storage`

PHP の `stream_wrapper_register` を使い、`StorageAdapterInterface` を介したオブジェクトストレージへのアクセスを PHP 標準ファイル関数から透過的に行えるようにする仕組みです。WordPress コアや他プラグインが `file_exists()`、`file_get_contents()`、`fopen()` 等でファイルにアクセスする際、ストレージプロバイダを意識せずに動作します。

## 登録方法

```php
use WPPack\Component\Storage\Adapter\Storage;
use WPPack\Component\Storage\StreamWrapper\StorageStreamWrapper;

// アダプタを作成し、プロトコルを登録
$adapter = Storage::fromDsn('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads');
StorageStreamWrapper::register('s3', $adapter);

// 以降、s3:// プロトコルで PHP 標準関数が使用可能
file_put_contents('s3://path/to/file.txt', 'Hello, World!');
$contents = file_get_contents('s3://path/to/file.txt');
```

登録解除:

```php
StorageStreamWrapper::unregister('s3');
```

## 対応 PHP 関数一覧

### ファイル操作

| 関数 | 説明 | 備考 |
|------|------|------|
| `file_exists()` | ファイル/ディレクトリの存在確認 | `url_stat` → `fileExists()` / `directoryExists()` |
| `file_get_contents()` | ファイル内容を文字列で取得 | `stream_open` + `stream_read` |
| `file_put_contents()` | ファイルに文字列を書き込み | `stream_open` + `stream_write` + `stream_close`（flush） |
| `fopen()` | ファイルを開く | モードに応じたバッファ初期化 |
| `fread()` | ファイルから読み取り | `php://temp` バッファから読み取り |
| `fwrite()` | ファイルに書き込み | `php://temp` バッファに書き込み |
| `fclose()` | ファイルを閉じる | 書き込みモードの場合、バッファ内容をストレージに flush |
| `fseek()` | ファイルポインタを移動 | バッファ内のシーク |
| `ftell()` | ファイルポインタの現在位置を取得 | バッファ内の位置 |
| `feof()` | ファイル終端判定 | バッファの EOF |
| `ftruncate()` | ファイルを切り詰め | バッファの切り詰め |
| `unlink()` | ファイルを削除 | `delete()` |
| `rename()` | ファイルを移動/リネーム | `move()` |
| `filesize()` | ファイルサイズを取得 | `url_stat` → `metadata()` |
| `is_file()` | ファイルかどうか判定 | `url_stat` の結果から判定 |
| `is_dir()` | ディレクトリかどうか判定 | `url_stat` → `directoryExists()` |
| `clearstatcache()` | stat キャッシュをクリア | `StatCache::clear()` |

### ディレクトリ操作

| 関数 | 説明 | 備考 |
|------|------|------|
| `mkdir()` | ディレクトリを作成 | `createDirectory()` に委譲 |
| `rmdir()` | ディレクトリを削除 | `deleteDirectory()` に委譲 |
| `opendir()` | ディレクトリを開く | `listContents()` |
| `readdir()` | ディレクトリエントリを読み取り | `listContents()` のイテレーション |
| `closedir()` | ディレクトリを閉じる | イテレータ解放 |
| `rewinddir()` | ディレクトリを先頭に巻き戻す | イテレータリセット |

## fopen モード対応表

| モード | 説明 | 動作 |
|--------|------|------|
| `r` | 読み取り専用 | リモートからバッファにダウンロード。ファイルが存在しない場合は失敗 |
| `r+` | 読み書き | リモートからバッファにダウンロード。書き込み可。close 時に flush |
| `w` | 書き込み専用（切り詰め） | 空バッファで開始。close 時に flush |
| `w+` | 読み書き（切り詰め） | 空バッファで開始。読み取り・書き込み可。close 時に flush |
| `a` | 追記専用 | リモートからバッファにダウンロード、ポインタを末尾に移動。close 時に flush |
| `a+` | 追記読み書き | リモートからバッファにダウンロード、ポインタを末尾に移動。close 時に flush |
| `x` | 排他的書き込み | ファイルが存在する場合は失敗。空バッファで開始。close 時に flush |
| `x+` | 排他的読み書き | ファイルが存在する場合は失敗。空バッファで開始。close 時に flush |
| `c` | 書き込み（既存保持） | ファイルが存在すればダウンロード、なければ空バッファ。close 時に flush |
| `c+` | 読み書き（既存保持） | ファイルが存在すればダウンロード、なければ空バッファ。close 時に flush |

> [!NOTE]
> すべての書き込みモード（`r+`, `w`, `w+`, `a`, `a+`, `x`, `x+`, `c`, `c+`）において、`fclose()` 呼び出し時にバッファ内容がストレージに書き戻されます。

## バッファリング戦略

StorageStreamWrapper は `php://temp` をバッファとして使用します。

```
fopen('s3://path/to/file.txt', 'r')
  → リモートからコンテンツをダウンロード
  → php://temp バッファに書き込み
  → ポインタを先頭にシーク

fread($fp, 1024)
  → php://temp バッファから読み取り

fwrite($fp, 'data')
  → php://temp バッファに書き込み

fclose($fp)
  → 書き込みモードの場合:
    → バッファ内容をストレージに writeStream() で flush
    → バッファを閉じる
```

`php://temp` は小さいデータ（デフォルト 2MB 以下）をメモリ上に保持し、閾値を超えると自動的に一時ファイルにスワップするため、メモリ効率と性能のバランスが取れています。

## StatCache

`url_stat()` の呼び出し結果をキャッシュして、同一リクエスト内での重複 API コールを防止します。

```php
use WPPack\Component\Storage\StreamWrapper\StatCache;

// キャッシュは自動的に使用される
file_exists('s3://path/to/file.txt');  // API コール発生
file_exists('s3://path/to/file.txt');  // キャッシュヒット（API コールなし）
filesize('s3://path/to/file.txt');     // キャッシュヒット

// キャッシュをクリア
clearstatcache();                      // PHP 標準関数で StatCache もクリアされる
StatCache::clear();                    // 直接クリアも可能
```

### ディレクトリ判定の最適化

`is_dir()` の呼び出し時、StatCache はまず `directoryExists()` を使用してディレクトリの存在を確認します。`directoryExists()` は指定パス配下にオブジェクトが存在するかを効率的に判定するため、不要な HEAD リクエストを回避できます。

```php
// directoryExists() でディレクトリ判定
is_dir('s3://uploads/2024/01');      // directoryExists() で確認（HEAD リクエストなし）

// ファイル存在確認は fileExists() を使用
is_file('s3://uploads/2024/01/photo.jpg');  // fileExists() → HEAD リクエスト
```

## mkdir / rmdir のアダプタ委譲

`mkdir()` と `rmdir()` はアダプタの `createDirectory()` / `deleteDirectory()` に委譲されます。

```php
// アダプタの createDirectory() に委譲
mkdir('s3://uploads/2024/01', 0777, true);

// アダプタの deleteDirectory() に委譲
rmdir('s3://uploads/2024/01');
```

実際の動作はアダプタ実装に依存します。オブジェクトストレージ（S3, GCS 等）では `createDirectory()` は空のプレフィックスマーカーオブジェクトを作成し、`deleteDirectory()` はプレフィックス配下の全オブジェクトを削除します。ローカルファイルシステムアダプタでは実際のディレクトリ操作を行います。

WordPress コアはアップロード時に `mkdir()` を呼び出すため、すべてのアダプタでこの操作が正しく処理される必要があります。

## マルチプロバイダ使用例

異なるストレージプロバイダを同時に異なるプロトコルで登録できます。

```php
use WPPack\Component\Storage\Adapter\Storage;
use WPPack\Component\Storage\StreamWrapper\StorageStreamWrapper;

// S3
$s3 = Storage::fromDsn('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads');
StorageStreamWrapper::register('s3', $s3);

// Azure Blob Storage
$azure = Storage::fromDsn('azure://myaccount.blob.core.windows.net/media/uploads');
StorageStreamWrapper::register('azure', $azure);

// Google Cloud Storage
$gcs = Storage::fromDsn('gcs://my-bucket.storage.googleapis.com/uploads');
StorageStreamWrapper::register('gcs', $gcs);

// 各プロトコルで透過的にアクセス
file_put_contents('s3://path/to/file.txt', 'Hello from S3!');
file_put_contents('azure://path/to/file.txt', 'Hello from Azure!');
file_put_contents('gcs://path/to/file.txt', 'Hello from GCS!');

// プロバイダ間のコピーも PHP 標準関数で
$content = file_get_contents('s3://original.jpg');
file_put_contents('gcs://backup.jpg', $content);
```

## 主要クラス

### StorageStreamWrapper

```
WPPack\Component\Storage\StreamWrapper\StorageStreamWrapper
```

PHP の `StreamWrapper` インターフェースを実装し、`stream_wrapper_register()` で任意のプロトコルを登録します。内部で `StorageAdapterInterface` を利用してストレージ操作を行います。

### StatCache

```
WPPack\Component\Storage\StreamWrapper\StatCache
```

`url_stat()` の結果をリクエストスコープでキャッシュし、API コールの重複を防止します。`clearstatcache()` 呼び出し時に自動的にクリアされます。

## 関連ドキュメント

- [Storage コンポーネント](./README.md)
- [S3 Storage](./s3-storage.md)
- [Azure Storage](./azure-storage.md)
- [GCS Storage](./gcs-storage.md)
- [Media Storage 連携](../media/storage.md)
