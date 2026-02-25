# Filesystem Component

**Package:** `wppack/filesystem`
**Namespace:** `WpPack\Component\Filesystem\`
**Layer:** Infrastructure

WordPress のファイル操作をモダンなオブジェクト指向 API でラップするコンポーネントです。`WP_Filesystem` の初期化を自動化し、ファイルの読み書き・コピー・移動・削除などの基本操作、`wp_handle_upload()` によるファイルアップロード処理、WordPress アップロードディレクトリの統合、バリデーション、Named Hook Attributes を提供します。

## インストール

```bash
composer require wppack/filesystem
```

## 基本コンセプト

### 従来の WordPress と WpPack の比較

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
if ($content === false) {
    // エラー処理 - 詳細なエラー情報がない
}

if (!$wp_filesystem->put_contents('/path/to/file.txt', $data, FS_CHMOD_FILE)) {
    // 書き込み失敗
}

// WpPack Filesystem - シンプルで自動初期化
use WpPack\Component\Filesystem\Filesystem;

$filesystem = $container->get(Filesystem::class);

$content = $filesystem->read('path/to/file.txt');

$filesystem->write('path/to/file.txt', $content);
```

## Filesystem クラス

ファイル操作のメインエントリーポイントです：

```php
use WpPack\Component\Filesystem\Filesystem;

$filesystem = new Filesystem();

// 読み書き
$filesystem->write('/path/to/file.txt', 'content');
$content = $filesystem->read('/path/to/file.txt');
$filesystem->append('/path/to/file.txt', 'more content');

// 存在チェック
$exists = $filesystem->exists('/path/to/file.txt');
$isFile = $filesystem->isFile('/path/to/file.txt');
$isDir = $filesystem->isDirectory('/path/to/dir');

// 削除
$filesystem->delete('/path/to/file.txt');
$filesystem->deleteDirectory('/path/to/dir');

// コピー・移動
$filesystem->copy('/path/from.txt', '/path/to.txt');
$filesystem->move('/path/from.txt', '/path/to.txt');

// ディレクトリ作成
$filesystem->mkdir('/path/to/new-dir', recursive: true);

// パーミッション
$filesystem->chmod('/path/to/file.txt', 0644);

// ファイル情報
$size = $filesystem->size('/path/to/file.txt');
$mtime = $filesystem->lastModified('/path/to/file.txt');
$mimeType = $filesystem->mimeType('/path/to/file.txt');

// ディレクトリ一覧
$files = $filesystem->files('path/to/directory');
$directories = $filesystem->directories('path/to/directory');
$all = $filesystem->listContents('path/to/directory', recursive: true);
```

## オブジェクト指向ファイル操作

ファイルをオブジェクトとして扱います：

```php
use WpPack\Component\Filesystem\File;

$file = $filesystem->file('document.pdf');

// ファイル操作
$content = $file->read();
$file->write($newContent);
$file->append($additionalContent);
$file->delete();
$file->move('new/location/document.pdf');
$file->copy('backup/document.pdf');

// ファイルプロパティ
echo $file->name();          // document.pdf
echo $file->basename();      // document
echo $file->extension();     // pdf
echo $file->size();          // バイト数
echo $file->sizeForHumans(); // 1.5 MB
echo $file->mimeType();      // application/pdf
echo $file->path();          // フルパス
echo $file->directory();     // 親ディレクトリ

// ファイル状態チェック
if ($file->exists()) {
    if ($file->isReadable() && $file->isWritable()) {
        $file->chmod(0644);
    }
}
```

## WordPress アップロードディレクトリ統合

```php
use WpPack\Component\Filesystem\WordPress\UploadPath;

$uploadPath = new UploadPath();

// WordPress アップロードベースパス
$basePath = $uploadPath->getBasePath();    // /var/www/html/wp-content/uploads
$baseUrl = $uploadPath->getBaseUrl();      // https://example.com/wp-content/uploads

// 年月ベースのサブディレクトリ
$currentPath = $uploadPath->getCurrentPath();  // /var/www/html/wp-content/uploads/2024/01
$currentUrl = $uploadPath->getCurrentUrl();    // https://example.com/wp-content/uploads/2024/01

// カスタムサブディレクトリ
$customPath = $uploadPath->subdir('exports');
// /var/www/html/wp-content/uploads/exports
```

## TempFile

自動クリーンアップ付きの一時ファイル作成：

```php
use WpPack\Component\Filesystem\TempFile;

// 一時ファイル作成（スコープ終了時に自動削除）
$temp = TempFile::create(prefix: 'wppack_');
$filesystem->write($temp->getPath(), $content);

// 処理...

// $temp がスコープ外になると自動削除
```

## ファイルアップロード処理

### シンプルなアップロード

