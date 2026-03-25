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

namespace WpPack\Component\Mailer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Attachment;
use WpPack\Component\Mailer\Exception\InvalidArgumentException;

final class AttachmentTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'wppack_attachment_test_');
        file_put_contents($this->tempFile, 'test content');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    #[Test]
    public function constructWithValidFile(): void
    {
        $attachment = new Attachment($this->tempFile);

        self::assertSame($this->tempFile, $attachment->path);
        self::assertNull($attachment->name);
        self::assertNull($attachment->contentType);
        self::assertFalse($attachment->inline);
    }

    #[Test]
    public function constructWithAllParameters(): void
    {
        $attachment = new Attachment(
            $this->tempFile,
            'document.pdf',
            'application/pdf',
            false,
        );

        self::assertSame($this->tempFile, $attachment->path);
        self::assertSame('document.pdf', $attachment->name);
        self::assertSame('application/pdf', $attachment->contentType);
        self::assertFalse($attachment->inline);
    }

    #[Test]
    public function constructWithInlineFlag(): void
    {
        $attachment = new Attachment(
            $this->tempFile,
            'logo.png',
            'image/png',
            inline: true,
        );

        self::assertTrue($attachment->inline);
        self::assertSame('logo.png', $attachment->name);
        self::assertSame('image/png', $attachment->contentType);
    }

    #[Test]
    public function constructWithNonExistentFileThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not readable');

        new Attachment('/non/existent/file.txt');
    }

    #[Test]
    public function constructWithDirectoryThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not readable');

        new Attachment(sys_get_temp_dir());
    }

    #[Test]
    public function propertiesAreReadonly(): void
    {
        $attachment = new Attachment($this->tempFile, 'test.txt', 'text/plain', true);

        $reflection = new \ReflectionClass($attachment);

        self::assertTrue($reflection->getProperty('path')->isReadOnly());
        self::assertTrue($reflection->getProperty('name')->isReadOnly());
        self::assertTrue($reflection->getProperty('contentType')->isReadOnly());
        self::assertTrue($reflection->getProperty('inline')->isReadOnly());
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(Attachment::class);

        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function nameDefaultsToNull(): void
    {
        $attachment = new Attachment($this->tempFile);

        self::assertNull($attachment->name);
    }

    #[Test]
    public function contentTypeDefaultsToNull(): void
    {
        $attachment = new Attachment($this->tempFile);

        self::assertNull($attachment->contentType);
    }

    #[Test]
    public function inlineDefaultsToFalse(): void
    {
        $attachment = new Attachment($this->tempFile);

        self::assertFalse($attachment->inline);
    }
}
