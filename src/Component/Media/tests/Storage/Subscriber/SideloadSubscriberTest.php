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

namespace WpPack\Component\Media\Tests\Storage\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\EventDispatcher\WordPressEvent;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Media\Storage\Subscriber\SideloadSubscriber;
use WpPack\Component\Storage\Test\InMemoryStorageAdapter;

#[CoversClass(SideloadSubscriber::class)]
final class SideloadSubscriberTest extends TestCase
{
    private InMemoryStorageAdapter $adapter;
    private StorageConfiguration $config;
    private SideloadSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryStorageAdapter();
        $this->config = new StorageConfiguration(
            protocol: 's3',
            bucket: 'my-bucket',
            prefix: 'uploads',
        );
        $this->subscriber = new SideloadSubscriber($this->config, $this->adapter);
    }

    #[Test]
    public function sideloadedFileIsWrittenToStorageAndTmpNameIsRewritten(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'wppack_test_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'file-contents');

        $file = [
            'tmp_name' => $tmpFile,
            'name' => 'photo.jpg',
        ];

        $event = new WordPressEvent('wp_handle_sideload_prefilter', [$file]);
        $this->subscriber->filterSideloadPrefilter($event);

        /** @var array{tmp_name: string, name: string} $result */
        $result = $event->filterValue;

        // tmp_name should be rewritten to stream wrapper path
        self::assertStringStartsWith('s3://my-bucket/uploads/', $result['tmp_name']);
        self::assertStringContainsString('photo.jpg', $result['tmp_name']);

        // File should exist in storage
        $key = preg_replace('#^s3://my-bucket/#', '', $result['tmp_name']);
        self::assertNotNull($key);
        self::assertTrue($this->adapter->fileExists($key));
        self::assertSame('file-contents', $this->adapter->read($key));

        // Original temp file should be deleted
        self::assertFileDoesNotExist($tmpFile);
    }

    #[Test]
    public function fileOutsideSystemTempDirIsSkipped(): void
    {
        $file = [
            'tmp_name' => '/var/www/html/wp-content/uploads/some-file.jpg',
            'name' => 'some-file.jpg',
        ];

        $event = new WordPressEvent('wp_handle_sideload_prefilter', [$file]);
        $this->subscriber->filterSideloadPrefilter($event);

        // filterValue should remain unchanged
        self::assertSame($file, $event->filterValue);
    }

    #[Test]
    public function nonExistentFileReturnsOriginalFile(): void
    {
        $nonExistentPath = sys_get_temp_dir() . '/wppack_nonexistent_' . uniqid() . '.jpg';

        $file = [
            'tmp_name' => $nonExistentPath,
            'name' => 'missing.jpg',
        ];

        $event = new WordPressEvent('wp_handle_sideload_prefilter', [$file]);
        $this->subscriber->filterSideloadPrefilter($event);

        // filterValue should remain unchanged (realpath returns false for non-existent files,
        // and str_starts_with with the raw path may or may not match — but file_get_contents
        // will fail and the method returns early)
        self::assertSame($file, $event->filterValue);
    }
}
