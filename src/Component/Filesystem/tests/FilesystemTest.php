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

namespace WPPack\Component\Filesystem\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Filesystem\Filesystem;

final class FilesystemTest extends TestCase
{
    private Filesystem $filesystem;
    private string $testDir;

    protected function setUp(): void
    {
        if (!defined('FS_CHMOD_DIR')) {
            define('FS_CHMOD_DIR', 0755);
        }

        if (!defined('FS_CHMOD_FILE')) {
            define('FS_CHMOD_FILE', 0644);
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

        $this->filesystem = new Filesystem(new \WP_Filesystem_Direct(null));
        $this->testDir = sys_get_temp_dir() . '/wppack_filesystem_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (!isset($this->testDir)) {
            return;
        }

        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    #[Test]
    public function writeAndRead(): void
    {
        $path = $this->testDir . '/test.txt';

        self::assertTrue($this->filesystem->write($path, 'Hello, World!'));
        self::assertSame('Hello, World!', $this->filesystem->read($path));
    }

    #[Test]
    public function append(): void
    {
        $path = $this->testDir . '/append.txt';

        $this->filesystem->write($path, 'Hello');
        $this->filesystem->append($path, ', World!');

        self::assertSame('Hello, World!', $this->filesystem->read($path));
    }

    #[Test]
    public function appendToNonExistentFileCreatesIt(): void
    {
        $path = $this->testDir . '/new_append.txt';

        self::assertTrue($this->filesystem->append($path, 'content'));
        self::assertSame('content', $this->filesystem->read($path));
    }

    #[Test]
    public function exists(): void
    {
        $path = $this->testDir . '/exists.txt';

        self::assertFalse($this->filesystem->exists($path));

        $this->filesystem->write($path, 'data');

        self::assertTrue($this->filesystem->exists($path));
    }

    #[Test]
    public function isFile(): void
    {
        $path = $this->testDir . '/file.txt';
        $this->filesystem->write($path, 'data');

        self::assertTrue($this->filesystem->isFile($path));
        self::assertFalse($this->filesystem->isFile($this->testDir));
    }

    #[Test]
    public function isDirectory(): void
    {
        $dir = $this->testDir . '/subdir';
        mkdir($dir);

        self::assertTrue($this->filesystem->isDirectory($dir));
        self::assertFalse($this->filesystem->isDirectory($this->testDir . '/file.txt'));
    }

    #[Test]
    public function delete(): void
    {
        $path = $this->testDir . '/delete.txt';
        $this->filesystem->write($path, 'data');

        self::assertTrue($this->filesystem->exists($path));
        self::assertTrue($this->filesystem->delete($path));
        self::assertFalse($this->filesystem->exists($path));
    }

    #[Test]
    public function deleteNonExistentFile(): void
    {
        // WP_Filesystem_Direct::delete() returns true for non-existent files
        self::assertTrue($this->filesystem->delete($this->testDir . '/nonexistent.txt'));
    }

    #[Test]
    public function deleteDirectory(): void
    {
        $dir = $this->testDir . '/to_delete';
        mkdir($dir);
        file_put_contents($dir . '/file.txt', 'data');
        mkdir($dir . '/sub');
        file_put_contents($dir . '/sub/nested.txt', 'nested');

        self::assertTrue($this->filesystem->deleteDirectory($dir));
        self::assertFalse(is_dir($dir));
    }

    #[Test]
    public function deleteDirectoryNonExistent(): void
    {
        // WP_Filesystem_Direct::rmdir() returns true for non-existent directories
        self::assertTrue($this->filesystem->deleteDirectory($this->testDir . '/nonexistent'));
    }

    #[Test]
    public function copy(): void
    {
        $source = $this->testDir . '/source.txt';
        $dest = $this->testDir . '/dest.txt';
        $this->filesystem->write($source, 'copy me');

        self::assertTrue($this->filesystem->copy($source, $dest));
        self::assertSame('copy me', $this->filesystem->read($dest));
        self::assertTrue($this->filesystem->exists($source));
    }

    #[Test]
    public function move(): void
    {
        $source = $this->testDir . '/move_source.txt';
        $dest = $this->testDir . '/move_dest.txt';
        $this->filesystem->write($source, 'move me');

        self::assertTrue($this->filesystem->move($source, $dest));
        self::assertSame('move me', $this->filesystem->read($dest));
        self::assertFalse($this->filesystem->exists($source));
    }

    #[Test]
    public function mkdir(): void
    {
        $dir = $this->testDir . '/newdir';

        self::assertTrue($this->filesystem->mkdir($dir));
        self::assertTrue(is_dir($dir));
    }

    #[Test]
    public function mkdirRecursive(): void
    {
        $dir = $this->testDir . '/a/b/c';

        self::assertTrue($this->filesystem->mkdir($dir, recursive: true));
        self::assertTrue(is_dir($dir));
    }

    #[Test]
    public function chmod(): void
    {
        $path = $this->testDir . '/chmod.txt';
        $this->filesystem->write($path, 'data');

        self::assertTrue($this->filesystem->chmod($path, 0644));

        $perms = fileperms($path) & 0777;
        self::assertSame(0644, $perms);
    }

    #[Test]
    public function fileSize(): void
    {
        $path = $this->testDir . '/size.txt';
        $content = 'Hello, World!';
        $this->filesystem->write($path, $content);

        self::assertSame(strlen($content), $this->filesystem->size($path));
    }

    #[Test]
    public function lastModified(): void
    {
        $path = $this->testDir . '/mtime.txt';
        $this->filesystem->write($path, 'data');

        $mtime = $this->filesystem->lastModified($path);
        self::assertIsInt($mtime);
        self::assertGreaterThan(0, $mtime);
        self::assertLessThanOrEqual(time(), $mtime);
    }

    #[Test]
    public function mimeType(): void
    {
        $path = $this->testDir . '/mime.txt';
        $this->filesystem->write($path, 'plain text content');

        self::assertSame('text/plain', $this->filesystem->mimeType($path));
    }

    #[Test]
    public function mimeTypeNonExistentFile(): void
    {
        self::assertNull($this->filesystem->mimeType($this->testDir . '/nonexistent.txt'));
    }

    #[Test]
    public function files(): void
    {
        $this->filesystem->write($this->testDir . '/a.txt', 'a');
        $this->filesystem->write($this->testDir . '/b.txt', 'b');
        mkdir($this->testDir . '/subdir');

        $files = $this->filesystem->files($this->testDir);

        self::assertSame(['a.txt', 'b.txt'], $files);
    }

    #[Test]
    public function directories(): void
    {
        mkdir($this->testDir . '/alpha');
        mkdir($this->testDir . '/beta');
        $this->filesystem->write($this->testDir . '/file.txt', 'data');

        $dirs = $this->filesystem->directories($this->testDir);

        self::assertSame(['alpha', 'beta'], $dirs);
    }

    #[Test]
    public function listContents(): void
    {
        $this->filesystem->write($this->testDir . '/file.txt', 'data');
        mkdir($this->testDir . '/subdir');

        $all = $this->filesystem->listContents($this->testDir);

        self::assertSame(['file.txt', 'subdir'], $all);
    }

    #[Test]
    public function listContentsRecursive(): void
    {
        mkdir($this->testDir . '/sub');
        $this->filesystem->write($this->testDir . '/root.txt', 'root');
        $this->filesystem->write($this->testDir . '/sub/nested.txt', 'nested');

        $all = $this->filesystem->listContents($this->testDir, recursive: true);

        self::assertSame(['root.txt', 'sub', 'sub/nested.txt'], $all);
    }

    #[Test]
    public function readNonExistentFile(): void
    {
        self::assertNull($this->filesystem->read($this->testDir . '/nonexistent.txt'));
    }

    #[Test]
    public function fileSizeNonExistentFile(): void
    {
        self::assertNull($this->filesystem->size($this->testDir . '/nonexistent.txt'));
    }

    #[Test]
    public function lastModifiedNonExistentFile(): void
    {
        self::assertNull($this->filesystem->lastModified($this->testDir . '/nonexistent.txt'));
    }

    #[Test]
    public function mkdirRecursiveOnExistingDirectory(): void
    {
        $dir = $this->testDir . '/already_exists';
        mkdir($dir, 0755, true);

        self::assertTrue($this->filesystem->mkdir($dir, recursive: true));
    }

    #[Test]
    public function filesOnNonExistentDirectory(): void
    {
        self::assertSame([], $this->filesystem->files($this->testDir . '/nonexistent'));
    }

    #[Test]
    public function directoriesOnNonExistentDirectory(): void
    {
        self::assertSame([], $this->filesystem->directories($this->testDir . '/nonexistent'));
    }

    #[Test]
    public function listContentsOnNonExistentDirectory(): void
    {
        self::assertSame([], $this->filesystem->listContents($this->testDir . '/nonexistent'));
    }

    private function removeDirectory(string $path): void
    {
        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . \DIRECTORY_SEPARATOR . $item;

            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }
}
