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
        // url_stat tries metadata() first, then directoryExists()
        $this->adapter->createDirectory('uploads/2024/01');
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
        self::assertFalse($this->adapter->fileExists('delete-me.txt'));
    }

    public function testRename(): void
    {
        $this->adapter->write('old-name.txt', 'data');

        $result = rename(
            self::PROTOCOL . '://old-name.txt',
            self::PROTOCOL . '://new-name.txt',
        );

        self::assertTrue($result);
        self::assertFalse($this->adapter->fileExists('old-name.txt'));
        self::assertSame('data', $this->adapter->read('new-name.txt'));
    }

    // ──────────────────────────────────────────────
    // mkdir / rmdir (delegates to adapter)
    // ──────────────────────────────────────────────

    public function testMkdirCreatesDirectory(): void
    {
        self::assertTrue(mkdir(self::PROTOCOL . '://new-dir', 0777, true));
        self::assertTrue($this->adapter->directoryExists('new-dir'));
    }

    public function testRmdirDeletesDirectory(): void
    {
        $this->adapter->createDirectory('some-dir');
        self::assertTrue($this->adapter->directoryExists('some-dir'));

        self::assertTrue(rmdir(self::PROTOCOL . '://some-dir'));
        self::assertFalse($this->adapter->directoryExists('some-dir'));
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
        // url_stat now tries metadata() first, then directoryExists()
        $this->adapter->createDirectory('uploads/2024/01');

        clearstatcache();
        StorageStreamWrapper::getStatCache(self::PROTOCOL)?->clear();

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

    // ──────────────────────────────────────────────
    // stream_truncate additional edge cases
    // ──────────────────────────────────────────────

    public function testTruncateMarksDirtyAndFlushesOnClose(): void
    {
        $fp = fopen(self::PROTOCOL . '://trunc-dirty.txt', 'w+');
        fwrite($fp, 'Hello World');

        // Truncate to 5 bytes — marks dirty
        ftruncate($fp, 5);

        // Close triggers flush of truncated content
        fclose($fp);

        self::assertSame('Hello', $this->adapter->read('trunc-dirty.txt'));
    }

    public function testTruncateToZero(): void
    {
        $fp = fopen(self::PROTOCOL . '://trunc-zero.txt', 'w+');
        fwrite($fp, 'Some data');

        ftruncate($fp, 0);
        rewind($fp);
        $content = stream_get_contents($fp);
        self::assertSame('', $content);

        fclose($fp);
    }

    // ──────────────────────────────────────────────
    // stream_seek beyond end of file
    // ──────────────────────────────────────────────

    public function testSeekBeyondEnd(): void
    {
        $this->adapter->write('seek-beyond.txt', 'ABC');

        $fp = fopen(self::PROTOCOL . '://seek-beyond.txt', 'r+');
        self::assertIsResource($fp);

        // Seek beyond end of file — behavior varies by PHP version
        $result = fseek($fp, 100);

        if ($result === 0) {
            // PHP 8.3+: seek succeeds, position moves past EOF
            self::assertSame(100, ftell($fp));
            $data = fread($fp, 10);
            self::assertSame('', $data);
        } else {
            // PHP 8.2: seek fails, position unchanged
            self::assertSame(-1, $result);
        }

        fclose($fp);
    }

    public function testSeekWithWhenceEnd(): void
    {
        $this->adapter->write('seek-end.txt', 'ABCDEF');

        $fp = fopen(self::PROTOCOL . '://seek-end.txt', 'r');
        self::assertIsResource($fp);

        // Seek from end
        fseek($fp, -3, \SEEK_END);
        self::assertSame('DEF', fread($fp, 3));

        fclose($fp);
    }

    public function testSeekWithWhenceCur(): void
    {
        $this->adapter->write('seek-cur.txt', 'ABCDEF');

        $fp = fopen(self::PROTOCOL . '://seek-cur.txt', 'r');
        self::assertIsResource($fp);

        fread($fp, 2); // position at 2
        fseek($fp, 1, \SEEK_CUR); // skip 1 more
        self::assertSame(3, ftell($fp));
        self::assertSame('DEF', fread($fp, 3));

        fclose($fp);
    }

    // ──────────────────────────────────────────────
    // stream_set_option always returns false
    // ──────────────────────────────────────────────

    public function testStreamSetOptionReturnsFalse(): void
    {
        $fp = fopen(self::PROTOCOL . '://setopt.txt', 'w');
        self::assertIsResource($fp);

        fwrite($fp, 'data');

        // stream_set_option is called internally by stream_set_timeout etc.
        // We verify via the class method directly that it returns false
        $wrapper = new StorageStreamWrapper();
        self::assertFalse($wrapper->stream_set_option(0, 0, 0));

        fclose($fp);
    }

    // ──────────────────────────────────────────────
    // url_stat with Throwable from adapter (non ObjectNotFoundException)
    // ──────────────────────────────────────────────

    public function testUrlStatReturnsFalseOnGenericException(): void
    {
        // Create a mock adapter that throws a generic exception on metadata
        $errorAdapter = new class implements \WpPack\Component\Storage\Adapter\StorageAdapterInterface {
            public function getName(): string
            {
                return 'error';
            }
            public function write(string $path, string $contents, array $metadata = []): void {}
            public function writeStream(string $path, mixed $resource, array $metadata = []): void {}
            public function read(string $path): string
            {
                return '';
            }
            public function readStream(string $path): mixed
            {
                return fopen('php://memory', 'r');
            }
            public function delete(string $path): void {}
            public function deleteMultiple(array $paths): void {}
            public function fileExists(string $path): bool
            {
                return true;
            }
            public function createDirectory(string $path): void {}
            public function deleteDirectory(string $path): void {}
            public function directoryExists(string $path): bool
            {
                return false;
            }
            public function copy(string $source, string $destination): void {}
            public function move(string $source, string $destination): void {}
            public function metadata(string $path): \WpPack\Component\Storage\ObjectMetadata
            {
                throw new \RuntimeException('Connection error');
            }
            public function publicUrl(string $path): string
            {
                return '';
            }
            public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
            {
                return '';
            }
            public function temporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
            {
                return '';
            }
            public function listContents(string $path = '', bool $deep = false): iterable
            {
                return [];
            }
            public function setVisibility(string $path, \WpPack\Component\Storage\Visibility $visibility): void {}
        };

        StorageStreamWrapper::register('errtest', $errorAdapter);

        try {
            clearstatcache();
            StorageStreamWrapper::getStatCache('errtest')?->clear();

            // url_stat should return false on generic Throwable
            $result = @stat('errtest://some-file.txt');
            self::assertFalse($result);
        } finally {
            StorageStreamWrapper::unregister('errtest');
        }
    }

    // ──────────────────────────────────────────────
    // unlink returns false when adapter throws
    // ──────────────────────────────────────────────

    public function testUnlinkReturnsFalseOnException(): void
    {
        $errorAdapter = new class implements \WpPack\Component\Storage\Adapter\StorageAdapterInterface {
            public function getName(): string
            {
                return 'error';
            }
            public function write(string $path, string $contents, array $metadata = []): void {}
            public function writeStream(string $path, mixed $resource, array $metadata = []): void {}
            public function read(string $path): string
            {
                return '';
            }
            public function readStream(string $path): mixed
            {
                return fopen('php://memory', 'r');
            }
            public function delete(string $path): void
            {
                throw new \RuntimeException('Delete failed');
            }
            public function deleteMultiple(array $paths): void {}
            public function fileExists(string $path): bool
            {
                return true;
            }
            public function createDirectory(string $path): void {}
            public function deleteDirectory(string $path): void {}
            public function directoryExists(string $path): bool
            {
                return false;
            }
            public function copy(string $source, string $destination): void {}
            public function move(string $source, string $destination): void {}
            public function metadata(string $path): \WpPack\Component\Storage\ObjectMetadata
            {
                return new \WpPack\Component\Storage\ObjectMetadata(path: $path, size: 0);
            }
            public function publicUrl(string $path): string
            {
                return '';
            }
            public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
            {
                return '';
            }
            public function temporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
            {
                return '';
            }
            public function listContents(string $path = '', bool $deep = false): iterable
            {
                return [];
            }
            public function setVisibility(string $path, \WpPack\Component\Storage\Visibility $visibility): void {}
        };

        StorageStreamWrapper::register('unlinktest', $errorAdapter);

        try {
            $result = @unlink('unlinktest://somefile.txt');
            self::assertFalse($result);
        } finally {
            StorageStreamWrapper::unregister('unlinktest');
        }
    }

    // ──────────────────────────────────────────────
    // rename returns false when adapter throws
    // ──────────────────────────────────────────────

    public function testRenameReturnsFalseOnException(): void
    {
        $errorAdapter = new class implements \WpPack\Component\Storage\Adapter\StorageAdapterInterface {
            public function getName(): string
            {
                return 'error';
            }
            public function write(string $path, string $contents, array $metadata = []): void {}
            public function writeStream(string $path, mixed $resource, array $metadata = []): void {}
            public function read(string $path): string
            {
                return '';
            }
            public function readStream(string $path): mixed
            {
                return fopen('php://memory', 'r');
            }
            public function delete(string $path): void {}
            public function deleteMultiple(array $paths): void {}
            public function fileExists(string $path): bool
            {
                return true;
            }
            public function createDirectory(string $path): void {}
            public function deleteDirectory(string $path): void {}
            public function directoryExists(string $path): bool
            {
                return false;
            }
            public function copy(string $source, string $destination): void {}
            public function move(string $source, string $destination): void
            {
                throw new \RuntimeException('Move failed');
            }
            public function metadata(string $path): \WpPack\Component\Storage\ObjectMetadata
            {
                return new \WpPack\Component\Storage\ObjectMetadata(path: $path, size: 0);
            }
            public function publicUrl(string $path): string
            {
                return '';
            }
            public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
            {
                return '';
            }
            public function temporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
            {
                return '';
            }
            public function listContents(string $path = '', bool $deep = false): iterable
            {
                return [];
            }
            public function setVisibility(string $path, \WpPack\Component\Storage\Visibility $visibility): void {}
        };

        StorageStreamWrapper::register('renametest', $errorAdapter);

        try {
            $result = @rename('renametest://from.txt', 'renametest://to.txt');
            self::assertFalse($result);
        } finally {
            StorageStreamWrapper::unregister('renametest');
        }
    }

    // ──────────────────────────────────────────────
    // stream_open with unregistered protocol adapter
    // ──────────────────────────────────────────────

    public function testOpenFailsWhenReadOnlyFileDoesNotExist(): void
    {
        $fp = @fopen(self::PROTOCOL . '://nonexistent-readonly.txt', 'r');
        self::assertFalse($fp);
    }

    // ──────────────────────────────────────────────
    // dir_opendir on adapter that throws
    // ──────────────────────────────────────────────

    public function testDirOpendirReturnsFalseOnException(): void
    {
        $errorAdapter = new class implements \WpPack\Component\Storage\Adapter\StorageAdapterInterface {
            public function getName(): string
            {
                return 'error';
            }
            public function write(string $path, string $contents, array $metadata = []): void {}
            public function writeStream(string $path, mixed $resource, array $metadata = []): void {}
            public function read(string $path): string
            {
                return '';
            }
            public function readStream(string $path): mixed
            {
                return fopen('php://memory', 'r');
            }
            public function delete(string $path): void {}
            public function deleteMultiple(array $paths): void {}
            public function fileExists(string $path): bool
            {
                return false;
            }
            public function createDirectory(string $path): void {}
            public function deleteDirectory(string $path): void {}
            public function directoryExists(string $path): bool
            {
                return false;
            }
            public function copy(string $source, string $destination): void {}
            public function move(string $source, string $destination): void {}
            public function metadata(string $path): \WpPack\Component\Storage\ObjectMetadata
            {
                return new \WpPack\Component\Storage\ObjectMetadata(path: $path, size: 0);
            }
            public function publicUrl(string $path): string
            {
                return '';
            }
            public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
            {
                return '';
            }
            public function temporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
            {
                return '';
            }
            public function listContents(string $path = '', bool $deep = false): iterable
            {
                throw new \RuntimeException('List failed');
            }
            public function setVisibility(string $path, \WpPack\Component\Storage\Visibility $visibility): void {}
        };

        StorageStreamWrapper::register('dirtest', $errorAdapter);

        try {
            $result = @opendir('dirtest://some-dir');
            self::assertFalse($result);
        } finally {
            StorageStreamWrapper::unregister('dirtest');
        }
    }

    // ──────────────────────────────────────────────
    // StatCache invalidated on write (via flush)
    // ──────────────────────────────────────────────

    public function testStatCacheInvalidatedOnWrite(): void
    {
        $this->adapter->write('write-cache.txt', 'old');

        // Populate cache via stat
        clearstatcache();
        file_exists(self::PROTOCOL . '://write-cache.txt');

        $statCache = StorageStreamWrapper::getStatCache(self::PROTOCOL);
        self::assertNotNull($statCache->get(self::PROTOCOL . '://write-cache.txt'));

        // Write new content, which should invalidate the cache for that key
        file_put_contents(self::PROTOCOL . '://write-cache.txt', 'new content');

        // Cache should have been invalidated via flush()
        self::assertNull($statCache->get(self::PROTOCOL . '://write-cache.txt'));
    }

    // ──────────────────────────────────────────────
    // stream_eof returns true when body is null
    // ──────────────────────────────────────────────

    public function testStreamEofReturnsTrueWhenBodyNull(): void
    {
        $wrapper = new StorageStreamWrapper();

        // body is null by default
        self::assertTrue($wrapper->stream_eof());
    }

    // ──────────────────────────────────────────────
    // stream_tell returns false when body is null
    // ──────────────────────────────────────────────

    public function testStreamTellReturnsFalseWhenBodyNull(): void
    {
        $wrapper = new StorageStreamWrapper();

        self::assertFalse($wrapper->stream_tell());
    }

    // ──────────────────────────────────────────────
    // stream_seek returns false when body is null
    // ──────────────────────────────────────────────

    public function testStreamSeekReturnsFalseWhenBodyNull(): void
    {
        $wrapper = new StorageStreamWrapper();

        self::assertFalse($wrapper->stream_seek(0));
    }

    // ──────────────────────────────────────────────
    // stream_stat returns false when body is null
    // ──────────────────────────────────────────────

    public function testStreamStatReturnsFalseWhenBodyNull(): void
    {
        $wrapper = new StorageStreamWrapper();

        self::assertFalse($wrapper->stream_stat());
    }

    // ──────────────────────────────────────────────
    // stream_flush returns false when body is null
    // ──────────────────────────────────────────────

    public function testStreamFlushReturnsFalseWhenBodyNull(): void
    {
        $wrapper = new StorageStreamWrapper();

        self::assertFalse($wrapper->stream_flush());
    }

    // ──────────────────────────────────────────────
    // stream_truncate returns false when body is null
    // ──────────────────────────────────────────────

    public function testStreamTruncateReturnsFalseWhenBodyNull(): void
    {
        $wrapper = new StorageStreamWrapper();

        self::assertFalse($wrapper->stream_truncate(0));
    }

    // ──────────────────────────────────────────────
    // stream_read returns false when body is null
    // ──────────────────────────────────────────────

    public function testStreamReadReturnsFalseWhenBodyNull(): void
    {
        $wrapper = new StorageStreamWrapper();

        self::assertFalse($wrapper->stream_read(1024));
    }

    // ──────────────────────────────────────────────
    // stream_write returns 0 when body is null
    // ──────────────────────────────────────────────

    public function testStreamWriteReturnsZeroWhenBodyNull(): void
    {
        $wrapper = new StorageStreamWrapper();

        self::assertSame(0, $wrapper->stream_write('data'));
    }

    // ──────────────────────────────────────────────
    // stream_close does nothing when body is null
    // ──────────────────────────────────────────────

    public function testStreamCloseDoesNothingWhenBodyNull(): void
    {
        $wrapper = new StorageStreamWrapper();

        // Should not throw
        $wrapper->stream_close();
        self::assertTrue(true);
    }

    // ──────────────────────────────────────────────
    // append mode with adapter that throws generic error
    // ──────────────────────────────────────────────

    public function testAppendModeReturnsFalseOnGenericError(): void
    {
        $errorAdapter = new class implements \WpPack\Component\Storage\Adapter\StorageAdapterInterface {
            public function getName(): string
            {
                return 'error';
            }
            public function write(string $path, string $contents, array $metadata = []): void {}
            public function writeStream(string $path, mixed $resource, array $metadata = []): void {}
            public function read(string $path): string
            {
                return '';
            }
            public function readStream(string $path): mixed
            {
                throw new \RuntimeException('Connection error');
            }
            public function delete(string $path): void {}
            public function deleteMultiple(array $paths): void {}
            public function fileExists(string $path): bool
            {
                return true;
            }
            public function createDirectory(string $path): void {}
            public function deleteDirectory(string $path): void {}
            public function directoryExists(string $path): bool
            {
                return false;
            }
            public function copy(string $source, string $destination): void {}
            public function move(string $source, string $destination): void {}
            public function metadata(string $path): \WpPack\Component\Storage\ObjectMetadata
            {
                return new \WpPack\Component\Storage\ObjectMetadata(path: $path, size: 0);
            }
            public function publicUrl(string $path): string
            {
                return '';
            }
            public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
            {
                return '';
            }
            public function temporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
            {
                return '';
            }
            public function listContents(string $path = '', bool $deep = false): iterable
            {
                return [];
            }
            public function setVisibility(string $path, \WpPack\Component\Storage\Visibility $visibility): void {}
        };

        StorageStreamWrapper::register('appendtest', $errorAdapter);

        try {
            $fp = @fopen('appendtest://file.txt', 'a');
            self::assertFalse($fp);
        } finally {
            StorageStreamWrapper::unregister('appendtest');
        }
    }

    // ──────────────────────────────────────────────
    // c+ mode with adapter that throws generic error
    // ──────────────────────────────────────────────

    public function testModeCPlusReturnsFalseOnGenericError(): void
    {
        $errorAdapter = new class implements \WpPack\Component\Storage\Adapter\StorageAdapterInterface {
            public function getName(): string
            {
                return 'error';
            }
            public function write(string $path, string $contents, array $metadata = []): void {}
            public function writeStream(string $path, mixed $resource, array $metadata = []): void {}
            public function read(string $path): string
            {
                return '';
            }
            public function readStream(string $path): mixed
            {
                throw new \RuntimeException('Connection error');
            }
            public function delete(string $path): void {}
            public function deleteMultiple(array $paths): void {}
            public function fileExists(string $path): bool
            {
                return true;
            }
            public function createDirectory(string $path): void {}
            public function deleteDirectory(string $path): void {}
            public function directoryExists(string $path): bool
            {
                return false;
            }
            public function copy(string $source, string $destination): void {}
            public function move(string $source, string $destination): void {}
            public function metadata(string $path): \WpPack\Component\Storage\ObjectMetadata
            {
                return new \WpPack\Component\Storage\ObjectMetadata(path: $path, size: 0);
            }
            public function publicUrl(string $path): string
            {
                return '';
            }
            public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
            {
                return '';
            }
            public function temporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
            {
                return '';
            }
            public function listContents(string $path = '', bool $deep = false): iterable
            {
                return [];
            }
            public function setVisibility(string $path, \WpPack\Component\Storage\Visibility $visibility): void {}
        };

        StorageStreamWrapper::register('cplustest', $errorAdapter);

        try {
            $fp = @fopen('cplustest://file.txt', 'c+');
            self::assertFalse($fp);
        } finally {
            StorageStreamWrapper::unregister('cplustest');
        }
    }

    // ──────────────────────────────────────────────
    // x mode with adapter.fileExists() that throws
    // ──────────────────────────────────────────────

    public function testModeXReturnsFalseWhenFileExistsThrows(): void
    {
        $errorAdapter = new class implements \WpPack\Component\Storage\Adapter\StorageAdapterInterface {
            public function getName(): string
            {
                return 'error';
            }
            public function write(string $path, string $contents, array $metadata = []): void {}
            public function writeStream(string $path, mixed $resource, array $metadata = []): void {}
            public function read(string $path): string
            {
                return '';
            }
            public function readStream(string $path): mixed
            {
                return fopen('php://memory', 'r');
            }
            public function delete(string $path): void {}
            public function deleteMultiple(array $paths): void {}
            public function fileExists(string $path): bool
            {
                throw new \RuntimeException('Connection error');
            }
            public function createDirectory(string $path): void {}
            public function deleteDirectory(string $path): void {}
            public function directoryExists(string $path): bool
            {
                return false;
            }
            public function copy(string $source, string $destination): void {}
            public function move(string $source, string $destination): void {}
            public function metadata(string $path): \WpPack\Component\Storage\ObjectMetadata
            {
                return new \WpPack\Component\Storage\ObjectMetadata(path: $path, size: 0);
            }
            public function publicUrl(string $path): string
            {
                return '';
            }
            public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
            {
                return '';
            }
            public function temporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
            {
                return '';
            }
            public function listContents(string $path = '', bool $deep = false): iterable
            {
                return [];
            }
            public function setVisibility(string $path, \WpPack\Component\Storage\Visibility $visibility): void {}
        };

        StorageStreamWrapper::register('xtest', $errorAdapter);

        try {
            $fp = @fopen('xtest://file.txt', 'x');
            self::assertFalse($fp);
        } finally {
            StorageStreamWrapper::unregister('xtest');
        }
    }

    // ──────────────────────────────────────────────
    // re-registration overwrites existing wrapper
    // ──────────────────────────────────────────────

    public function testRegisterOverwritesExistingWrapper(): void
    {
        $adapter2 = new InMemoryStorageAdapter();
        $adapter2->write('new.txt', 'new-data');

        // Re-register with new adapter
        StorageStreamWrapper::register(self::PROTOCOL, $adapter2);

        self::assertSame($adapter2, StorageStreamWrapper::getAdapter(self::PROTOCOL));
        self::assertSame('new-data', file_get_contents(self::PROTOCOL . '://new.txt'));
    }
}
