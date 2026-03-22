<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Tests\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\EventDispatcher\WordPressEvent;
use WpPack\Component\Media\Storage\PrivateAttachmentChecker;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Storage\Test\InMemoryStorageAdapter;
use WpPack\Component\Storage\Visibility;
use WpPack\Plugin\S3StoragePlugin\Subscriber\PrivateAttachmentAclSubscriber;

#[CoversClass(PrivateAttachmentAclSubscriber::class)]
final class PrivateAttachmentAclSubscriberTest extends TestCase
{
    private InMemoryStorageAdapter $adapter;
    private StorageConfiguration $config;
    private PrivateAttachmentChecker $checker;
    private PrivateAttachmentAclSubscriber $subscriber;
    private int $attachmentId;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryStorageAdapter();
        $this->config = new StorageConfiguration(
            protocol: 's3',
            bucket: 'my-bucket',
            prefix: 'uploads',
        );
        $this->checker = new PrivateAttachmentChecker();
        $this->subscriber = new PrivateAttachmentAclSubscriber(
            $this->config,
            $this->adapter,
            $this->checker,
        );

        $this->attachmentId = wp_insert_attachment([
            'post_title' => 'Test Image',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ]);
    }

    protected function tearDown(): void
    {
        wp_delete_attachment($this->attachmentId, true);
    }

    #[Test]
    public function setVisibilityOnPrivateAttachmentMainFileAndThumbnails(): void
    {
        $this->checker->setPrivate($this->attachmentId, true);
        update_post_meta($this->attachmentId, '_wp_attached_file', '2024/01/photo.jpg');

        // Pre-create files in storage so setVisibility doesn't throw ObjectNotFoundException
        $this->adapter->write('uploads/2024/01/photo.jpg', 'main');
        $this->adapter->write('uploads/2024/01/photo-150x150.jpg', 'thumb1');
        $this->adapter->write('uploads/2024/01/photo-300x200.jpg', 'thumb2');

        $metadata = [
            'file' => '2024/01/photo.jpg',
            'sizes' => [
                'thumbnail' => ['file' => 'photo-150x150.jpg', 'width' => 150, 'height' => 150],
                'medium' => ['file' => 'photo-300x200.jpg', 'width' => 300, 'height' => 200],
            ],
        ];

        $event = new WordPressEvent('wp_generate_attachment_metadata', [$metadata, $this->attachmentId]);
        $this->subscriber->setVisibilityOnGenerate($event);

        // Main file should be private
        self::assertSame(Visibility::PRIVATE, $this->adapter->getVisibility('uploads/2024/01/photo.jpg'));

        // Thumbnails should be private
        self::assertSame(Visibility::PRIVATE, $this->adapter->getVisibility('uploads/2024/01/photo-150x150.jpg'));
        self::assertSame(Visibility::PRIVATE, $this->adapter->getVisibility('uploads/2024/01/photo-300x200.jpg'));
    }

    #[Test]
    public function nonPrivateAttachmentIsSkipped(): void
    {
        $this->checker->setPrivate($this->attachmentId, false);
        update_post_meta($this->attachmentId, '_wp_attached_file', '2024/01/photo.jpg');

        $this->adapter->write('uploads/2024/01/photo.jpg', 'main');

        $metadata = [
            'file' => '2024/01/photo.jpg',
            'sizes' => [],
        ];

        $event = new WordPressEvent('wp_generate_attachment_metadata', [$metadata, $this->attachmentId]);
        $this->subscriber->setVisibilityOnGenerate($event);

        // Visibility should not be set
        self::assertNull($this->adapter->getVisibility('uploads/2024/01/photo.jpg'));
    }

    #[Test]
    public function emptyAttachedFileIsSkipped(): void
    {
        $this->checker->setPrivate($this->attachmentId, true);
        update_post_meta($this->attachmentId, '_wp_attached_file', '');

        $metadata = ['sizes' => []];

        $event = new WordPressEvent('wp_generate_attachment_metadata', [$metadata, $this->attachmentId]);
        $this->subscriber->setVisibilityOnGenerate($event);

        // Should not throw or set any visibility — just return early
        self::assertTrue(true);
    }
}
