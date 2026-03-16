<?php

declare(strict_types=1);

namespace WpPack\Component\Wpress\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Wpress\Exception\ArchiveException;
use WpPack\Component\Wpress\Exception\EntryNotFoundException;
use WpPack\Component\Wpress\Header;
use WpPack\Component\Wpress\WpressArchive;

final class WpressArchiveTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/wpress_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function createAndReadArchive(): void
    {
        $path = $this->tempDir . '/test.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('package.json', '{"SiteURL":"https://example.com"}');
        $archive->addFromString('database.sql', 'DROP TABLE test;');
        $archive->close();

        $archive = new WpressArchive($path);
        $entries = iterator_to_array($archive->getEntries());

        self::assertCount(2, $entries);
        self::assertSame('package.json', $entries[0]->getPath());
        self::assertSame('database.sql', $entries[1]->getPath());
        $archive->close();
    }

    #[Test]
    public function getEntryByPath(): void
    {
        $path = $this->tempDir . '/test.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('package.json', '{"SiteURL":"https://example.com"}');
        $archive->addFromString('database.sql', 'SELECT 1;');
        $archive->close();

        $archive = new WpressArchive($path);
        $entry = $archive->getEntry('database.sql');

        self::assertSame('SELECT 1;', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function getEntryNotFoundThrows(): void
    {
        $path = $this->tempDir . '/test.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('package.json', '{}');
        $archive->close();

        $archive = new WpressArchive($path);

        $this->expectException(EntryNotFoundException::class);
        $archive->getEntry('nonexistent.txt');
    }

    #[Test]
    public function countEntries(): void
    {
        $path = $this->tempDir . '/test.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('a.txt', 'aaa');
        $archive->addFromString('b.txt', 'bbb');
        $archive->addFromString('c.txt', 'ccc');
        $archive->close();

        $archive = new WpressArchive($path);

        self::assertCount(3, $archive);
        $archive->close();
    }

    #[Test]
    public function addFile(): void
    {
        $sourcePath = $this->tempDir . '/source.txt';
        file_put_contents($sourcePath, 'File content here');

        $archivePath = $this->tempDir . '/test.wpress';

        $archive = new WpressArchive($archivePath, WpressArchive::CREATE);
        $archive->addFile($sourcePath, 'wp-content/data/source.txt');
        $archive->close();

        $archive = new WpressArchive($archivePath);
        $entry = $archive->getEntry('wp-content/data/source.txt');

        self::assertSame('File content here', $entry->getContents());
        self::assertSame('source.txt', $entry->getName());
        self::assertSame('wp-content/data', $entry->getPrefix());
        $archive->close();
    }

    #[Test]
    public function addDirectory(): void
    {
        $sourceDir = $this->tempDir . '/source';
        mkdir($sourceDir . '/sub', 0777, true);
        file_put_contents($sourceDir . '/file1.txt', 'Content 1');
        file_put_contents($sourceDir . '/sub/file2.txt', 'Content 2');

        $archivePath = $this->tempDir . '/test.wpress';

        $archive = new WpressArchive($archivePath, WpressArchive::CREATE);
        $archive->addDirectory($sourceDir, 'wp-content/uploads');
        $archive->close();

        $archive = new WpressArchive($archivePath);
        $paths = [];

        foreach ($archive->getEntries() as $entry) {
            $paths[$entry->getPath()] = $entry->getContents();
        }

        self::assertArrayHasKey('wp-content/uploads/file1.txt', $paths);
        self::assertArrayHasKey('wp-content/uploads/sub/file2.txt', $paths);
        self::assertSame('Content 1', $paths['wp-content/uploads/file1.txt']);
        self::assertSame('Content 2', $paths['wp-content/uploads/sub/file2.txt']);
        $archive->close();
    }

    #[Test]
    public function extractTo(): void
    {
        $archivePath = $this->tempDir . '/test.wpress';

        $archive = new WpressArchive($archivePath, WpressArchive::CREATE);
        $archive->addFromString('package.json', '{}');
        $archive->addFromString('wp-content/uploads/image.jpg', 'fake-image-data');
        $archive->addFromString('wp-content/themes/flavor/style.css', 'body {}');
        $archive->close();

        $extractDir = $this->tempDir . '/extracted';
        mkdir($extractDir);

        $archive = new WpressArchive($archivePath);
        $archive->extractTo($extractDir);
        $archive->close();

        self::assertFileExists($extractDir . '/package.json');
        self::assertFileExists($extractDir . '/wp-content/uploads/image.jpg');
        self::assertFileExists($extractDir . '/wp-content/themes/flavor/style.css');
        self::assertSame('fake-image-data', file_get_contents($extractDir . '/wp-content/uploads/image.jpg'));
    }

    #[Test]
    public function extractToWithFilter(): void
    {
        $archivePath = $this->tempDir . '/test.wpress';

        $archive = new WpressArchive($archivePath, WpressArchive::CREATE);
        $archive->addFromString('package.json', '{}');
        $archive->addFromString('wp-content/uploads/image.jpg', 'image');
        $archive->addFromString('wp-content/themes/flavor/style.css', 'css');
        $archive->close();

        $extractDir = $this->tempDir . '/extracted';
        mkdir($extractDir);

        $archive = new WpressArchive($archivePath);
        $archive->extractTo($extractDir, filter: 'wp-content/uploads/');
        $archive->close();

        self::assertFileExists($extractDir . '/wp-content/uploads/image.jpg');
        self::assertFileDoesNotExist($extractDir . '/package.json');
        self::assertFileDoesNotExist($extractDir . '/wp-content/themes/flavor/style.css');
    }

    #[Test]
    public function deleteEntry(): void
    {
        $archivePath = $this->tempDir . '/test.wpress';

        $archive = new WpressArchive($archivePath, WpressArchive::CREATE);
        $archive->addFromString('a.txt', 'aaa');
        $archive->addFromString('b.txt', 'bbb');
        $archive->addFromString('c.txt', 'ccc');
        $archive->close();

        $archive = new WpressArchive($archivePath);
        $archive->deleteEntry('b.txt');
        $archive->close();

        $archive = new WpressArchive($archivePath);
        $paths = [];

        foreach ($archive->getEntries() as $entry) {
            $paths[] = $entry->getPath();
        }

        self::assertSame(['a.txt', 'c.txt'], $paths);
        $archive->close();
    }

    #[Test]
    public function deleteEntriesByPattern(): void
    {
        $archivePath = $this->tempDir . '/test.wpress';

        $archive = new WpressArchive($archivePath, WpressArchive::CREATE);
        $archive->addFromString('package.json', '{}');
        $archive->addFromString('wp-content/cache/file1.tmp', 'cache1');
        $archive->addFromString('wp-content/cache/file2.tmp', 'cache2');
        $archive->addFromString('wp-content/uploads/image.jpg', 'image');
        $archive->close();

        $archive = new WpressArchive($archivePath);
        $archive->deleteEntries('wp-content/cache/*');
        $archive->close();

        $archive = new WpressArchive($archivePath);
        $paths = [];

        foreach ($archive->getEntries() as $entry) {
            $paths[] = $entry->getPath();
        }

        self::assertSame(['package.json', 'wp-content/uploads/image.jpg'], $paths);
        $archive->close();
    }

    #[Test]
    public function roundTripMultipleEntries(): void
    {
        $archivePath = $this->tempDir . '/roundtrip.wpress';

        $entries = [
            'package.json' => '{"SiteURL":"https://example.com","HomeURL":"https://example.com"}',
            'database.sql' => "DROP TABLE IF EXISTS wp_posts;\nCREATE TABLE wp_posts (id INT);",
            'wp-content/uploads/2024/01/photo.jpg' => str_repeat("\xFF\xD8\xFF", 100),
            'wp-content/themes/flavor/style.css' => 'body { margin: 0; }',
            'wp-content/plugins/my-plugin/my-plugin.php' => '<?php /* Plugin */' . "\n",
        ];

        $archive = new WpressArchive($archivePath, WpressArchive::CREATE);

        foreach ($entries as $path => $content) {
            $archive->addFromString($path, $content);
        }

        $archive->close();

        // Read back
        $archive = new WpressArchive($archivePath);
        $restored = [];

        foreach ($archive->getEntries() as $entry) {
            $restored[$entry->getPath()] = $entry->getContents();
        }

        self::assertSame($entries, $restored);
        $archive->close();
    }

    #[Test]
    public function appendToExistingArchive(): void
    {
        $archivePath = $this->tempDir . '/append.wpress';

        // Create with 2 entries
        $archive = new WpressArchive($archivePath, WpressArchive::CREATE);
        $archive->addFromString('a.txt', 'aaa');
        $archive->addFromString('b.txt', 'bbb');
        $archive->close();

        // Append 1 more
        $archive = new WpressArchive($archivePath);
        $archive->addFromString('c.txt', 'ccc');
        $archive->close();

        // Verify all 3
        $archive = new WpressArchive($archivePath);
        self::assertCount(3, $archive);

        $paths = [];

        foreach ($archive->getEntries() as $entry) {
            $paths[] = $entry->getPath();
        }

        self::assertSame(['a.txt', 'b.txt', 'c.txt'], $paths);
        $archive->close();
    }

    #[Test]
    public function openNonexistentFileThrows(): void
    {
        $this->expectException(ArchiveException::class);

        new WpressArchive($this->tempDir . '/nonexistent.wpress');
    }

    #[Test]
    public function emptyArchiveHasZeroEntries(): void
    {
        $path = $this->tempDir . '/empty.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->close();

        $archive = new WpressArchive($path);
        self::assertCount(0, $archive);
        $archive->close();
    }

    #[Test]
    public function manyEntries(): void
    {
        $path = $this->tempDir . '/many.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);

        for ($i = 0; $i < 100; $i++) {
            $archive->addFromString("files/file_{$i}.txt", "Content of file {$i}");
        }

        $archive->close();

        $archive = new WpressArchive($path);
        self::assertCount(100, $archive);

        $entry = $archive->getEntry('files/file_50.txt');
        self::assertSame('Content of file 50', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function deleteAndAddInSameSession(): void
    {
        $path = $this->tempDir . '/mixed.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('old.txt', 'old content');
        $archive->addFromString('keep.txt', 'keep this');
        $archive->close();

        $archive = new WpressArchive($path);
        $archive->deleteEntry('old.txt');
        $archive->addFromString('new.txt', 'new content');
        $archive->close();

        $archive = new WpressArchive($path);
        $paths = [];

        foreach ($archive->getEntries() as $entry) {
            $paths[$entry->getPath()] = $entry->getContents();
        }

        self::assertArrayNotHasKey('old.txt', $paths);
        self::assertSame('keep this', $paths['keep.txt']);
        self::assertSame('new content', $paths['new.txt']);
        $archive->close();
    }

    #[Test]
    public function binaryContentPreserved(): void
    {
        $path = $this->tempDir . '/binary.wpress';
        $binaryContent = random_bytes(4096);

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('binary.dat', $binaryContent);
        $archive->close();

        $archive = new WpressArchive($path);
        $entry = $archive->getEntry('binary.dat');
        self::assertSame($binaryContent, $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function closeWithoutModificationIsNoop(): void
    {
        $path = $this->tempDir . '/noop.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('test.txt', 'data');
        $archive->close();

        $sizeBefore = filesize($path);

        // Open and close without modification
        $archive = new WpressArchive($path);
        $archive->close();

        self::assertSame($sizeBefore, filesize($path));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
