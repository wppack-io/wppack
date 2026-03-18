<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Tests\StreamWrapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Storage\StreamWrapper\StatCache;
use WpPack\Component\Storage\StreamWrapper\StorageStreamWrapper;
use WpPack\Component\Storage\Test\InMemoryStorageAdapter;

#[CoversClass(StorageStreamWrapper::class)]
#[CoversClass(StatCache::class)]
final class StorageStreamWrapperTest extends TestCase
{
    private const PROTOCOL = 'swtest';

    private InMemoryStorageAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryStorageAdapter();
        StorageStreamWrapper::register(self::PROTOCOL, $this->adapter);
    }

    protected function tearDown(): void
    {
        StorageStreamWrapper::unregister(self::PROTOCOL);
    }

    // ──────────────────────────────────────────────
    // Registration
    // ──────────────────────────────────────────────

    public function testRegisterAndUnregister(): void
    {
        self::assertContains(self::PROTOCOL, stream_get_wrappers());

        StorageStreamWrapper::unregister(self::PROTOCOL);

        self::assertNotContains(self::PROTOCOL, stream_get_wrappers());
    }

    public function testGetAdapter(): void
    {
        self::assertSame($this->adapter, StorageStreamWrapper::getAdapter(self::PROTOCOL));
    }

    public function testGetStatCache(): void
    {
        self::assertInstanceOf(StatCache::class, StorageStreamWrapper::getStatCache(self::PROTOCOL));
    }

    // ──────────────────────────────────────────────
    // file_get_contents / file_put_contents
    // ──────────────────────────────────────────────

    public function testFilePutContentsAndFileGetContents(): void
    {
        $path = self::PROTOCOL . '://test/hello.txt';

        file_put_contents($path, 'Hello, World!');

        self::assertSame('Hello, World!', file_get_contents($path));
        self::assertSame('Hello, World!', $this->adapter->read('test/hello.txt'));
    }

    // ──────────────────────────────────────────────
    // fopen modes
    // ──────────────────────────────────────────────

    public function testModeR(): void
    {
        $this->adapter->write('file.txt', 'contents');

        $fp = fopen(self::PROTOCOL . '://file.txt', 'r');
        self::assertIsResource($fp);

        $data = fread($fp, 1024);
        self::assertSame('contents', $data);

        // r mode is read-only, writes should return 0
        $written = fwrite($fp, 'extra');
        self::assertSame(0, $written);

        fclose($fp);

        // Original content unchanged
        self::assertSame('contents', $this->adapter->read('file.txt'));
    }

    public function testModeRPlus(): void
    {
        $this->adapter->write('file.txt', 'hello');

        $fp = fopen(self::PROTOCOL . '://file.txt', 'r+');
        self::assertIsResource($fp);

        self::assertSame('hello', fread($fp, 1024));

        rewind($fp);
        fwrite($fp, 'HELLO');
        fclose($fp);

        self::assertSame('HELLO', $this->adapter->read('file.txt'));
    }

    public function testModeW(): void
    {
        $fp = fopen(self::PROTOCOL . '://newfile.txt', 'w');
        self::assertIsResource($fp);

        fwrite($fp, 'new content');
        fclose($fp);

        self::assertSame('new content', $this->adapter->read('newfile.txt'));
    }

    public function testModeWOverwritesExisting(): void
    {
        $this->adapter->write('file.txt', 'old content');

        $fp = fopen(self::PROTOCOL . '://file.txt', 'w');
        fwrite($fp, 'new');
        fclose($fp);

        self::assertSame('new', $this->adapter->read('file.txt'));
    }

    public function testModeWPlus(): void
    {
        $fp = fopen(self::PROTOCOL . '://file.txt', 'w+');
        self::assertIsResource($fp);

        fwrite($fp, 'data');
        rewind($fp);
        self::assertSame('data', fread($fp, 1024));

        fclose($fp);

        self::assertSame('data', $this->adapter->read('file.txt'));
    }

    public function testModeA(): void
    {
        $this->adapter->write('file.txt', 'Hello');

        $fp = fopen(self::PROTOCOL . '://file.txt', 'a');
        self::assertIsResource($fp);

        fwrite($fp, ' World');
        fclose($fp);

        self::assertSame('Hello World', $this->adapter->read('file.txt'));
    }

    public function testModeACreatesNewFile(): void
    {
        $fp = fopen(self::PROTOCOL . '://new.txt', 'a');
        self::assertIsResource($fp);

        fwrite($fp, 'appended');
        fclose($fp);

        self::assertSame('appended', $this->adapter->read('new.txt'));
    }

    public function testModeAPlus(): void
    {
        $this->adapter->write('file.txt', 'Start');

        $fp = fopen(self::PROTOCOL . '://file.txt', 'a+');
        self::assertIsResource($fp);

        fwrite($fp, ' End');

        // Can read back the full content
        rewind($fp);
        self::assertSame('Start End', stream_get_contents($fp));

        fclose($fp);

        self::assertSame('Start End', $this->adapter->read('file.txt'));
    }

    public function testModeXCreatesNewFile(): void
    {
        $fp = fopen(self::PROTOCOL . '://exclusive.txt', 'x');
        self::assertIsResource($fp);

        fwrite($fp, 'exclusive');
        fclose($fp);

        self::assertSame('exclusive', $this->adapter->read('exclusive.txt'));
    }

    public function testModeXFailsIfFileExists(): void
    {
        $this->adapter->write('existing.txt', 'content');

        $fp = @fopen(self::PROTOCOL . '://existing.txt', 'x');
        self::assertFalse($fp);
    }

    public function testModeXPlus(): void
    {
        $fp = fopen(self::PROTOCOL . '://xplus.txt', 'x+');
        self::assertIsResource($fp);

        fwrite($fp, 'data');
        rewind($fp);
        self::assertSame('data', fread($fp, 1024));

        fclose($fp);

        self::assertSame('data', $this->adapter->read('xplus.txt'));
    }

    public function testModeC(): void
    {
        $fp = fopen(self::PROTOCOL . '://cfile.txt', 'c');
        self::assertIsResource($fp);

        fwrite($fp, 'content');
        fclose($fp);

        self::assertSame('content', $this->adapter->read('cfile.txt'));
    }

    public function testModeCPlus(): void
    {
        $this->adapter->write('cplus.txt', 'existing');

        $fp = fopen(self::PROTOCOL . '://cplus.txt', 'c+');
        self::assertIsResource($fp);

        // Can read existing content
        self::assertSame('existing', fread($fp, 1024));

        // Can write
        rewind($fp);
        fwrite($fp, 'REPLACED');
        fclose($fp);

        self::assertSame('REPLACED', $this->adapter->read('cplus.txt'));
    }

    public function testModeCPlusCreatesNewFile(): void
    {
        $fp = fopen(self::PROTOCOL . '://cplusnew.txt', 'c+');
        self::assertIsResource($fp);

        fwrite($fp, 'new');
        fclose($fp);

        self::assertSame('new', $this->adapter->read('cplusnew.txt'));
    }

    // ──────────────────────────────────────────────
    // Stream operations
    // ──────────────────────────────────────────────

    public function testFseekAndFtell(): void
    {
        $this->adapter->write('file.txt', 'ABCDEF');

        $fp = fopen(self::PROTOCOL . '://file.txt', 'r');
        self::assertSame(0, ftell($fp));

        fseek($fp, 3);
        self::assertSame(3, ftell($fp));
        self::assertSame('DEF', fread($fp, 3));

        fclose($fp);
    }

    public function testFeof(): void
    {
        $this->adapter->write('file.txt', 'AB');

        $fp = fopen(self::PROTOCOL . '://file.txt', 'r');
        self::assertFalse(feof($fp));

        fread($fp, 2);
        // After reading to end, next read triggers EOF
        fread($fp, 1);
        self::assertTrue(feof($fp));

        fclose($fp);
    }

    public function testFtruncate(): void
    {
        $fp = fopen(self::PROTOCOL . '://trunc.txt', 'w+');
        fwrite($fp, 'Hello World');

        ftruncate($fp, 5);
        rewind($fp);
        self::assertSame('Hello', stream_get_contents($fp));

        fclose($fp);
    }

    // ──────────────────────────────────────────────
    // file_exists / is_file / is_dir / filesize
    // ──────────────────────────────────────────────

    public function testFileExists(): void
    {
        self::assertFalse(file_exists(self::PROTOCOL . '://nonexistent.txt'));

        $this->adapter->write('exists.txt', 'data');
        self::assertTrue(file_exists(self::PROTOCOL . '://exists.txt'));
    }

    public function testFileExistsForDirectoryPath(): void
    {
        // Paths without extension are treated as directories
        self::assertTrue(file_exists(self::PROTOCOL . '://uploads/2024/01'));
    }

    public function testFilesize(): void
    {
        $this->adapter->write('sized.txt', 'twelve chars');

        // Clear stat cache for fresh metadata
        clearstatcache();
        StorageStreamWrapper::getStatCache(self::PROTOCOL)?->clear();

        self::assertSame(12, filesize(self::PROTOCOL . '://sized.txt'));
    }

    // ──────────────────────────────────────────────
    // unlink / rename
    // ──────────────────────────────────────────────

    public function testUnlink(): void
    {
        $this->adapter->write('delete-me.txt', 'gone');

        $result = unlink(self::PROTOCOL . '://delete-me.txt');

        self::assertTrue($result);
        self::assertFalse($this->adapter->exists('delete-me.txt'));
    }

    public function testRename(): void
    {
        $this->adapter->write('old-name.txt', 'data');

        $result = rename(
            self::PROTOCOL . '://old-name.txt',
            self::PROTOCOL . '://new-name.txt',
        );

        self::assertTrue($result);
        self::assertFalse($this->adapter->exists('old-name.txt'));
        self::assertSame('data', $this->adapter->read('new-name.txt'));
    }

    // ──────────────────────────────────────────────
    // mkdir / rmdir (no-ops for object storage)
    // ──────────────────────────────────────────────

    public function testMkdirAlwaysSucceeds(): void
    {
        self::assertTrue(mkdir(self::PROTOCOL . '://new-dir', 0777, true));
    }

    public function testRmdirAlwaysSucceeds(): void
    {
        self::assertTrue(rmdir(self::PROTOCOL . '://some-dir'));
    }

    // ──────────────────────────────────────────────
    // Directory listing (opendir/readdir/closedir)
    // ──────────────────────────────────────────────

    public function testDirOperations(): void
    {
        $this->adapter->write('uploads/a.txt', 'a');
        $this->adapter->write('uploads/b.txt', 'b');
        $this->adapter->write('uploads/sub/c.txt', 'c');

        $dh = opendir(self::PROTOCOL . '://uploads');
        self::assertIsResource($dh);

        $entries = [];
        while (($entry = readdir($dh)) !== false) {
            $entries[] = $entry;
        }

        closedir($dh);

        sort($entries);
        self::assertSame(['a.txt', 'b.txt', 'sub'], $entries);
    }

    public function testRewindDir(): void
    {
        $this->adapter->write('dir/file.txt', 'data');

        $dh = opendir(self::PROTOCOL . '://dir');
        $first = readdir($dh);
        self::assertSame('file.txt', $first);

        rewinddir($dh);
        $again = readdir($dh);
        self::assertSame('file.txt', $again);

        closedir($dh);
    }

    // ──────────────────────────────────────────────
    // StatCache integration
    // ──────────────────────────────────────────────

    public function testStatCacheIsUsedForUrlStat(): void
    {
        $this->adapter->write('cached.txt', 'data');

        // First call populates cache
        clearstatcache();
        file_exists(self::PROTOCOL . '://cached.txt');

        $statCache = StorageStreamWrapper::getStatCache(self::PROTOCOL);
        self::assertNotNull($statCache);
        self::assertNotNull($statCache->get(self::PROTOCOL . '://cached.txt'));
    }

    public function testStatCacheInvalidatedOnUnlink(): void
    {
        $this->adapter->write('to-delete.txt', 'data');

        // Populate cache
        clearstatcache();
        file_exists(self::PROTOCOL . '://to-delete.txt');

        // Unlink should invalidate
        unlink(self::PROTOCOL . '://to-delete.txt');

        $statCache = StorageStreamWrapper::getStatCache(self::PROTOCOL);
        self::assertNull($statCache->get(self::PROTOCOL . '://to-delete.txt'));
    }

    public function testStatCacheInvalidatedOnRename(): void
    {
        $this->adapter->write('src.txt', 'data');

        // Populate cache
        clearstatcache();
        file_exists(self::PROTOCOL . '://src.txt');

        rename(self::PROTOCOL . '://src.txt', self::PROTOCOL . '://dst.txt');

        $statCache = StorageStreamWrapper::getStatCache(self::PROTOCOL);
        self::assertNull($statCache->get(self::PROTOCOL . '://src.txt'));
        self::assertNull($statCache->get(self::PROTOCOL . '://dst.txt'));
    }

    // ──────────────────────────────────────────────
    // Binary mode flag stripping
    // ──────────────────────────────────────────────

    public function testBinaryModeFlag(): void
    {
        $fp = fopen(self::PROTOCOL . '://binary.bin', 'wb');
        self::assertIsResource($fp);

        fwrite($fp, "\x00\x01\x02");
        fclose($fp);

        self::assertSame("\x00\x01\x02", $this->adapter->read('binary.bin'));
    }

    public function testTextModeFlag(): void
    {
        $fp = fopen(self::PROTOCOL . '://text.txt', 'wt');
        self::assertIsResource($fp);

        fwrite($fp, 'text');
        fclose($fp);

        self::assertSame('text', $this->adapter->read('text.txt'));
    }

    // ──────────────────────────────────────────────
    // stream_stat / stream_flush / stream_truncate edge cases
    // ──────────────────────────────────────────────

    public function testStreamStatReturnsStatArray(): void
    {
        $this->adapter->write('stat.txt', 'hello');

        $fp = fopen(self::PROTOCOL . '://stat.txt', 'r');
        self::assertIsResource($fp);

        $stat = fstat($fp);
        self::assertIsArray($stat);
        self::assertArrayHasKey('size', $stat);
        // Read-only mode → 0100444
        self::assertSame(0100444, $stat['mode']);

        fclose($fp);
    }

    public function testStreamStatWritableMode(): void
    {
        $fp = fopen(self::PROTOCOL . '://writable.txt', 'w+');
        self::assertIsResource($fp);

        fwrite($fp, 'data');
        $stat = fstat($fp);
        self::assertIsArray($stat);
        // Writable mode → 0100666
        self::assertSame(0100666, $stat['mode']);

        fclose($fp);
    }

    public function testStreamFlushWritesData(): void
    {
        $fp = fopen(self::PROTOCOL . '://flush.txt', 'w');
        self::assertIsResource($fp);

        fwrite($fp, 'flushed');
        fflush($fp);

        // Data should be flushed to storage
        self::assertSame('flushed', $this->adapter->read('flush.txt'));

        fclose($fp);
    }

    public function testStreamFlushReturnsTrueWhenNotDirty(): void
    {
        $this->adapter->write('existing.txt', 'data');

        $fp = fopen(self::PROTOCOL . '://existing.txt', 'r+');
        self::assertIsResource($fp);

        // Read without writing (not dirty)
        fread($fp, 1024);
        $result = fflush($fp);
        self::assertTrue($result);

        fclose($fp);
    }

    public function testStreamTruncateOnReadOnlyReturnsFalse(): void
    {
        $this->adapter->write('trunc-ro.txt', 'data');

        $fp = fopen(self::PROTOCOL . '://trunc-ro.txt', 'r');
        self::assertIsResource($fp);

        // Cannot truncate read-only stream
        $result = ftruncate($fp, 0);
        self::assertFalse($result);

        fclose($fp);
    }

    public function testStreamLockAlwaysReturnsTrue(): void
    {
        $fp = fopen(self::PROTOCOL . '://lock.txt', 'w');
        self::assertIsResource($fp);

        fwrite($fp, 'data');
        // flock calls stream_lock
        self::assertTrue(flock($fp, \LOCK_EX));
        self::assertTrue(flock($fp, \LOCK_UN));

        fclose($fp);
    }

    // ──────────────────────────────────────────────
    // url_stat edge cases
    // ──────────────────────────────────────────────

    public function testUrlStatReturnsFileStatWithTimestamps(): void
    {
        $this->adapter->write('timed.txt', str_repeat('x', 100));

        clearstatcache();
        StorageStreamWrapper::getStatCache(self::PROTOCOL)?->clear();

        $stat = stat(self::PROTOCOL . '://timed.txt');
        self::assertIsArray($stat);
        self::assertSame(100, $stat['size']);
        // Regular file mode
        self::assertSame(0100666, $stat['mode']);
    }

    public function testUrlStatReturnsDirectoryStatForPathWithoutExtension(): void
    {
        clearstatcache();

        $stat = stat(self::PROTOCOL . '://uploads/2024/01');
        self::assertIsArray($stat);
        // Directory mode
        self::assertSame(0040777, $stat['mode']);
    }

    public function testUrlStatReturnsFalseForNonExistentFile(): void
    {
        clearstatcache();
        StorageStreamWrapper::getStatCache(self::PROTOCOL)?->clear();

        $result = @stat(self::PROTOCOL . '://does-not-exist.txt');
        self::assertFalse($result);
    }

    // ──────────────────────────────────────────────
    // getAdapter / getStatCache for unregistered protocol
    // ──────────────────────────────────────────────

    public function testGetAdapterReturnsNullForUnregisteredProtocol(): void
    {
        self::assertNull(StorageStreamWrapper::getAdapter('unregistered'));
    }

    public function testGetStatCacheReturnsNullForUnregisteredProtocol(): void
    {
        self::assertNull(StorageStreamWrapper::getStatCache('unregistered'));
    }

    public function testUnregisterIdempotent(): void
    {
        StorageStreamWrapper::unregister('unregistered_protocol');
        self::assertNull(StorageStreamWrapper::getAdapter('unregistered_protocol'));
    }

    // ──────────────────────────────────────────────
    // Mode c without existing file
    // ──────────────────────────────────────────────

    public function testModeCExistingFile(): void
    {
        $this->adapter->write('cexist.txt', 'old content');

        $fp = fopen(self::PROTOCOL . '://cexist.txt', 'c');
        self::assertIsResource($fp);

        fwrite($fp, 'overwritten');
        fclose($fp);

        self::assertSame('overwritten', $this->adapter->read('cexist.txt'));
    }

    // ──────────────────────────────────────────────
    // stream_read on write-only, stream_write on read-only
    // ──────────────────────────────────────────────

    public function testStreamReadReturnsFalseOnWriteOnlyMode(): void
    {
        $fp = fopen(self::PROTOCOL . '://wo.txt', 'w');
        self::assertIsResource($fp);

        fwrite($fp, 'data');
        rewind($fp);

        $data = fread($fp, 1024);
        // In write-only mode, read returns false/empty
        self::assertEmpty($data);

        fclose($fp);
    }

    // ──────────────────────────────────────────────
    // Append to non-existent file with a+
    // ──────────────────────────────────────────────

    public function testModeAPlusCreatesNewFile(): void
    {
        $fp = fopen(self::PROTOCOL . '://aplus-new.txt', 'a+');
        self::assertIsResource($fp);

        fwrite($fp, 'new content');
        rewind($fp);

        self::assertSame('new content', stream_get_contents($fp));
        fclose($fp);

        self::assertSame('new content', $this->adapter->read('aplus-new.txt'));
    }

    // ──────────────────────────────────────────────
    // x+ mode with existing file
    // ──────────────────────────────────────────────

    public function testModeXPlusFailsIfFileExists(): void
    {
        $this->adapter->write('xplus-exist.txt', 'content');

        $fp = @fopen(self::PROTOCOL . '://xplus-exist.txt', 'x+');
        self::assertFalse($fp);
    }

    // ──────────────────────────────────────────────
    // Directory listing: opendir on root
    // ──────────────────────────────────────────────

    public function testDirOpendirAtRoot(): void
    {
        $this->adapter->write('root-a.txt', 'a');
        $this->adapter->write('root-b.txt', 'b');

        $dh = opendir(self::PROTOCOL . '://');
        self::assertIsResource($dh);

        $entries = [];
        while (($entry = readdir($dh)) !== false) {
            $entries[] = $entry;
        }

        closedir($dh);

        self::assertContains('root-a.txt', $entries);
        self::assertContains('root-b.txt', $entries);
    }

    // ──────────────────────────────────────────────
    // stream_close flush on dirty writable
    // ──────────────────────────────────────────────

    public function testStreamCloseFlushesOnDirtyWritable(): void
    {
        $fp = fopen(self::PROTOCOL . '://close-flush.txt', 'w');
        fwrite($fp, 'auto-flushed');
        // Close should automatically flush dirty data
        fclose($fp);

        self::assertSame('auto-flushed', $this->adapter->read('close-flush.txt'));
    }

    // ──────────────────────────────────────────────
    // StatCache pre-caching during dir listing
    // ──────────────────────────────────────────────

    public function testDirListingPreCachesStat(): void
    {
        $this->adapter->write('listing/file1.txt', 'content1');
        $this->adapter->write('listing/file2.txt', 'content2');

        StorageStreamWrapper::getStatCache(self::PROTOCOL)?->clear();

        $dh = opendir(self::PROTOCOL . '://listing');
        while (readdir($dh) !== false) {
            // consume entries
        }
        closedir($dh);

        // Stats should be pre-cached during directory listing
        $statCache = StorageStreamWrapper::getStatCache(self::PROTOCOL);
        self::assertNotNull($statCache);

        // File stats should have been cached
        $cached1 = $statCache->get(self::PROTOCOL . '://listing/file1.txt');
        self::assertNotNull($cached1, 'Stat for file1.txt should be pre-cached during directory listing');
    }
}
