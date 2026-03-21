<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Plugin\S3StoragePlugin\Message\S3ObjectRemovedMessage;

#[CoversClass(S3ObjectRemovedMessage::class)]
final class S3ObjectRemovedMessageTest extends TestCase
{
    #[Test]
    public function constructSetsProperties(): void
    {
        $message = new S3ObjectRemovedMessage(
            bucket: 'my-bucket',
            key: 'uploads/2024/01/photo.jpg',
        );

        self::assertSame('my-bucket', $message->bucket);
        self::assertSame('uploads/2024/01/photo.jpg', $message->key);
    }
}
