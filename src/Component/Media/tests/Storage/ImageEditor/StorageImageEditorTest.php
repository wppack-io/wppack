<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Media\Tests\Storage\ImageEditor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Media\Storage\ImageEditor\StorageImageEditor;

#[CoversClass(StorageImageEditor::class)]
final class StorageImageEditorTest extends TestCase
{
    private string $tempDir;

    /** @var list<string> */
    private array $cleanupFiles = [];

    protected function setUp(): void
    {
        if (!extension_loaded('imagick')) {
            self::markTestSkipped('The imagick extension is not available.');
        }

        // WordPress image editor classes are loaded lazily; ensure they are available
        if (!class_exists(\WP_Image_Editor::class, false)) {
            require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
        }
        if (!class_exists(\WP_Image_Editor_Imagick::class, false)) {
            require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
        }

        $this->tempDir = sys_get_temp_dir() . '/wppack_media_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        if (isset($this->tempDir) && is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($this->tempDir);
        }
    }

    #[Test]
    public function loadLocalFileSucceeds(): void
    {
        $imagePath = $this->createTestImage();

        $editor = new StorageImageEditor($imagePath);
        $result = $editor->load();

        self::assertTrue($result);
    }

    #[Test]
    public function loadFileSchemePathIsNotTreatedAsStreamWrapper(): void
    {
        $imagePath = $this->createTestImage();

        // file:// paths should be passed through to parent::load() directly
        $editor = new StorageImageEditor($imagePath);
        $result = $editor->load();

        self::assertTrue($result);

        // Verify no temp files were created (reflection check)
        $reflection = new \ReflectionClass($editor);
        $tempFilesProp = $reflection->getProperty('tempFiles');
        $tempFiles = $tempFilesProp->getValue($editor);

        self::assertEmpty($tempFiles);
    }

    #[Test]
    public function loadStreamWrapperPathDownloadsToTemp(): void
    {
        $this->registerTestStreamWrapper();

        $imagePath = $this->createTestImage();
        TestStreamWrapper::$files['wppacktest://bucket/image.jpg'] = file_get_contents($imagePath);

        $editor = new StorageImageEditor('wppacktest://bucket/image.jpg');
        $result = $editor->load();

        self::assertTrue($result);

        // Verify that temp files were created
        $reflection = new \ReflectionClass($editor);
        $tempFilesProp = $reflection->getProperty('tempFiles');
        $tempFiles = $tempFilesProp->getValue($editor);

        self::assertNotEmpty($tempFiles);

        $this->unregisterTestStreamWrapper();
    }

    #[Test]
    public function loadStreamWrapperPathReturnsErrorOnReadFailure(): void
    {
        $this->registerTestStreamWrapper();
        TestStreamWrapper::$files = [];

        $editor = new StorageImageEditor('wppacktest://bucket/nonexistent.jpg');
        $result = $editor->load();

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('image_editor_load_error', $result->get_error_code());
        self::assertStringContainsString('Failed to read file from storage', $result->get_error_message());

        $this->unregisterTestStreamWrapper();
    }

    #[Test]
    public function loadStreamWrapperExtractsExtension(): void
    {
        $this->registerTestStreamWrapper();

        $imagePath = $this->createTestImage();
        TestStreamWrapper::$files['wppacktest://bucket/photo.png'] = file_get_contents($imagePath);

        $editor = new StorageImageEditor('wppacktest://bucket/photo.png');
        $result = $editor->load();

        self::assertTrue($result);

        // Verify the temp file was created
        $reflection = new \ReflectionClass($editor);
        $tempFilesProp = $reflection->getProperty('tempFiles');
        $tempFiles = $tempFilesProp->getValue($editor);

        self::assertCount(1, $tempFiles);

        $this->unregisterTestStreamWrapper();
    }

    #[Test]
    public function saveToLocalFileSucceeds(): void
    {
        $imagePath = $this->createTestImage();
        $editor = new StorageImageEditor($imagePath);
        $editor->load();

        $destFile = $this->tempDir . '/saved_image.jpg';

        $result = $editor->save($destFile, 'image/jpeg');

        self::assertIsArray($result);
        self::assertArrayHasKey('path', $result);
        self::assertArrayHasKey('file', $result);
        self::assertArrayHasKey('width', $result);
        self::assertArrayHasKey('height', $result);
        self::assertArrayHasKey('mime-type', $result);

        if (is_array($result) && isset($result['path'])) {
            $this->cleanupFiles[] = $result['path'];
        }
    }

    #[Test]
    public function saveToStreamWrapperWritesToRemoteStorage(): void
    {
        $this->registerTestStreamWrapper();
        TestStreamWrapper::$files = [];

        $imagePath = $this->createTestImage();
        $editor = new StorageImageEditor($imagePath);
        $editor->load();

        // Use reflection to call _save directly with the Imagick image
        $reflection = new \ReflectionClass($editor);
        $imageProp = $reflection->getProperty('image');
        $imagickImage = $imageProp->getValue($editor);

        $saveMethod = $reflection->getMethod('_save');
        $result = $saveMethod->invoke(
            $editor,
            $imagickImage,
            'wppacktest://bucket/uploads/saved.jpg',
            'image/jpeg',
        );

        self::assertIsArray($result);
        self::assertStringStartsWith('wppacktest://bucket/uploads/', $result['path']);

        $this->unregisterTestStreamWrapper();
    }

    #[Test]
    public function saveToFileSchemeIsNotTreatedAsStreamWrapper(): void
    {
        $imagePath = $this->createTestImage();
        $editor = new StorageImageEditor($imagePath);
        $editor->load();

        $destFile = $this->tempDir . '/file_scheme_test.jpg';

        // Use reflection to call _save with file:// prefix
        $reflection = new \ReflectionClass($editor);
        $imageProp = $reflection->getProperty('image');
        $imagickImage = $imageProp->getValue($editor);

        $saveMethod = $reflection->getMethod('_save');
        $result = $saveMethod->invoke(
            $editor,
            $imagickImage,
            'file://' . $destFile,
            'image/jpeg',
        );

        self::assertIsArray($result);

        // Clean up
        if (is_array($result) && isset($result['path']) && file_exists($result['path'])) {
            $this->cleanupFiles[] = $result['path'];
        }
    }

    #[Test]
    public function saveWithNullFilenameUsesDefault(): void
    {
        $imagePath = $this->createTestImage();
        $editor = new StorageImageEditor($imagePath);
        $editor->load();

        $result = $editor->save(null, 'image/jpeg');

        self::assertIsArray($result);
        self::assertArrayHasKey('path', $result);

        if (is_array($result) && isset($result['path']) && file_exists($result['path'])) {
            $this->cleanupFiles[] = $result['path'];
        }
    }

    #[Test]
    public function destructCleansUpTempFiles(): void
    {
        $this->registerTestStreamWrapper();

        $imagePath = $this->createTestImage();
        TestStreamWrapper::$files['wppacktest://bucket/cleanup.jpg'] = file_get_contents($imagePath);

        $editor = new StorageImageEditor('wppacktest://bucket/cleanup.jpg');
        $editor->load();

        // Get temp file paths before destruction
        $reflection = new \ReflectionClass($editor);
        $tempFilesProp = $reflection->getProperty('tempFiles');
        $tempFiles = $tempFilesProp->getValue($editor);

        self::assertNotEmpty($tempFiles);
        foreach ($tempFiles as $tempFile) {
            self::assertFileExists($tempFile);
        }

        $storedTempFiles = $tempFiles;

        // Trigger __destruct
        unset($editor);

        // Verify temp files were cleaned up
        foreach ($storedTempFiles as $tempFile) {
            self::assertFileDoesNotExist($tempFile);
        }

        $this->unregisterTestStreamWrapper();
    }

    #[Test]
    public function destructHandlesAlreadyDeletedTempFiles(): void
    {
        $this->registerTestStreamWrapper();

        $imagePath = $this->createTestImage();
        TestStreamWrapper::$files['wppacktest://bucket/already-deleted.jpg'] = file_get_contents($imagePath);

        $editor = new StorageImageEditor('wppacktest://bucket/already-deleted.jpg');
        $editor->load();

        // Manually delete temp files before destruction
        $reflection = new \ReflectionClass($editor);
        $tempFilesProp = $reflection->getProperty('tempFiles');
        $tempFiles = $tempFilesProp->getValue($editor);

        foreach ($tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        // __destruct should not throw even if temp files are gone
        unset($editor);

        self::assertTrue(true); // If we get here, no exception was thrown

        $this->unregisterTestStreamWrapper();
    }

    #[Test]
    public function loadStreamWrapperPathWithoutExtension(): void
    {
        $this->registerTestStreamWrapper();

        $imagePath = $this->createTestImage();
        TestStreamWrapper::$files['wppacktest://bucket/no-extension-file'] = file_get_contents($imagePath);

        $editor = new StorageImageEditor('wppacktest://bucket/no-extension-file');
        $result = $editor->load();

        self::assertTrue($result);

        $this->unregisterTestStreamWrapper();
    }

    #[Test]
    public function saveToStreamWrapperReturnsErrorWhenWriteFails(): void
    {
        if (!in_array('wppackfail', stream_get_wrappers(), true)) {
            stream_wrapper_register('wppackfail', FailWriteStreamWrapper::class);
        }

        $imagePath = $this->createTestImage();
        $editor = new StorageImageEditor($imagePath);
        $editor->load();

        $reflection = new \ReflectionClass($editor);
        $imageProp = $reflection->getProperty('image');
        $imagickImage = $imageProp->getValue($editor);

        $saveMethod = $reflection->getMethod('_save');
        $result = $saveMethod->invoke(
            $editor,
            $imagickImage,
            'wppackfail://bucket/uploads/fail-write.jpg',
            'image/jpeg',
        );

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('image_editor_save_error', $result->get_error_code());
        self::assertStringContainsString('Failed to write to storage', $result->get_error_message());

        if (in_array('wppackfail', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('wppackfail');
        }
    }


    #[Test]
    public function saveReturnsWpErrorWhenParentSaveFails(): void
    {
        $imagePath = $this->createTestImage();
        $editor = new StorageImageEditor($imagePath);
        $editor->load();

        // Destroy the Imagick resource so parent::_save fails
        $reflection = new \ReflectionClass($editor);
        $imageProp = $reflection->getProperty('image');
        $imagickImage = $imageProp->getValue($editor);
        $imagickImage->clear();
        $imagickImage->destroy();

        $destFile = $this->tempDir . '/should_fail.jpg';
        $result = $editor->save($destFile, 'image/jpeg');

        self::assertInstanceOf(\WP_Error::class, $result);
    }

    #[Test]
    public function saveStreamWrapperReturnsWpErrorWhenParentSaveFails(): void
    {
        $this->registerTestStreamWrapper();
        TestStreamWrapper::$files = [];

        $imagePath = $this->createTestImage();
        $editor = new StorageImageEditor($imagePath);
        $editor->load();

        // Destroy the Imagick resource so parent::_save fails
        $reflection = new \ReflectionClass($editor);
        $imageProp = $reflection->getProperty('image');
        $imagickImage = $imageProp->getValue($editor);
        $imagickImage->clear();
        $imagickImage->destroy();

        $saveMethod = $reflection->getMethod('_save');
        $result = $saveMethod->invoke(
            $editor,
            new \Imagick(),
            'wppacktest://bucket/uploads/fail.jpg',
            'image/jpeg',
        );

        self::assertInstanceOf(\WP_Error::class, $result);

        $this->unregisterTestStreamWrapper();
    }

    /**
     * Create a minimal test JPEG image.
     */
    private function createTestImage(int $width = 10, int $height = 10): string
    {
        $imagePath = $this->tempDir . '/test_image_' . uniqid() . '.jpg';

        $imagick = new \Imagick();
        $imagick->newImage($width, $height, new \ImagickPixel('red'));
        $imagick->setImageFormat('jpeg');
        $imagick->writeImage($imagePath);
        $imagick->clear();
        $imagick->destroy();

        $this->cleanupFiles[] = $imagePath;

        return $imagePath;
    }

    private function registerTestStreamWrapper(): void
    {
        if (!in_array('wppacktest', stream_get_wrappers(), true)) {
            stream_wrapper_register('wppacktest', TestStreamWrapper::class);
        }
    }

    private function unregisterTestStreamWrapper(): void
    {
        if (in_array('wppacktest', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('wppacktest');
        }
    }
}

/**
 * Minimal stream wrapper for testing stream-based file operations.
 */
class TestStreamWrapper
{
    /** @var resource Stream context set by PHP */
    public $context;

    /** @var array<string, string> */
    public static array $files = [];

    /** @var resource|false */
    private $stream = false;
    private string $path = '';

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->path = $path;

        if (str_contains($mode, 'r')) {
            if (!isset(self::$files[$path])) {
                return false;
            }
            $this->stream = fopen('php://memory', 'r+');
            if ($this->stream === false) {
                return false;
            }
            fwrite($this->stream, self::$files[$path]);
            rewind($this->stream);

            return true;
        }

        if (str_contains($mode, 'w') || str_contains($mode, 'a')) {
            $this->stream = fopen('php://memory', 'r+');

            return $this->stream !== false;
        }

        return false;
    }

    public function stream_read(int $count): string|false
    {
        if ($this->stream === false) {
            return false;
        }

        return fread($this->stream, $count);
    }

    public function stream_write(string $data): int
    {
        if ($this->stream === false) {
            return 0;
        }

        $written = fwrite($this->stream, $data);
        rewind($this->stream);
        self::$files[$this->path] = stream_get_contents($this->stream);
        fseek($this->stream, 0, \SEEK_END);

        return $written !== false ? $written : 0;
    }

    public function stream_eof(): bool
    {
        if ($this->stream === false) {
            return true;
        }

        return feof($this->stream);
    }

    public function stream_stat(): array|false
    {
        $size = isset(self::$files[$this->path]) ? strlen(self::$files[$this->path]) : 0;

        return [
            'size' => $size,
            7 => $size,
        ];
    }

    public function url_stat(string $path, int $flags): array|false
    {
        if (!isset(self::$files[$path])) {
            return false;
        }

        $size = strlen(self::$files[$path]);

        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0100644,
            'nlink' => 1,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => $size,
            'atime' => time(),
            'mtime' => time(),
            'ctime' => time(),
            'blksize' => -1,
            'blocks' => -1,
            0 => 0,
            1 => 0,
            2 => 0100644,
            3 => 1,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => $size,
            8 => time(),
            9 => time(),
            10 => time(),
            11 => -1,
            12 => -1,
        ];
    }

    public function stream_close(): void
    {
        if ($this->stream !== false) {
            fclose($this->stream);
        }
    }

    public function stream_tell(): int
    {
        if ($this->stream === false) {
            return 0;
        }

        $pos = ftell($this->stream);

        return $pos !== false ? $pos : 0;
    }

    public function stream_seek(int $offset, int $whence = \SEEK_SET): bool
    {
        if ($this->stream === false) {
            return false;
        }

        return fseek($this->stream, $offset, $whence) === 0;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return true;
    }

    public function unlink(string $path): bool
    {
        unset(self::$files[$path]);

        return true;
    }

    public function rename(string $pathFrom, string $pathTo): bool
    {
        if (isset(self::$files[$pathFrom])) {
            self::$files[$pathTo] = self::$files[$pathFrom];
            unset(self::$files[$pathFrom]);

            return true;
        }

        return false;
    }
}

/**
 * Stream wrapper that always fails on write (stream_open returns false for write mode).
 * Used to test file_put_contents failure in StorageImageEditor::_save().
 */
class FailWriteStreamWrapper
{
    /** @var resource Stream context set by PHP */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        // Always fail - prevents file_put_contents from writing
        return false;
    }

    public function url_stat(string $path, int $flags): array|false
    {
        return false;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return true;
    }
}