```php
$file = $filesystem->upload($_FILES['file'])
    ->to('uploads/documents')
    ->validate([
        'mimeTypes' => ['application/pdf', 'image/jpeg', 'image/png'],
        'maxSize' => '5M',
    ])
    ->filename(function ($file) {
        return date('Y-m-d') . '-' . uniqid() . '-' . $file->getClientOriginalName();
    })
    ->store();
```

### 複数ファイルアップロード

```php
use WpPack\Component\Filesystem\Upload\UploadedFile;

$files = $filesystem->upload($_FILES['documents'])
    ->each(function (UploadedFile $file) {
        return $file->validate(['maxSize' => '5M']);
    })
    ->to('uploads/batch')
    ->store();
```

### アップロード時の画像処理

```php
$image = $filesystem->upload($_FILES['avatar'])
    ->process(function ($path) {
        $image = wp_get_image_editor($path);
        if (!is_wp_error($image)) {
            $image->resize(200, 200, true);
            $image->save($path);
        }
    })
    ->to('uploads/avatars')
    ->store();
```

### WordPress AJAX アップロードハンドラー

```php
#[Action('wp_ajax_upload_file', priority: 10)]
#[Action('wp_ajax_nopriv_upload_file', priority: 10)]
public function ajaxUploadHandler(): void
{
    if (!wp_verify_nonce($_POST['_wpnonce'], 'file_upload')) {
        wp_send_json_error('Security check failed');
    }

    try {
        $file = $this->filesystem->upload($_FILES['file'])
            ->to('uploads/documents')
            ->validate([
                'mimeTypes' => ['application/pdf'],
                'maxSize' => '10M',
            ])
            ->store();

        wp_send_json_success([
            'name' => $file->name(),
            'path' => $file->path(),
            'size' => $file->sizeForHumans(),
        ]);
    } catch (\Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
```

## ファイルバリデーション

```php
use WpPack\Component\Filesystem\Validation\FileValidator;

$validator = $filesystem->validator()
    ->mimeTypes(['image/jpeg', 'image/png', 'image/gif'])
    ->extensions(['jpg', 'jpeg', 'png', 'gif'])
    ->maxSize('5M')
    ->minSize('10K')
    ->dimensions([
        'min_width' => 100,
        'min_height' => 100,
        'max_width' => 2000,
        'max_height' => 2000,
    ]);

if ($validator->validate('path/to/image.jpg')) {
    echo "File is valid";
} else {
    $errors = $validator->errors();
    foreach ($errors as $error) {
        echo "Validation error: {$error}\n";
    }
}

// バリデーション失敗時に例外をスロー
try {
    $validator->validateOrFail('path/to/image.jpg');
} catch (ValidationException $e) {
    echo "Validation failed: " . $e->getMessage();
}

// カスタムバリデーションルール
$validator->addRule('no_php_code', function ($path) {
    $content = file_get_contents($path);
    return strpos($content, '<?php') === false;
});
```

## WordPress 固有機能

```php
// WordPress アップロード統合
$filesystem->wordpress()->upload($_FILES['media'])
    ->asAttachment() // WordPress アタッチメントを作成
    ->withMetadata([
        'title' => 'My Document',
        'description' => 'Important document',
    ])
    ->toMediaLibrary();

// WordPress ディレクトリの取得
$uploadDir = $filesystem->wordpress()->uploadsDirectory();
$uploadUrl = $filesystem->wordpress()->uploadsUrl();
$themeDir = $filesystem->wordpress()->themeDirectory();
$pluginDir = $filesystem->wordpress()->pluginDirectory('my-plugin');

// WordPress アタッチメントの操作
$attachment = $filesystem->wordpress()->attachment(123);
$file = $attachment->file();
$metadata = $attachment->metadata();
$thumbnails = $attachment->thumbnails();

// テーマのファイルシステム操作
$theme = $filesystem->theme();
$template = $theme->read('templates/header.php');
$theme->write('custom/style.css', $css);

// プラグインのファイルシステム操作
$plugin = $filesystem->plugin('my-plugin');
$config = $plugin->readJson('config.json');
$plugin->writeJson('settings.json', $settings);
```

## Named Hook Attributes

### Filesystem Method フック

#### `#[FilesystemMethodFilter]`

**WordPress Hook:** `filesystem_method`

```php
use WpPack\Component\Filesystem\Attribute\FilesystemMethodFilter;

final class FilesystemMethodSelector
{
    public function __construct(
        private readonly FilesystemAnalyzer $analyzer,
    ) {}

    #[FilesystemMethodFilter(priority: 10)]
    public function selectFilesystemMethod(
        string $method,
        array $args,
        string $context,
        bool $allow_relaxed_file_ownership,
    ): string {
        // ローカル開発では direct メソッドを強制
        if (defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) {
            return 'direct';
        }

        // 本番サーバーでは SSH を使用
        if (wp_get_environment_type() === 'production' && $this->analyzer->hasSSHSupport()) {
            return 'ssh2';
        }

        // direct メソッドが安全かチェック
        if ($this->analyzer->canUseDirectMethod($context)) {
            return 'direct';
        }

        return $method;
    }
}
```

