## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Filesystem/Subscriber/`

### Filesystem Method フック

#### `#[FilesystemMethodFilter]`

**WordPress Hook:** `filesystem_method`

```php
use WpPack\Component\Hook\Attribute\Filesystem\Filter\FilesystemMethodFilter;

final class FilesystemMethodSelector
{
    #[FilesystemMethodFilter(priority: 10)]
    public function selectFilesystemMethod(
        string $method,
        array $args,
        string $context,
        bool $allow_relaxed_file_ownership,
    ): string {
        if (defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) {
            return 'direct';
        }

        return $method;
    }
}
```

#### `#[FilesystemMethodFileFilter]`

**WordPress Hook:** `filesystem_method_file`

```php
use WpPack\Component\Hook\Attribute\Filesystem\Filter\FilesystemMethodFileFilter;

final class FilesystemDetectionCustomizer
{
    #[FilesystemMethodFileFilter(priority: 10)]
    public function customizeDetectionFile(string $file, string $method): string
    {
        return $file;
    }
}
```

### アップロードディレクトリフック

#### `#[UploadDirFilter]`

**WordPress Hook:** `upload_dir`

```php
use WpPack\Component\Hook\Attribute\Filesystem\Filter\UploadDirFilter;

final class UploadDirectoryManager
{
    #[UploadDirFilter(priority: 10)]
    public function customizeUploadDirectory(array $uploads): array
    {
        // 本番環境では CDN を使用
        if ($this->shouldUseCDN()) {
            $uploads['baseurl'] = 'https://cdn.example.com/uploads';
        }

        return $uploads;
    }
}
```

#### `#[WpUniqueFilenameFilter]`

**WordPress Hook:** `wp_unique_filename`

```php
use WpPack\Component\Hook\Attribute\Filesystem\Filter\WpUniqueFilenameFilter;

final class FilenameGenerator
{
    #[WpUniqueFilenameFilter(priority: 10)]
    public function generateUniqueFilename(
        string $filename,
        string $ext,
        string $dir,
        ?callable $unique_filename_callback,
    ): string {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = strtolower(preg_replace('/[^a-z0-9-_]/', '-', $name));

        return $name . '.' . $ext;
    }
}
```

## クイックリファレンス

```php
// Filesystem Method
#[FilesystemMethodFilter(priority: 10)]          // ファイルシステムメソッドの選択
#[FilesystemMethodFileFilter(priority: 10)]      // メソッド検出用テストファイル

// アップロード管理
#[UploadDirFilter(priority: 10)]                 // アップロードパスの変更
#[WpUniqueFilenameFilter(priority: 10)]          // ユニークファイル名の生成
#[WpHandleSideloadPrefilterFilter(priority: 10)] // サイドロードバリデーション

// ファイル操作
#[WpDeleteFileFilter(priority: 10)]              // ファイル削除の制御
#[FileIsDisplayableImageFilter(priority: 10)]    // 表示可能画像のチェック

// パスフィルター
#[WpUploadBitsFilter(priority: 10)]              // アップロードファイルデータのフィルタリング
#[LoadImageToEditPathFilter(priority: 10)]       // 画像エディタパス

// 初期化
#[WpFilesystemInitAction(priority: 10)]          // WP_Filesystem 初期化
#[WpMkdirModeFilter(priority: 10)]               // mkdir パーミッション
```
