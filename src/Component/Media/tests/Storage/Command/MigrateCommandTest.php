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

namespace WPPack\Component\Media\Tests\Storage\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Console\Input\ArrayInput;
use WPPack\Component\Console\Output\BufferedOutput;
use WPPack\Component\Console\Output\OutputStyle;
use WPPack\Component\Media\Storage\Command\MigrateCommand;
use WPPack\Component\Media\Storage\StorageConfiguration;
use WPPack\Component\Storage\Adapter\StorageAdapterInterface;
use WPPack\Component\Storage\Test\InMemoryStorageAdapter;

#[CoversClass(MigrateCommand::class)]
final class MigrateCommandTest extends TestCase
{
    private InMemoryStorageAdapter $adapter;
    private StorageConfiguration $config;
    private MigrateCommand $command;
    private string $uploadsDir;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryStorageAdapter();
        $this->config = new StorageConfiguration(
            protocol: 's3',
            bucket: 'my-bucket',
            prefix: 'uploads',
        );
        $this->command = new MigrateCommand($this->adapter, $this->config);

        $uploadDir = wp_upload_dir();
        $this->uploadsDir = $uploadDir['basedir'];
    }

    protected function tearDown(): void
    {
        // Clean up any created attachments and files
        $attachments = get_posts([
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);

        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment->ID, true);
        }
    }

    #[Test]
    public function configureDefinesOptions(): void
    {
        $definition = $this->command->getDefinition();

        self::assertTrue($definition->hasOption('dry-run'));
        self::assertTrue($definition->hasOption('batch-size'));
    }

    #[Test]
    public function executeWithNoAttachmentsReturnsSuccess(): void
    {
        $input = new ArrayInput([], ['dry-run' => false, 'batch-size' => 100]);
        $buffer = new BufferedOutput();
        $output = new OutputStyle($buffer);

        $exitCode = $this->command->run($input, $output);

        self::assertSame(0, $exitCode);
        $outputText = $buffer->getBuffer();
        self::assertStringContainsString('s3://my-bucket/uploads', $outputText);
        self::assertStringContainsString('Migration completed successfully', $outputText);
    }

    #[Test]
    public function executeMigratesAttachmentFiles(): void
    {
        // Create a local file to migrate
        $relPath = '2024/01';
        $fullDir = $this->uploadsDir . '/' . $relPath;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }
        $localFile = $fullDir . '/test-image.jpg';
        file_put_contents($localFile, 'fake image content');

        $attachmentId = wp_insert_attachment([
            'post_title' => 'Test Image',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], $relPath . '/test-image.jpg');

        update_post_meta($attachmentId, '_wp_attached_file', $relPath . '/test-image.jpg');

        $input = new ArrayInput([], ['dry-run' => false, 'batch-size' => 100]);
        $buffer = new BufferedOutput();
        $output = new OutputStyle($buffer);

        $exitCode = $this->command->run($input, $output);

        self::assertSame(0, $exitCode);
        self::assertTrue($this->adapter->fileExists('uploads/' . $relPath . '/test-image.jpg'));
        self::assertStringContainsString('Migrated: 1', $buffer->getBuffer());

        // Cleanup local file
        @unlink($localFile);
    }

    #[Test]
    public function executeDryRunDoesNotCopyFiles(): void
    {
        $relPath = '2024/02';
        $fullDir = $this->uploadsDir . '/' . $relPath;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }
        $localFile = $fullDir . '/dry-run.jpg';
        file_put_contents($localFile, 'dry run content');

        $attachmentId = wp_insert_attachment([
            'post_title' => 'Dry Run Image',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], $relPath . '/dry-run.jpg');

        update_post_meta($attachmentId, '_wp_attached_file', $relPath . '/dry-run.jpg');

        $input = new ArrayInput([], ['dry-run' => true, 'batch-size' => 100]);
        $buffer = new BufferedOutput();
        $output = new OutputStyle($buffer);

        $exitCode = $this->command->run($input, $output);

        self::assertSame(0, $exitCode);
        self::assertFalse($this->adapter->fileExists('uploads/' . $relPath . '/dry-run.jpg'));
        $outputText = $buffer->getBuffer();
        self::assertStringContainsString('Dry run mode enabled', $outputText);
        self::assertStringContainsString('[DRY RUN]', $outputText);

        @unlink($localFile);
    }

    #[Test]
    public function executeSkipsAlreadyMigratedFiles(): void
    {
        $relPath = '2024/03';
        $fullDir = $this->uploadsDir . '/' . $relPath;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }
        $localFile = $fullDir . '/already-there.jpg';
        file_put_contents($localFile, 'local content');

        // Pre-populate the storage with the file
        $this->adapter->write('uploads/' . $relPath . '/already-there.jpg', 'existing content');

        $attachmentId = wp_insert_attachment([
            'post_title' => 'Already There',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], $relPath . '/already-there.jpg');

        update_post_meta($attachmentId, '_wp_attached_file', $relPath . '/already-there.jpg');

        $input = new ArrayInput([], ['dry-run' => false, 'batch-size' => 100]);
        $buffer = new BufferedOutput();
        $output = new OutputStyle($buffer);

        $exitCode = $this->command->run($input, $output);

        self::assertSame(0, $exitCode);
        // Original content should remain unchanged (not overwritten)
        self::assertSame('existing content', $this->adapter->read('uploads/' . $relPath . '/already-there.jpg'));
        self::assertStringContainsString('Skipped: 1', $buffer->getBuffer());

        @unlink($localFile);
    }

    #[Test]
    public function executeSkipsAttachmentsWithoutFile(): void
    {
        $attachmentId = wp_insert_attachment([
            'post_title' => 'No File',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ]);

        // No _wp_attached_file meta set

        $input = new ArrayInput([], ['dry-run' => false, 'batch-size' => 100]);
        $buffer = new BufferedOutput();
        $output = new OutputStyle($buffer);

        $exitCode = $this->command->run($input, $output);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Skipped: 1', $buffer->getBuffer());
    }

    #[Test]
    public function executeSkipsMissingLocalFiles(): void
    {
        $attachmentId = wp_insert_attachment([
            'post_title' => 'Missing File',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], '2024/04/missing-file.jpg');

        update_post_meta($attachmentId, '_wp_attached_file', '2024/04/missing-file.jpg');

        $input = new ArrayInput([], ['dry-run' => false, 'batch-size' => 100]);
        $buffer = new BufferedOutput();
        $output = new OutputStyle($buffer);

        $exitCode = $this->command->run($input, $output);

        self::assertSame(0, $exitCode);
        self::assertFalse($this->adapter->fileExists('uploads/2024/04/missing-file.jpg'));
    }

    #[Test]
    public function executeMigratesThumbnails(): void
    {
        $relPath = '2024/05';
        $fullDir = $this->uploadsDir . '/' . $relPath;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }
        file_put_contents($fullDir . '/photo.jpg', 'original image');
        file_put_contents($fullDir . '/photo-150x150.jpg', 'thumbnail');
        file_put_contents($fullDir . '/photo-300x200.jpg', 'medium');

        $attachmentId = wp_insert_attachment([
            'post_title' => 'Photo With Thumbs',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], $relPath . '/photo.jpg');

        update_post_meta($attachmentId, '_wp_attached_file', $relPath . '/photo.jpg');
        wp_update_attachment_metadata($attachmentId, [
            'file' => $relPath . '/photo.jpg',
            'sizes' => [
                'thumbnail' => ['file' => 'photo-150x150.jpg'],
                'medium' => ['file' => 'photo-300x200.jpg'],
            ],
        ]);

        $input = new ArrayInput([], ['dry-run' => false, 'batch-size' => 100]);
        $buffer = new BufferedOutput();
        $output = new OutputStyle($buffer);

        $exitCode = $this->command->run($input, $output);

        self::assertSame(0, $exitCode);
        self::assertTrue($this->adapter->fileExists('uploads/' . $relPath . '/photo.jpg'));
        self::assertTrue($this->adapter->fileExists('uploads/' . $relPath . '/photo-150x150.jpg'));
        self::assertTrue($this->adapter->fileExists('uploads/' . $relPath . '/photo-300x200.jpg'));
        self::assertStringContainsString('Migrated: 3', $buffer->getBuffer());

        @unlink($fullDir . '/photo.jpg');
        @unlink($fullDir . '/photo-150x150.jpg');
        @unlink($fullDir . '/photo-300x200.jpg');
    }

    #[Test]
    public function executeBatchProcessesMultipleAttachments(): void
    {
        $relPath = '2024/06';
        $fullDir = $this->uploadsDir . '/' . $relPath;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        // Create 3 attachments with batch-size 2 to test batching
        for ($i = 1; $i <= 3; $i++) {
            $filename = "batch-{$i}.jpg";
            file_put_contents($fullDir . '/' . $filename, "content {$i}");

            $attachmentId = wp_insert_attachment([
                'post_title' => "Batch {$i}",
                'post_mime_type' => 'image/jpeg',
                'post_status' => 'inherit',
            ], $relPath . '/' . $filename);

            update_post_meta($attachmentId, '_wp_attached_file', $relPath . '/' . $filename);
        }

        $input = new ArrayInput([], ['dry-run' => false, 'batch-size' => 2]);
        $buffer = new BufferedOutput();
        $output = new OutputStyle($buffer);

        $exitCode = $this->command->run($input, $output);

        self::assertSame(0, $exitCode);
        for ($i = 1; $i <= 3; $i++) {
            self::assertTrue($this->adapter->fileExists('uploads/' . $relPath . "/batch-{$i}.jpg"));
            @unlink($fullDir . "/batch-{$i}.jpg");
        }
    }

    #[Test]
    public function executeHandlesExceptionDuringWrite(): void
    {
        // Create a decorator that delegates all methods but throws on writeStream
        $inner = new InMemoryStorageAdapter();
        $failingAdapter = new class ($inner) implements StorageAdapterInterface {
            public function __construct(private readonly InMemoryStorageAdapter $inner) {}

            public function getName(): string
            {
                return $this->inner->getName();
            }

            public function write(string $path, string $contents, array $metadata = []): void
            {
                $this->inner->write($path, $contents, $metadata);
            }

            public function writeStream(string $path, mixed $resource, array $metadata = []): void
            {
                throw new \RuntimeException('Simulated storage failure');
            }

            public function read(string $path): string
            {
                return $this->inner->read($path);
            }

            public function readStream(string $path): mixed
            {
                return $this->inner->readStream($path);
            }

            public function delete(string $path): void
            {
                $this->inner->delete($path);
            }

            public function deleteMultiple(array $paths): void
            {
                $this->inner->deleteMultiple($paths);
            }

            public function fileExists(string $path): bool
            {
                return $this->inner->fileExists($path);
            }

            public function createDirectory(string $path): void
            {
                $this->inner->createDirectory($path);
            }

            public function deleteDirectory(string $path): void
            {
                $this->inner->deleteDirectory($path);
            }

            public function directoryExists(string $path): bool
            {
                return $this->inner->directoryExists($path);
            }

            public function copy(string $source, string $destination): void
            {
                $this->inner->copy($source, $destination);
            }

            public function move(string $source, string $destination): void
            {
                $this->inner->move($source, $destination);
            }

            public function metadata(string $path): \WPPack\Component\Storage\ObjectMetadata
            {
                return $this->inner->metadata($path);
            }

            public function publicUrl(string $path): string
            {
                return $this->inner->publicUrl($path);
            }

            public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
            {
                return $this->inner->temporaryUrl($path, $expiration);
            }

            public function temporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
            {
                return $this->inner->temporaryUploadUrl($path, $expiration, $options);
            }

            public function listContents(string $path = '', bool $deep = false): iterable
            {
                return $this->inner->listContents($path, $deep);
            }

            public function setVisibility(string $path, \WPPack\Component\Storage\Visibility $visibility): void
            {
                $this->inner->setVisibility($path, $visibility);
            }
        };

        $command = new MigrateCommand($failingAdapter, $this->config);

        $relPath = '2024/07';
        $fullDir = $this->uploadsDir . '/' . $relPath;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }
        file_put_contents($fullDir . '/fail.jpg', 'content');

        $attachmentId = wp_insert_attachment([
            'post_title' => 'Fail Image',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], $relPath . '/fail.jpg');

        update_post_meta($attachmentId, '_wp_attached_file', $relPath . '/fail.jpg');

        $input = new ArrayInput([], ['dry-run' => false, 'batch-size' => 100]);
        $buffer = new BufferedOutput();
        $output = new OutputStyle($buffer);

        $exitCode = $command->run($input, $output);

        self::assertSame(1, $exitCode);
        $outputText = $buffer->getBuffer();
        self::assertStringContainsString('Failed: 1', $outputText);
        self::assertStringContainsString('Some files failed to migrate', $outputText);

        @unlink($fullDir . '/fail.jpg');
    }

    #[Test]
    public function executeHandlesUnreadableFile(): void
    {
        $relPath = '2024/08';
        $fullDir = $this->uploadsDir . '/' . $relPath;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $localFile = $fullDir . '/unreadable.jpg';
        file_put_contents($localFile, 'some content');
        // Make the file unreadable
        chmod($localFile, 0000);

        $attachmentId = wp_insert_attachment([
            'post_title' => 'Unreadable',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], $relPath . '/unreadable.jpg');

        update_post_meta($attachmentId, '_wp_attached_file', $relPath . '/unreadable.jpg');

        $input = new ArrayInput([], ['dry-run' => false, 'batch-size' => 100]);
        $buffer = new BufferedOutput();
        $output = new OutputStyle($buffer);

        $exitCode = $this->command->run($input, $output);

        // Restore permissions before cleanup
        chmod($localFile, 0644);

        self::assertSame(1, $exitCode);
        $outputText = $buffer->getBuffer();
        self::assertStringContainsString('Failed to open', $outputText);

        @unlink($localFile);
    }

    #[Test]
    public function commandAttributeIsCorrect(): void
    {
        $attribute = MigrateCommand::getCommandAttribute();

        self::assertSame('media:migrate-storage', $attribute->name);
        self::assertSame('Migrate local uploads to object storage', $attribute->description);
    }
}
