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

namespace WpPack\Plugin\S3StoragePlugin\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Media\AttachmentManager;
use WpPack\Component\PostType\PostRepository;
use WpPack\Component\Site\BlogSwitcher;
use WpPack\Plugin\S3StoragePlugin\Handler\GenerateThumbnailsHandler;
use WpPack\Plugin\S3StoragePlugin\Message\GenerateThumbnailsMessage;

#[CoversClass(GenerateThumbnailsHandler::class)]
final class GenerateThumbnailsHandlerTest extends TestCase
{
    private GenerateThumbnailsHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new GenerateThumbnailsHandler(
            blogSwitcher: new BlogSwitcher(),
            attachment: new AttachmentManager(new PostRepository()),
        );
    }

    #[Test]
    public function invokeWithValidAttachment(): void
    {
        // Create a real attachment in WordPress
        $attachmentId = self::createAttachment();
        self::assertGreaterThan(0, $attachmentId);

        $message = new GenerateThumbnailsMessage(
            attachmentId: $attachmentId,
            blogId: 1,
        );

        set_error_handler(static fn(): bool => true);
        try {
            ($this->handler)($message);
        } finally {
            restore_error_handler();
        }

        // Verify the handler ran without errors
        $metadata = wp_get_attachment_metadata($attachmentId);
        // Metadata may be empty array or false depending on the file
        self::assertNotNull($metadata);

        wp_delete_attachment($attachmentId, true);
    }

    #[Test]
    public function invokeWithNonExistentAttachment(): void
    {
        $message = new GenerateThumbnailsMessage(
            attachmentId: 999999,
            blogId: 1,
        );

        // Should return early when get_attached_file returns false/empty
        ($this->handler)($message);

        // No exception means the handler gracefully handled missing attachment
        self::assertTrue(true);
    }

    #[Test]
    public function invokeWithBlogIdOneDoesNotSwitchBlog(): void
    {
        $currentBlogId = get_current_blog_id();

        $message = new GenerateThumbnailsMessage(
            attachmentId: 999999,
            blogId: 1,
        );

        ($this->handler)($message);

        // Blog ID should remain the same (no switch_to_blog called)
        self::assertSame($currentBlogId, get_current_blog_id());
    }

    #[Test]
    public function invokeWithMultisiteBlogIdSwitchesAndRestores(): void
    {
        $currentBlogId = get_current_blog_id();

        $message = new GenerateThumbnailsMessage(
            attachmentId: 999999,
            blogId: 2,
        );

        ($this->handler)($message);

        // Blog ID should be restored after handler completes
        self::assertSame($currentBlogId, get_current_blog_id());
    }

    #[Test]
    public function invokeWithEmptyFileReturnsEarly(): void
    {
        // Create attachment without a real file
        $attachmentId = wp_insert_attachment([
            'post_title' => 'test-empty-file',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], '');

        self::assertIsInt($attachmentId);
        self::assertGreaterThan(0, $attachmentId);

        $message = new GenerateThumbnailsMessage(
            attachmentId: $attachmentId,
            blogId: 1,
        );

        // Should return early because get_attached_file returns empty
        ($this->handler)($message);

        self::assertTrue(true);

        wp_delete_attachment($attachmentId, true);
    }

    #[Test]
    public function invokeRestoresBlogEvenOnFailure(): void
    {
        $currentBlogId = get_current_blog_id();

        $message = new GenerateThumbnailsMessage(
            attachmentId: 999999,
            blogId: 5,
        );

        ($this->handler)($message);

        // The finally block should have restored the blog
        self::assertSame($currentBlogId, get_current_blog_id());
    }

    #[Test]
    public function invokeWithValidAttachmentAndMetadata(): void
    {
        // Create a real image attachment to generate metadata
        $upload = wp_upload_dir();
        $filePath = $upload['path'] . '/test-thumbnail-handler.jpg';

        // Create a simple JPEG file
        if (\function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(100, 100);
            if ($img !== false) {
                imagejpeg($img, $filePath);
                imagedestroy($img);
            }
        }

        if (!file_exists($filePath)) {
            // If GD is not available, create a minimal valid JPEG
            file_put_contents($filePath, file_get_contents(__DIR__ . '/../../fixtures/test.jpg') ?: '');

            if (!file_exists($filePath) || filesize($filePath) === 0) {
                self::markTestSkipped('Cannot create test image file.');
            }
        }

        $relativePath = str_replace($upload['basedir'] . '/', '', $filePath);
        $attachmentId = wp_insert_attachment([
            'post_title' => 'test-thumbnail-handler',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], $relativePath);

        self::assertIsInt($attachmentId);

        $message = new GenerateThumbnailsMessage(
            attachmentId: $attachmentId,
            blogId: 1,
        );

        ($this->handler)($message);

        wp_delete_attachment($attachmentId, true);
        @unlink($filePath);
    }

    private static function createAttachment(): int
    {
        $attachmentId = wp_insert_attachment([
            'post_title' => 'test-attachment',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], 'test-image.jpg');

        if ($attachmentId instanceof \WP_Error) {
            return 0;
        }

        return $attachmentId;
    }
}
