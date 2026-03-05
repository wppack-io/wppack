# Filesystem Component

**Package:** `wppack/filesystem`
**Namespace:** `WpPack\Component\Filesystem\`
**Layer:** Infrastructure

WordPress のファイル操作をモダンなオブジェクト指向 API でラップするコンポーネントです。`WP_Filesystem_Base` を DI 注入可能にし、ファイルの読み書き・コピー・移動・削除などの基本操作、WordPress アップロードディレクトリの統合、Named Hook Attributes を提供します。

## インストール

```bash
composer require wppack/filesystem
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// 従来の WordPress - WP_Filesystem の初期化が複雑
global $wp_filesystem;

if (!function_exists('WP_Filesystem')) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}

WP_Filesystem();

if (!$wp_filesystem) {
    return new WP_Error('fs_unavailable', 'Filesystem not available');
}

$content = $wp_filesystem->get_contents('/path/to/file.txt');
$wp_filesystem->put_contents('/path/to/file.txt', $data, FS_CHMOD_FILE);
```

### After（WpPack）

```php
use WpPack\Component\Filesystem\Filesystem;

// DI コンテナから取得（WP_Filesystem_Base は必須）
$filesystem = $container->get(Filesystem::class);

$content = $filesystem->read('/path/to/file.txt');
$filesystem->write('/path/to/file.txt', $content);
```

## Filesystem クラス

`WP_Filesystem_Base` をラップし DI 注入可能にするサービスクラスです。`WP_Filesystem_Base` の注入は必須です。

### コンストラクタ

```php
use WpPack\Component\Filesystem\Filesystem;

// WP_Filesystem_Base を注入（必須）
$filesystem = new Filesystem($wp_filesystem);

// テスト環境では WP_Filesystem_Direct を使用
$filesystem = new Filesystem(new \WP_Filesystem_Direct(null));
```

### ファイルの読み書き

```php
// 読み込み
$content = $filesystem->read('/path/to/file.txt');

// 書き込み
$filesystem->write('/path/to/file.txt', 'content');

// 追記
$filesystem->append('/path/to/file.txt', 'more content');
```

### 存在チェック

```php
$filesystem->exists('/path/to/file.txt');     // ファイルまたはディレクトリ
$filesystem->isFile('/path/to/file.txt');     // ファイルのみ
$filesystem->isDirectory('/path/to/dir');     // ディレクトリのみ
```

### 削除

```php
$filesystem->delete('/path/to/file.txt');            // ファイル削除
$filesystem->deleteDirectory('/path/to/dir');        // ディレクトリ再帰削除
```

### コピー・移動

```php
$filesystem->copy('/path/from.txt', '/path/to.txt');
$filesystem->move('/path/from.txt', '/path/to.txt');
```

### ディレクトリ作成

```php
$filesystem->mkdir('/path/to/new-dir');
$filesystem->mkdir('/path/to/a/b/c', recursive: true);
```

### パーミッション

```php
$filesystem->chmod('/path/to/file.txt', 0644);
```

### ファイル情報

```php
$size = $filesystem->size('/path/to/file.txt');           // バイト数
$mtime = $filesystem->lastModified('/path/to/file.txt');  // UNIX タイムスタンプ
$mime = $filesystem->mimeType('/path/to/file.txt');       // MIME タイプ
```

### ディレクトリ一覧

```php
$files = $filesystem->files('/path/to/directory');           // ファイル名のみ
$dirs = $filesystem->directories('/path/to/directory');      // ディレクトリ名のみ
$all = $filesystem->listContents('/path/to/directory');      // 全エントリ
$all = $filesystem->listContents('/path/to/dir', recursive: true);  // 再帰
```

### メソッドリファレンス

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `read(string $path)` | `string\|false` | ファイル読み込み |
| `write(string $path, string $content)` | `bool` | ファイル書き込み |
| `append(string $path, string $content)` | `bool` | 追記 |
| `exists(string $path)` | `bool` | 存在チェック |
| `isFile(string $path)` | `bool` | ファイル判定 |
| `isDirectory(string $path)` | `bool` | ディレクトリ判定 |
| `delete(string $path)` | `bool` | ファイル削除 |
| `deleteDirectory(string $path)` | `bool` | ディレクトリ再帰削除 |
| `copy(string $source, string $dest)` | `bool` | コピー |
| `move(string $source, string $dest)` | `bool` | 移動 |
| `mkdir(string $path, bool $recursive = false)` | `bool` | ディレクトリ作成 |
| `chmod(string $path, int $mode)` | `bool` | パーミッション変更 |
| `size(string $path)` | `int\|false` | サイズ取得 |
| `lastModified(string $path)` | `int\|false` | 更新日時取得 |
| `mimeType(string $path)` | `string\|false` | MIME タイプ取得 |
| `files(string $directory)` | `list<string>` | ファイル一覧 |
| `directories(string $directory)` | `list<string>` | ディレクトリ一覧 |
| `listContents(string $directory, bool $recursive = false)` | `list<string>` | 全コンテンツ一覧 |

## WordPress アップロードディレクトリ統合

`UploadPath` は `wp_upload_dir()` のラッパーで、DI 注入可能です。

```php
use WpPack\Component\Filesystem\WordPress\UploadPath;

