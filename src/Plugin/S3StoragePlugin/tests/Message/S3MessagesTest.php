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

namespace WPPack\Plugin\S3StoragePlugin\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Plugin\S3StoragePlugin\Message\GenerateThumbnailsMessage;
use WPPack\Plugin\S3StoragePlugin\Message\S3ObjectCreatedMessage;

#[CoversClass(GenerateThumbnailsMessage::class)]
#[CoversClass(S3ObjectCreatedMessage::class)]
final class S3MessagesTest extends TestCase
{
    #[Test]
    public function generateThumbnailsMessageCarriesAttachmentAndBlog(): void
    {
        $msg = new GenerateThumbnailsMessage(attachmentId: 42, blogId: 1);

        self::assertSame(42, $msg->attachmentId);
        self::assertSame(1, $msg->blogId);
    }

    #[Test]
    public function s3ObjectCreatedMessageCarriesMetadata(): void
    {
        $msg = new S3ObjectCreatedMessage(
            bucket: 'my-bucket',
            key: '2024/01/image.jpg',
            size: 123456,
            eTag: 'etag-abc',
        );

        self::assertSame('my-bucket', $msg->bucket);
        self::assertSame('2024/01/image.jpg', $msg->key);
        self::assertSame(123456, $msg->size);
        self::assertSame('etag-abc', $msg->eTag);
    }

    #[Test]
    public function messagesAreFinalReadonly(): void
    {
        foreach ([GenerateThumbnailsMessage::class, S3ObjectCreatedMessage::class] as $class) {
            $ref = new \ReflectionClass($class);
            self::assertTrue($ref->isFinal(), "{$class} should be final");
            self::assertTrue($ref->isReadOnly(), "{$class} should be readonly");
        }
    }
}
