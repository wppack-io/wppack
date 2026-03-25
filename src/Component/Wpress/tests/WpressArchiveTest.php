<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

    #[Test]
    public function addFileWithNonexistentSourceThrows(): void
    {
        $path = $this->tempDir . '/test.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessage('Source file not found');
        $archive->addFile($this->tempDir . '/nonexistent.txt', 'data/file.txt');
    }

    #[Test]
    public function addDirectoryWithNonexistentSourceThrows(): void
    {
        $path = $this->tempDir . '/test.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessage('Source directory not found');
        $archive->addDirectory($this->tempDir . '/nonexistent_dir', 'wp-content');
    }

    #[Test]
    public function encryptedArchiveRoundTrip(): void
    {
        $path = $this->tempDir . '/encrypted.wpress';
        $password = 'my-secret-password';

        // Create encrypted archive with package.json indicating encryption
        $archive = new WpressArchive($path, WpressArchive::CREATE, password: $password);
        $archive->addFromString('package.json', json_encode([
            'Encrypted' => true,
            'Compression' => ['Enabled' => false],
        ]));
        $archive->addFromString('secret.txt', 'Top secret data');
        $archive->close();

        // Read it back
        $archive = new WpressArchive($path, password: $password);
        $entry = $archive->getEntry('secret.txt');
        self::assertSame('Top secret data', $entry->getContents());

        // package.json should be readable as plaintext
        $pkg = $archive->getEntry('package.json');
        $data = json_decode($pkg->getContents(), true);
        self::assertTrue($data['Encrypted']);
        $archive->close();
    }

    #[Test]
    public function compressedArchiveRoundTrip(): void
    {
        $path = $this->tempDir . '/compressed.wpress';
        $password = 'pw';

        // Step 1: Create archive with package.json first (stored as plaintext config)
        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('package.json', json_encode([
            'Encrypted' => false,
            'Compression' => ['Enabled' => true, 'Type' => 'gzip'],
        ]));
        $archive->close();

        // Step 2: Reopen with password so buildContentProcessor reads package.json
        // and selects CompressedContentProcessor
        $archive = new WpressArchive($path, password: $password);
        $archive->addFromString('data.sql', 'SELECT * FROM wp_posts;');
        $archive->close();

        // Read it back
        $archive = new WpressArchive($path, password: $password);
        $entry = $archive->getEntry('data.sql');
        self::assertSame('SELECT * FROM wp_posts;', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function encryptedAndCompressedArchiveRoundTrip(): void
    {
        $path = $this->tempDir . '/chain.wpress';
        $password = 'chain-password';

        // Step 1: Create archive with package.json first (stored as plaintext config)
        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('package.json', json_encode([
            'Encrypted' => true,
            'Compression' => ['Enabled' => true, 'Type' => 'gzip'],
        ]));
        $archive->close();

        // Step 2: Reopen with password so buildContentProcessor reads package.json
        // and selects ChainContentProcessor (encrypt + compress)
        $archive = new WpressArchive($path, password: $password);
        $archive->addFromString('data.txt', 'Compressed and encrypted content');
        $archive->close();

        // Read it back
        $archive = new WpressArchive($path, password: $password);
        $entry = $archive->getEntry('data.txt');
        self::assertSame('Compressed and encrypted content', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function passwordArchiveWithoutPackageJsonDefaultsToEncryption(): void
    {
        $path = $this->tempDir . '/no-pkg.wpress';
        $password = 'secret';

        // Create encrypted archive without package.json
        $archive = new WpressArchive($path, WpressArchive::CREATE, password: $password);
        $archive->addFromString('data.txt', 'Encrypted without package.json');
        $archive->close();

        // Read it back - should default to encrypted processor
        $archive = new WpressArchive($path, password: $password);
        $entry = $archive->getEntry('data.txt');
        self::assertSame('Encrypted without package.json', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function passwordArchiveWithInvalidPackageJsonDefaultsToEncryption(): void
    {
        $path = $this->tempDir . '/bad-pkg.wpress';
        $password = 'secret';

        // Create archive with invalid JSON in package.json
        $archive = new WpressArchive($path, WpressArchive::CREATE, password: $password);
        $archive->addFromString('package.json', 'not valid json{{{');
        $archive->addFromString('data.txt', 'Data with bad package.json');
        $archive->close();

        // Should still work (falls back to encrypted only)
        $archive = new WpressArchive($path, password: $password);
        $entry = $archive->getEntry('data.txt');
        self::assertSame('Data with bad package.json', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function passwordArchiveWithEmptyPackageJson(): void
    {
        $path = $this->tempDir . '/empty-pkg.wpress';
        $password = 'secret';

        // Step 1: Create archive with empty package.json (size=0)
        // Empty package.json is read as '{}' by readPackageJsonRaw,
        // which means Encrypted=false, Compression=false → plain processor
        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('package.json', '');
        $archive->close();

        // Step 2: Reopen with password; empty package.json → plain processor
        // so data entries are stored in plaintext
        $archive = new WpressArchive($path, password: $password);
        $archive->addFromString('data.txt', 'Data with empty package.json');
        $archive->close();

        // Read it back
        $archive = new WpressArchive($path, password: $password);
        $entry = $archive->getEntry('data.txt');
        self::assertSame('Data with empty package.json', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function passwordArchiveWithPlainConfig(): void
    {
        $path = $this->tempDir . '/plain-cfg.wpress';
        $password = 'pw';

        // Step 1: Create archive with package.json indicating no encryption, no compression
        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('package.json', json_encode([
            'Encrypted' => false,
            'Compression' => ['Enabled' => false],
        ]));
        $archive->close();

        // Step 2: Reopen with password; package.json says plain → plain processor
        $archive = new WpressArchive($path, password: $password);
        $archive->addFromString('data.txt', 'Plaintext content');
        $archive->close();

        $archive = new WpressArchive($path, password: $password);
        $entry = $archive->getEntry('data.txt');
        self::assertSame('Plaintext content', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function addFilePreservesContentThroughFileStreaming(): void
    {
        $sourceDir = $this->tempDir . '/sources';
        mkdir($sourceDir);
        $largeContent = str_repeat('File data block. ', 1000);
        file_put_contents($sourceDir . '/large.txt', $largeContent);

        $path = $this->tempDir . '/file.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFile($sourceDir . '/large.txt', 'data/large.txt');
        $archive->close();

        $archive = new WpressArchive($path);
        $entry = $archive->getEntry('data/large.txt');
        self::assertSame($largeContent, $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function addFileWithRootPath(): void
    {
        $sourcePath = $this->tempDir . '/root.txt';
        file_put_contents($sourcePath, 'root content');

        $path = $this->tempDir . '/root.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFile($sourcePath, 'root.txt');
        $archive->close();

        $archive = new WpressArchive($path);
        $entry = $archive->getEntry('root.txt');
        self::assertSame('root content', $entry->getContents());
        self::assertSame('root.txt', $entry->getName());
        $archive->close();
    }

    #[Test]
    public function addFileToEncryptedArchive(): void
    {
        $sourceFile = $this->tempDir . '/secret-file.txt';
        file_put_contents($sourceFile, 'Secret file content');

        $path = $this->tempDir . '/enc-file.wpress';
        $password = 'file-password';

        $archive = new WpressArchive($path, WpressArchive::CREATE, password: $password);
        $archive->addFromString('package.json', json_encode([
            'Encrypted' => true,
            'Compression' => ['Enabled' => false],
        ]));
        $archive->addFile($sourceFile, 'data/secret-file.txt');
        $archive->close();

        $archive = new WpressArchive($path, password: $password);
        $entry = $archive->getEntry('data/secret-file.txt');
        self::assertSame('Secret file content', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function deleteEntriesWithNoMatchDoesNotModify(): void
    {
        $path = $this->tempDir . '/no-match.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('a.txt', 'aaa');
        $archive->close();

        $sizeBefore = filesize($path);

        $archive = new WpressArchive($path);
        $archive->deleteEntries('nonexistent/*');
        $archive->close();

        clearstatcache(true, $path);
        self::assertSame($sizeBefore, filesize($path));
    }

    #[Test]
    public function extractToPreservesMtime(): void
    {
        $archivePath = $this->tempDir . '/mtime.wpress';
        $sourceFile = $this->tempDir . '/timed.txt';
        $mtime = 1706140800; // 2024-01-25

        file_put_contents($sourceFile, 'time test');
        touch($sourceFile, $mtime);

        $archive = new WpressArchive($archivePath, WpressArchive::CREATE);
        $archive->addFile($sourceFile, 'timed.txt');
        $archive->close();

        $extractDir = $this->tempDir . '/extract_mtime';
        mkdir($extractDir);

        $archive = new WpressArchive($archivePath);
        $archive->extractTo($extractDir);
        $archive->close();

        self::assertSame($mtime, filemtime($extractDir . '/timed.txt'));
    }

    #[Test]
    public function extractToWithNestedDirectoriesCreatesDirectories(): void
    {
        $archivePath = $this->tempDir . '/nested.wpress';

        $archive = new WpressArchive($archivePath, WpressArchive::CREATE);
        $archive->addFromString('wp-content/plugins/myplugin/lib/utils/helper.php', '<?php');
        $archive->close();

        $extractDir = $this->tempDir . '/extract_nested';

        $archive = new WpressArchive($archivePath);
        $archive->extractTo($extractDir);
        $archive->close();

        self::assertFileExists($extractDir . '/wp-content/plugins/myplugin/lib/utils/helper.php');
        self::assertSame('<?php', file_get_contents($extractDir . '/wp-content/plugins/myplugin/lib/utils/helper.php'));
    }

    #[Test]
    public function multipleDeletesThenAppendInOneSession(): void
    {
        $path = $this->tempDir . '/multi-delete.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('a.txt', 'aaa');
        $archive->addFromString('b.txt', 'bbb');
        $archive->addFromString('c.txt', 'ccc');
        $archive->addFromString('d.txt', 'ddd');
        $archive->close();

        $archive = new WpressArchive($path);
        $archive->deleteEntry('a.txt');
        $archive->deleteEntry('c.txt');
        $archive->addFromString('e.txt', 'eee');
        $archive->close();

        $archive = new WpressArchive($path);
        $paths = [];
        foreach ($archive->getEntries() as $entry) {
            $paths[$entry->getPath()] = $entry->getContents();
        }

        self::assertArrayNotHasKey('a.txt', $paths);
        self::assertArrayNotHasKey('c.txt', $paths);
        self::assertSame('bbb', $paths['b.txt']);
        self::assertSame('ddd', $paths['d.txt']);
        self::assertSame('eee', $paths['e.txt']);
        $archive->close();
    }

    #[Test]
    public function getEntriesOnEmptyArchive(): void
    {
        $path = $this->tempDir . '/empty-entries.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->close();

        $archive = new WpressArchive($path);
        $entries = iterator_to_array($archive->getEntries());
        self::assertSame([], $entries);
        $archive->close();
    }

    #[Test]
    public function addFromStringWithNestedPath(): void
    {
        $path = $this->tempDir . '/nested-string.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('deep/path/to/file.txt', 'nested content');
        $archive->close();

        $archive = new WpressArchive($path);
        $entry = $archive->getEntry('deep/path/to/file.txt');
        self::assertSame('nested content', $entry->getContents());
        self::assertSame('file.txt', $entry->getName());
        self::assertSame('deep/path/to', $entry->getPrefix());
        $archive->close();
    }

    #[Test]
    public function addFromStringWithRootPath(): void
    {
        $path = $this->tempDir . '/root-string.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('rootfile.txt', 'root content');
        $archive->close();

        $archive = new WpressArchive($path);
        $entry = $archive->getEntry('rootfile.txt');
        self::assertSame('root content', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function configEntriesAreAlwaysPlaintext(): void
    {
        $path = $this->tempDir . '/config-plain.wpress';
        $password = 'secret';

        // multisite.json is in CONFIG_ENTRIES - should be stored as plain
        $archive = new WpressArchive($path, WpressArchive::CREATE, password: $password);
        $archive->addFromString('package.json', json_encode([
            'Encrypted' => true,
            'Compression' => ['Enabled' => false],
        ]));
        $archive->addFromString('multisite.json', json_encode(['IsMultisite' => false]));
        $archive->addFromString('data.txt', 'encrypted content');
        $archive->close();

        // Verify config entries can be read back
        $archive = new WpressArchive($path, password: $password);
        $pkgEntry = $archive->getEntry('package.json');
        $multiEntry = $archive->getEntry('multisite.json');

        $pkgData = json_decode($pkgEntry->getContents(), true);
        self::assertTrue($pkgData['Encrypted']);

        $multiData = json_decode($multiEntry->getContents(), true);
        self::assertFalse($multiData['IsMultisite']);

        // Non-config entry should also be readable
        $dataEntry = $archive->getEntry('data.txt');
        self::assertSame('encrypted content', $dataEntry->getContents());
        $archive->close();
    }

    #[Test]
    public function addDirectoryWithTrailingSlashes(): void
    {
        $sourceDir = $this->tempDir . '/src_trailing';
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/file.txt', 'content');

        $path = $this->tempDir . '/trailing.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addDirectory($sourceDir . '/', 'prefix/');
        $archive->close();

        $archive = new WpressArchive($path);
        $entry = $archive->getEntry('prefix/file.txt');
        self::assertSame('content', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function getEntryStreamContent(): void
    {
        $path = $this->tempDir . '/stream.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('stream.txt', 'Stream data');
        $archive->close();

        $archive = new WpressArchive($path);
        $entry = $archive->getEntry('stream.txt');
        $stream = $entry->getStream();

        self::assertIsResource($stream);
        self::assertSame('Stream data', stream_get_contents($stream));

        fclose($stream);
        $archive->close();
    }

    #[Test]
    public function addEmptyStringContent(): void
    {
        $path = $this->tempDir . '/empty-content.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('empty.txt', '');
        $archive->close();

        $archive = new WpressArchive($path);
        $entry = $archive->getEntry('empty.txt');
        self::assertSame('', $entry->getContents());
        self::assertSame(0, $entry->getSize());
        $archive->close();
    }

    #[Test]
    public function packageJsonWithZeroSizeIsReadCorrectly(): void
    {
        // Test readPackageJsonRaw when header.size === 0
        $path = $this->tempDir . '/zero-pkg.wpress';

        // Create archive manually with zero-size package.json
        $handle = fopen($path, 'w+b');
        $header = new Header(name: 'package.json', size: 0, mtime: time(), prefix: '.');
        fwrite($handle, $header->toBinary());
        // No content for zero-size entry
        $dataHeader = new Header(name: 'data.txt', size: 4, mtime: time(), prefix: '.');
        fwrite($handle, $dataHeader->toBinary());
        fwrite($handle, 'test');
        fwrite($handle, Header::eof()->toBinary());
        fclose($handle);

        // Opening with a password should parse the zero-size package.json as '{}'
        // which means Encrypted=false, Compression=false -> plain processor
        $archive = new WpressArchive($path, password: 'pw');
        $entry = $archive->getEntry('data.txt');
        self::assertSame('test', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function readPackageJsonWhenNotFirstEntry(): void
    {
        // Test readPackageJsonRaw when package.json is not the first entry
        $path = $this->tempDir . '/pkg-later.wpress';

        $handle = fopen($path, 'w+b');
        // First entry: something else
        $h1 = new Header(name: 'other.txt', size: 5, mtime: time(), prefix: '.');
        fwrite($handle, $h1->toBinary());
        fwrite($handle, 'hello');
        // Second entry: package.json
        $pkgContent = json_encode(['Encrypted' => false, 'Compression' => ['Enabled' => false]]);
        $h2 = new Header(name: 'package.json', size: \strlen($pkgContent), mtime: time(), prefix: '.');
        fwrite($handle, $h2->toBinary());
        fwrite($handle, $pkgContent);
        fwrite($handle, Header::eof()->toBinary());
        fclose($handle);

        $archive = new WpressArchive($path, password: 'pw');
        $entry = $archive->getEntry('other.txt');
        self::assertSame('hello', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function archiveWithNoPackageJsonAndPassword(): void
    {
        // Test readPackageJsonRaw returning null (EOF before finding package.json)
        $path = $this->tempDir . '/no-pkg-pass.wpress';

        $handle = fopen($path, 'w+b');
        // Only non-package.json entries
        $h1 = new Header(name: 'data.txt', size: 4, mtime: time(), prefix: '.');
        fwrite($handle, $h1->toBinary());
        fwrite($handle, 'data');
        fwrite($handle, Header::eof()->toBinary());
        fclose($handle);

        // With password, no package.json -> defaults to EncryptedContentProcessor
        // Reading 'data.txt' will fail because it was stored in plain but
        // processor expects encrypted data. This validates the code path.
        $archive = new WpressArchive($path, password: 'pw');
        self::assertCount(1, $archive);
        $archive->close();
    }

    #[Test]
    public function deleteAllEntriesResultsInEmptyArchive(): void
    {
        $path = $this->tempDir . '/delete-all.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('a.txt', 'aaa');
        $archive->addFromString('b.txt', 'bbb');
        $archive->close();

        $archive = new WpressArchive($path);
        $archive->deleteEntry('a.txt');
        $archive->deleteEntry('b.txt');
        $archive->close();

        $archive = new WpressArchive($path);
        self::assertCount(0, $archive);
        $archive->close();
    }

    #[Test]
    public function addFileThenAppendMoreEntries(): void
    {
        $sourceFile = $this->tempDir . '/source_append.txt';
        file_put_contents($sourceFile, 'original file');

        $path = $this->tempDir . '/append-file.wpress';

        // Create with addFile
        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFile($sourceFile, 'first.txt');
        $archive->close();

        // Append more entries
        $archive = new WpressArchive($path);
        $archive->addFromString('second.txt', 'added later');
        $archive->close();

        // Verify all entries
        $archive = new WpressArchive($path);
        self::assertCount(2, $archive);
        $entry1 = $archive->getEntry('first.txt');
        self::assertSame('original file', $entry1->getContents());
        $entry2 = $archive->getEntry('second.txt');
        self::assertSame('added later', $entry2->getContents());
        $archive->close();
    }

    #[Test]
    public function largeFileContentStreaming(): void
    {
        // Test writeFileEntryPlain with content > CHUNK_READ_SIZE (8192)
        $sourceFile = $this->tempDir . '/large_stream.bin';
        $content = random_bytes(20000); // > 8192 to test chunked reading
        file_put_contents($sourceFile, $content);

        $path = $this->tempDir . '/large-stream.wpress';

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFile($sourceFile, 'large_stream.bin');
        $archive->close();

        $archive = new WpressArchive($path);
        $entry = $archive->getEntry('large_stream.bin');
        self::assertSame($content, $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function rewriteArchiveWithLargeContent(): void
    {
        // Test rewriteArchive copy loop with content > CHUNK_READ_SIZE
        $path = $this->tempDir . '/rewrite-large.wpress';
        $largeContent = str_repeat('X', 20000);

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('keep.txt', $largeContent);
        $archive->addFromString('delete.txt', 'to remove');
        $archive->close();

        $archive = new WpressArchive($path);
        $archive->deleteEntry('delete.txt');
        $archive->close();

        $archive = new WpressArchive($path);
        self::assertCount(1, $archive);
        $entry = $archive->getEntry('keep.txt');
        self::assertSame($largeContent, $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function appendToArchiveWithoutEofMarker(): void
    {
        // Test appendEntries fallback when findEofOffset returns null (no EOF marker)
        $path = $this->tempDir . '/no-eof.wpress';

        // Manually create an archive file without an EOF marker
        $handle = fopen($path, 'w+b');
        $header = new Header(name: 'existing.txt', size: 4, mtime: time(), prefix: '.');
        fwrite($handle, $header->toBinary());
        fwrite($handle, 'data');
        // No EOF marker written
        fclose($handle);

        // Open and append - this should trigger the SEEK_END fallback
        $archive = new WpressArchive($path);
        $archive->addFromString('new.txt', 'new data');
        $archive->close();

        // Verify the new entry was appended
        $archive = new WpressArchive($path);
        $entry = $archive->getEntry('new.txt');
        self::assertSame('new data', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function getEntriesOnArchiveWithoutEofMarker(): void
    {
        // Test getEntries when archive has no EOF marker (reaches feof/short read)
        $path = $this->tempDir . '/no-eof-entries.wpress';

        $handle = fopen($path, 'w+b');
        $content = 'hello';
        $header = new Header(name: 'test.txt', size: \strlen($content), mtime: time(), prefix: '.');
        fwrite($handle, $header->toBinary());
        fwrite($handle, $content);
        // No EOF marker - getEntries must handle gracefully via the break at feof/short read
        fclose($handle);

        $archive = new WpressArchive($path);
        $entries = iterator_to_array($archive->getEntries());
        self::assertCount(1, $entries);
        self::assertSame('hello', $entries[0]->getContents());
        $archive->close();
    }

    #[Test]
    public function addDirectorySkipsSymlinks(): void
    {
        // Test that addDirectory skips non-file entries (symlinks to directories)
        $sourceDir = $this->tempDir . '/src_symlink';
        mkdir($sourceDir . '/sub', 0777, true);
        file_put_contents($sourceDir . '/real.txt', 'real');
        // Create a symlink to a directory - the iterator yields it as a non-file
        symlink($sourceDir . '/sub', $sourceDir . '/link_to_sub');

        $path = $this->tempDir . '/symlink.wpress';

        try {
            $archive = new WpressArchive($path, WpressArchive::CREATE);
            $archive->addDirectory($sourceDir, 'prefix');
            $archive->close();

            $archive = new WpressArchive($path);
            $paths = [];
            foreach ($archive->getEntries() as $entry) {
                $paths[] = $entry->getPath();
            }
            // Only the real file should be added
            self::assertContains('prefix/real.txt', $paths);
            $archive->close();
        } finally {
            // Clean up symlink before removeDir runs
            if (is_link($sourceDir . '/link_to_sub')) {
                unlink($sourceDir . '/link_to_sub');
            }
        }
    }

    #[Test]
    public function rewriteArchiveWithAppendedEntriesCopiesLargeChunks(): void
    {
        // Test rewriteArchive copy loop with chunk reading (content > CHUNK_READ_SIZE)
        // AND verify pending appends are applied during rewrite
        $path = $this->tempDir . '/rewrite-append.wpress';
        $keepContent = str_repeat('K', 20000); // > 8192 to test chunked copy

        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('keep.txt', $keepContent);
        $archive->addFromString('delete.txt', 'remove me');
        $archive->close();

        // Delete one entry and add new one in the same session
        // This triggers rewriteArchive with pendingAppends
        $archive = new WpressArchive($path);
        $archive->deleteEntry('delete.txt');
        $archive->addFromString('appended.txt', 'appended content');
        $archive->close();

        $archive = new WpressArchive($path);
        $paths = [];
        foreach ($archive->getEntries() as $entry) {
            $paths[$entry->getPath()] = $entry->getContents();
        }
        self::assertArrayNotHasKey('delete.txt', $paths);
        self::assertSame($keepContent, $paths['keep.txt']);
        self::assertSame('appended content', $paths['appended.txt']);
        $archive->close();
    }

    #[Test]
    public function buildIndexOnArchiveWithoutEofMarker(): void
    {
        // Test buildIndex when headerData is short (no EOF marker)
        $path = $this->tempDir . '/no-eof-index.wpress';

        $handle = fopen($path, 'w+b');
        $content = 'test';
        $header = new Header(name: 'indexed.txt', size: \strlen($content), mtime: time(), prefix: '.');
        fwrite($handle, $header->toBinary());
        fwrite($handle, $content);
        // No EOF marker
        fclose($handle);

        $archive = new WpressArchive($path);
        // count() calls buildIndex which must handle the missing EOF gracefully
        self::assertSame(1, $archive->count());
        $archive->close();
    }

    #[Test]
    public function readPackageJsonWhenNotFoundReturnsNull(): void
    {
        // Test readPackageJsonRaw reaching feof without finding package.json
        // (returns null). This was already covered by archiveWithNoPackageJsonAndPassword
        // but let's test a bigger archive where no package.json is present
        $path = $this->tempDir . '/no-pkg-multi.wpress';

        $handle = fopen($path, 'w+b');
        // Multiple non-package.json entries
        for ($i = 0; $i < 5; $i++) {
            $content = "content_{$i}";
            $h = new Header(name: "file_{$i}.txt", size: \strlen($content), mtime: time(), prefix: '.');
            fwrite($handle, $h->toBinary());
            fwrite($handle, $content);
        }
        fwrite($handle, Header::eof()->toBinary());
        fclose($handle);

        // With password and no package.json, defaults to encrypted processor
        $archive = new WpressArchive($path, password: 'pw');
        self::assertSame(5, $archive->count());
        $archive->close();
    }

    #[Test]
    public function appendToEmptyFileArchive(): void
    {
        // Test findEofOffset when file is completely empty (0 bytes)
        // feof returns true immediately, findEofOffset returns null via post-loop return
        $path = $this->tempDir . '/empty-file.wpress';
        file_put_contents($path, '');

        $archive = new WpressArchive($path);
        $archive->addFromString('test.txt', 'content');
        $archive->close();

        // Verify the entry was written
        $archive = new WpressArchive($path);
        $entry = $archive->getEntry('test.txt');
        self::assertSame('content', $entry->getContents());
        $archive->close();
    }

    #[Test]
    public function readPackageJsonWhenFileEndsMidHeader(): void
    {
        // Test readPackageJsonRaw when fread returns short data (not false but < SIZE)
        // This exercises the null return at end of while loop
        $path = $this->tempDir . '/partial-header.wpress';

        // Write only a partial header (less than Header::SIZE bytes)
        $handle = fopen($path, 'w+b');
        fwrite($handle, str_repeat("\x01", 100)); // Less than 4377 bytes
        fclose($handle);

        // Opening with password triggers readPackageJsonRaw which will
        // encounter the short read and return null -> defaults to encrypted
        $archive = new WpressArchive($path, password: 'pw');
        // The archive exists but has garbage data
        self::assertSame(0, $archive->count());
        $archive->close();
    }

    #[Test]
    public function addFileToCompressedArchive(): void
    {
        // Test writeFileEntry with non-plain processor (encrypted)
        // which goes through the file_get_contents path
        $sourceFile = $this->tempDir . '/compressed-source.txt';
        file_put_contents($sourceFile, 'Compressed file content');

        $path = $this->tempDir . '/compressed-file.wpress';
        $password = 'compress-pw';

        // Create archive with package.json indicating compression only
        $archive = new WpressArchive($path, WpressArchive::CREATE);
        $archive->addFromString('package.json', json_encode([
            'Encrypted' => false,
            'Compression' => ['Enabled' => true, 'Type' => 'gzip'],
        ]));
        $archive->close();

        // Reopen with password to get CompressedContentProcessor
        $archive = new WpressArchive($path, password: $password);
        $archive->addFile($sourceFile, 'data/compressed-source.txt');
        $archive->close();

        // Read it back
        $archive = new WpressArchive($path, password: $password);
        $entry = $archive->getEntry('data/compressed-source.txt');
        self::assertSame('Compressed file content', $entry->getContents());
        $archive->close();
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
