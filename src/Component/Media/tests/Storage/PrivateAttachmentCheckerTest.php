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

namespace WpPack\Component\Media\Tests\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Media\Storage\PrivateAttachmentChecker;

#[CoversClass(PrivateAttachmentChecker::class)]
final class PrivateAttachmentCheckerTest extends TestCase
{
    private PrivateAttachmentChecker $checker;
    private int $attachmentId;

    protected function setUp(): void
    {
        $this->checker = new PrivateAttachmentChecker();
        $this->attachmentId = wp_insert_attachment([
            'post_title' => 'Test Attachment',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ]);
    }

    protected function tearDown(): void
    {
        wp_delete_attachment($this->attachmentId, true);
    }

    #[Test]
    public function setPrivateTrueMakesAttachmentPrivate(): void
    {
        $this->checker->setPrivate($this->attachmentId, true);

        self::assertTrue($this->checker->isPrivate($this->attachmentId));
    }

    #[Test]
    public function setPrivateFalseMakesAttachmentPublic(): void
    {
        $this->checker->setPrivate($this->attachmentId, true);
        $this->checker->setPrivate($this->attachmentId, false);

        self::assertFalse($this->checker->isPrivate($this->attachmentId));
    }

    #[Test]
    public function initialStateIsNotPrivate(): void
    {
        self::assertFalse($this->checker->isPrivate($this->attachmentId));
    }
}
