<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Tests\Storage\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\EventDispatcher\WordPressEvent;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Media\Storage\Subscriber\PrivacyExportSubscriber;
use WpPack\Component\Storage\Test\InMemoryStorageAdapter;

#[CoversClass(PrivacyExportSubscriber::class)]
final class PrivacyExportSubscriberTest extends TestCase
{
    private InMemoryStorageAdapter $adapter;
    private StorageConfiguration $config;
    private PrivacyExportSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryStorageAdapter();
        $this->config = new StorageConfiguration(
            protocol: 's3',
            bucket: 'my-bucket',
            prefix: 'uploads',
        );
        $this->subscriber = new PrivacyExportSubscriber($this->config, $this->adapter);
    }

    #[Test]
    public function onBeforeExportAddsExportsDirFilter(): void
    {
        $event = new WordPressEvent('wp_privacy_personal_data_export_file', []);
        $this->subscriber->onBeforeExport($event);

        // The filter should be registered — calling wp_privacy_exports_dir() should
        // return the local temp directory
        $dir = wp_privacy_exports_dir();
        self::assertStringStartsWith(sys_get_temp_dir(), $dir);
        self::assertStringContainsString('wppack_privacy_exports_', $dir);

        // Clean up
        $this->subscriber->onAfterExport($event);
    }

    #[Test]
    public function onAfterExportRemovesFilter(): void
    {
        $event = new WordPressEvent('wp_privacy_personal_data_export_file', []);

        // Register filter
        $this->subscriber->onBeforeExport($event);
        $dirDuringExport = wp_privacy_exports_dir();
        self::assertStringStartsWith(sys_get_temp_dir(), $dirDuringExport);

        // Remove filter
        $this->subscriber->onAfterExport($event);

        // After removal, wp_privacy_exports_dir should return normal WordPress path
        $dirAfterExport = wp_privacy_exports_dir();
        self::assertStringStartsNotWith(sys_get_temp_dir() . '/wppack_privacy_exports_', $dirAfterExport);

        // Clean up local temp dir if it exists
        if (is_dir($dirDuringExport)) {
            rmdir($dirDuringExport);
        }
    }

    #[Test]
    public function onExportFileCreatedUploadsFileToStorageAndCleansUp(): void
    {
        // Simulate the export flow
        $beforeEvent = new WordPressEvent('wp_privacy_personal_data_export_file', []);
        $this->subscriber->onBeforeExport($beforeEvent);

        // Get the redirected local temp dir
        $localTempDir = wp_privacy_exports_dir();
        self::assertStringStartsWith(sys_get_temp_dir(), $localTempDir);

        // Create a fake export file in the temp directory
        $exportFilename = 'wp-personal-data-file-test-123.zip';
        file_put_contents($localTempDir . $exportFilename, 'zip-contents');

        // Remove the filter (simulating onAfterExport)
        $this->subscriber->onAfterExport($beforeEvent);

        // Trigger onExportFileCreated
        $createdEvent = new WordPressEvent('wp_privacy_personal_data_export_file_created', [$exportFilename]);
        $this->subscriber->onExportFileCreated($createdEvent);

        // The file should have been uploaded to storage
        $found = false;
        foreach ($this->adapter->listContents('uploads', true) as $item) {
            if (str_contains($item->path, $exportFilename)) {
                $found = true;
                self::assertSame('zip-contents', $this->adapter->read($item->path));
                break;
            }
        }
        self::assertTrue($found, 'Export file should be uploaded to storage');

        // Local temp file and directory should be cleaned up
        self::assertFileDoesNotExist($localTempDir . $exportFilename);
        self::assertDirectoryDoesNotExist(rtrim($localTempDir, '/'));
    }

    #[Test]
    public function onExportFileCreatedDefensivelyRemovesFilter(): void
    {
        // Simulate: onBeforeExport adds the filter, but onAfterExport is never called
        $beforeEvent = new WordPressEvent('wp_privacy_personal_data_export_file', []);
        $this->subscriber->onBeforeExport($beforeEvent);

        $localTempDir = wp_privacy_exports_dir();
        self::assertStringStartsWith(sys_get_temp_dir(), $localTempDir);

        // Create a fake export file
        $exportFilename = 'wp-personal-data-file-defensive-456.zip';
        file_put_contents($localTempDir . $exportFilename, 'data');

        // Intentionally skip onAfterExport — simulate a failure in WordPress core

        // onExportFileCreated should defensively remove the filter
        $createdEvent = new WordPressEvent('wp_privacy_personal_data_export_file_created', [$exportFilename]);
        $this->subscriber->onExportFileCreated($createdEvent);

        // After defensive removal, wp_privacy_exports_dir should return normal path
        $dirAfter = wp_privacy_exports_dir();
        self::assertStringStartsNotWith(sys_get_temp_dir() . '/wppack_privacy_exports_', $dirAfter);
    }

    #[Test]
    public function onExportFileCreatedSkipsWhenNoTempDir(): void
    {
        // Without calling onBeforeExport, onExportFileCreated should be a no-op
        $event = new WordPressEvent('wp_privacy_personal_data_export_file_created', ['some-file.zip']);
        $this->subscriber->onExportFileCreated($event);

        // No files should be in storage
        $items = iterator_to_array($this->adapter->listContents('', true));
        self::assertCount(0, $items);
    }
}
