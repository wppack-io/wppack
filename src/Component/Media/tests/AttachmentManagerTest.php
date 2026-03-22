<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Media\AttachmentManager;

#[CoversClass(AttachmentManager::class)]
final class AttachmentManagerTest extends TestCase
{
    private AttachmentManager $manager;

    protected function setUp(): void
    {
        $this->manager = new AttachmentManager();
    }

    #[Test]
    public function insertCreatesAttachment(): void
    {
        $id = $this->manager->insert(
            ['post_title' => 'test-insert', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'],
            'test-insert-' . uniqid() . '.jpg',
        );

        self::assertIsInt($id);
        self::assertGreaterThan(0, $id);

        wp_delete_attachment($id, true);
    }

    #[Test]
    public function deleteRemovesAttachment(): void
    {
        $id = wp_insert_attachment(
            ['post_title' => 'test-delete', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'],
            'test-delete-' . uniqid() . '.jpg',
        );

        self::assertIsInt($id);

        $result = $this->manager->delete($id, true);

        self::assertInstanceOf(\WP_Post::class, $result);
        self::assertNull(get_post($id));
    }

    #[Test]
    public function prepareForJsReturnsArrayForValidAttachment(): void
    {
        $id = wp_insert_attachment(
            ['post_title' => 'test-prepare', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'],
            'test-prepare-' . uniqid() . '.jpg',
        );

        self::assertIsInt($id);

        $result = $this->manager->prepareForJs($id);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($id, $result['id']);

        wp_delete_attachment($id, true);
    }

    #[Test]
    public function prepareForJsReturnsNullForInvalidAttachment(): void
    {
        $result = $this->manager->prepareForJs(999999);

        self::assertNull($result);
    }

    #[Test]
    public function getAttachedFileReturnsFilePath(): void
    {
        $file = 'test-attached-' . uniqid() . '.jpg';
        $id = wp_insert_attachment(
            ['post_title' => 'test-attached', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'],
            $file,
        );

        self::assertIsInt($id);

        $result = $this->manager->getAttachedFile($id);

        self::assertIsString($result);
        self::assertStringContainsString($file, $result);

        wp_delete_attachment($id, true);
    }

    #[Test]
    public function updateAndGetMetadata(): void
    {
        $id = wp_insert_attachment(
            ['post_title' => 'test-metadata', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'],
            'test-metadata-' . uniqid() . '.jpg',
        );

        self::assertIsInt($id);

        $data = ['width' => 100, 'height' => 200, 'file' => 'test.jpg'];
        $this->manager->updateMetadata($id, $data);

        $metadata = $this->manager->getMetadata($id);

        self::assertIsArray($metadata);
        self::assertSame(100, $metadata['width']);
        self::assertSame(200, $metadata['height']);

        wp_delete_attachment($id, true);
    }

    #[Test]
    public function findByMetaReturnsAttachmentId(): void
    {
        $file = 'test-find-' . uniqid() . '.jpg';
        $id = wp_insert_attachment(
            ['post_title' => 'test-find', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'],
            $file,
        );

        self::assertIsInt($id);

        $result = $this->manager->findByMeta('_wp_attached_file', $file);

        self::assertSame($id, $result);

        wp_delete_attachment($id, true);
    }

    #[Test]
    public function findByMetaReturnsNullWhenNotFound(): void
    {
        $result = $this->manager->findByMeta('_wp_attached_file', 'nonexistent-' . uniqid() . '.jpg');

        self::assertNull($result);
    }
}