#### `#[FilesystemMethodFileFilter]`

**WordPress Hook:** `filesystem_method_file`

```php
use WpPack\Component\Filesystem\Attribute\FilesystemMethodFileFilter;

final class FilesystemDetectionCustomizer
{
    #[FilesystemMethodFileFilter(priority: 10)]
    public function customizeDetectionFile(string $file, string $method): string
    {
        if ($method === 'ssh2') {
            return ABSPATH . '.ssh-test';
        }

        if (defined('WPPACK_FS_TEST_MODE') && WPPACK_FS_TEST_MODE) {
            return wp_tempnam('fs-test');
        }

        return $file;
    }
}
```

### アップロードディレクトリフック

#### `#[UploadDirFilter]`

**WordPress Hook:** `upload_dir`

```php
use WpPack\Component\Filesystem\Attribute\UploadDirFilter;

final class UploadDirectoryManager
{
    #[UploadDirFilter(priority: 10)]
    public function customizeUploadDirectory(array $uploads): array
    {
        // 投稿タイプ別にアップロードを整理
        if ($post_id = $this->getCurrentPostId()) {
            $post_type = get_post_type($post_id);
            if ($post_type && $post_type !== 'post') {
                $uploads['path'] .= '/' . $post_type;
                $uploads['url'] .= '/' . $post_type;
                $uploads['subdir'] = '/' . $post_type . $uploads['subdir'];
            }
        }

        // 本番環境では CDN を使用
        if ($this->shouldUseCDN()) {
            $uploads['url'] = str_replace(
                home_url('/wp-content/uploads'),
                'https://cdn.example.com/uploads',
                $uploads['url']
            );
            $uploads['baseurl'] = 'https://cdn.example.com/uploads';
        }

        // ユーザーごとのアップロードを分離
        if (is_user_logged_in() && $this->isUserUpload()) {
            $user_id = get_current_user_id();
            $uploads['path'] .= '/users/' . $user_id;
            $uploads['url'] .= '/users/' . $user_id;
            $uploads['subdir'] .= '/users/' . $user_id;
        }

        if (!file_exists($uploads['path'])) {
            wp_mkdir_p($uploads['path']);
        }

        return $uploads;
    }
}
```

#### `#[WpUniqueFilenameFilter]`

**WordPress Hook:** `wp_unique_filename`

```php
use WpPack\Component\Filesystem\Attribute\WpUniqueFilenameFilter;

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
        $name = preg_replace('/-+/', '-', trim($name, '-'));

        // タイムスタンプを追加してユニーク性を向上
        $name = sprintf('%s_%s', $name, time());

        if (strlen($name) > 100) {
            $name = substr($name, 0, 100);
        }

        $filename = $name . '.' . $ext;

        $number = 2;
        while (file_exists($dir . '/' . $filename)) {
            $filename = sprintf('%s-%d.%s', $name, $number, $ext);
            $number++;
        }

        return $filename;
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
        $this->filesystem = new Filesystem();
        $this->testDir = sys_get_temp_dir() . '/wppack_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testDir)) {
            $this->filesystem->deleteDirectory($this->testDir);
        }
    }

    public function testWriteAndRead(): void
    {
        $filePath = $this->testDir . '/test.txt';
        $content = 'Hello, World!';

        $this->assertTrue($this->filesystem->write($filePath, $content));
        $this->assertTrue($this->filesystem->exists($filePath));
        $this->assertEquals($content, $this->filesystem->read($filePath));
    }

    public function testFileInfo(): void
    {
        $filePath = $this->testDir . '/info.txt';
        $content = 'File information test';

        $this->filesystem->write($filePath, $content);

        $this->assertEquals(strlen($content), $this->filesystem->size($filePath));
        $this->assertEquals('text/plain', $this->filesystem->mimeType($filePath));
    }

    public function testDirectoryOperations(): void
    {
        $dirPath = $this->testDir . '/subdir';

        $this->assertTrue($this->filesystem->createDirectory($dirPath));
        $this->assertTrue(is_dir($dirPath));

        $this->filesystem->write($dirPath . '/file.txt', 'test');

        $files = $this->filesystem->files($dirPath);
        $this->assertCount(1, $files);
    }
}
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `Filesystem` | ファイル操作のエントリーポイント |
| `File` | オブジェクト指向ファイル表現 |
| `TempFile` | 一時ファイル管理 |
| `WordPress\UploadPath` | WordPress アップロードパス統合 |
| `Upload\UploadedFile` | アップロードファイル処理 |
| `Validation\FileValidator` | ファイルバリデーション |
| `Attribute\FilesystemMethodFilter` | `filesystem_method` フィルター |
| `Attribute\UploadDirFilter` | `upload_dir` フィルター |
| `Attribute\WpUniqueFilenameFilter` | `wp_unique_filename` フィルター |