$uploadPath = new UploadPath();

// WordPress アップロードベースパス
$basePath = $uploadPath->getBasePath();    // /var/www/html/wp-content/uploads
$baseUrl = $uploadPath->getBaseUrl();      // https://example.com/wp-content/uploads

// 年月ベースのサブディレクトリ
$currentPath = $uploadPath->getCurrentPath();  // /var/www/html/wp-content/uploads/2024/01
$currentUrl = $uploadPath->getCurrentUrl();    // https://example.com/wp-content/uploads/2024/01

// カスタムサブディレクトリ（自動作成）
$customPath = $uploadPath->subdir('exports');
// /var/www/html/wp-content/uploads/exports
```

## Named Hook Attributes

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Filesystem/Subscriber/`

### Filesystem Method フック

#### `#[FilesystemMethodFilter]`

**WordPress Hook:** `filesystem_method`

```php
use WpPack\Component\Filesystem\Attribute\Filter\FilesystemMethodFilter;

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
use WpPack\Component\Filesystem\Attribute\Filter\FilesystemMethodFileFilter;

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
use WpPack\Component\Filesystem\Attribute\Filter\UploadDirFilter;

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
use WpPack\Component\Filesystem\Attribute\Filter\WpUniqueFilenameFilter;

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

### Hook Attribute リファレンス

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

## テスト

```php
use PHPUnit\Framework\TestCase;
use WpPack\Component\Filesystem\Filesystem;

class FileOperationsTest extends TestCase
{
    private Filesystem $filesystem;
    private string $testDir;

    protected function setUp(): void
    {
        if (!class_exists(\WP_Filesystem_Direct::class)) {
            self::markTestSkipped('WordPress Filesystem API is not available.');
        }

        $this->filesystem = new Filesystem(new \WP_Filesystem_Direct(null));
        $this->testDir = sys_get_temp_dir() . '/wppack_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    public function testWriteAndRead(): void
    {
        $path = $this->testDir . '/test.txt';

        $this->assertTrue($this->filesystem->write($path, 'Hello'));
        $this->assertSame('Hello', $this->filesystem->read($path));
    }
}
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `Filesystem` | `WP_Filesystem_Base` DI ラッパー |
| `WordPress\UploadPath` | `wp_upload_dir()` DI ラッパー |
| `Attribute\Action\WpFilesystemInitAction` | `wp_filesystem_init` アクション |
| `Attribute\Filter\FilesystemMethodFilter` | `filesystem_method` フィルター |
| `Attribute\Filter\FilesystemMethodFileFilter` | `filesystem_method_file` フィルター |
| `Attribute\Filter\UploadDirFilter` | `upload_dir` フィルター |
| `Attribute\Filter\WpUniqueFilenameFilter` | `wp_unique_filename` フィルター |
| `Attribute\Filter\WpHandleSideloadPrefilterFilter` | `wp_handle_sideload_prefilter` フィルター |
| `Attribute\Filter\WpDeleteFileFilter` | `wp_delete_file` フィルター |
| `Attribute\Filter\FileIsDisplayableImageFilter` | `file_is_displayable_image` フィルター |
| `Attribute\Filter\WpUploadBitsFilter` | `wp_upload_bits` フィルター |
| `Attribute\Filter\LoadImageToEditPathFilter` | `load_image_to_edit_path` フィルター |
| `Attribute\Filter\WpMkdirModeFilter` | `wp_mkdir_mode` フィルター |

## 依存関係

### 必須
- **WordPress** - `WP_Filesystem_Base`（コンストラクタ注入）

### 推奨
- **Hook コンポーネント** - Named Hook Attributes 使用時
