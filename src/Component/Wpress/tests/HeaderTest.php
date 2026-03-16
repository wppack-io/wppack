<?php

declare(strict_types=1);

namespace WpPack\Component\Wpress\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Wpress\Exception\InvalidArgumentException;
use WpPack\Component\Wpress\Header;

final class HeaderTest extends TestCase
{
    #[Test]
    public function packUnpackRoundTrip(): void
    {
        $header = new Header(
            name: 'style.css',
            size: 1024,
            mtime: 1706140800,
            prefix: 'themes/flavor',
        );

        $binary = $header->toBinary();

        self::assertSame(Header::SIZE, \strlen($binary));

        $restored = Header::fromBinary($binary);

        self::assertSame('style.css', $restored->name);
        self::assertSame(1024, $restored->size);
        self::assertSame(1706140800, $restored->mtime);
        self::assertSame('themes/flavor', $restored->prefix);
    }

    #[Test]
    public function fromBinaryRejectsInvalidLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Header::fromBinary('too short');
    }

    #[Test]
    public function eofDetection(): void
    {
        $eof = Header::eof();

        self::assertTrue($eof->isEof());
        self::assertSame('', $eof->name);
        self::assertSame(0, $eof->size);
        self::assertSame(0, $eof->mtime);
        self::assertSame('', $eof->prefix);
    }

    #[Test]
    public function eofBinaryIsAllNulBytes(): void
    {
        $binary = Header::eof()->toBinary();

        self::assertSame(Header::SIZE, \strlen($binary));
        self::assertSame(str_repeat("\0", Header::SIZE), $binary);
    }

    #[Test]
    public function normalHeaderIsNotEof(): void
    {
        $header = new Header(name: 'test.txt', size: 100, mtime: 1000, prefix: '.');

        self::assertFalse($header->isEof());
    }

    #[Test]
    public function getPathWithDotPrefix(): void
    {
        $header = new Header(name: 'package.json', size: 256, mtime: 1000, prefix: '.');

        self::assertSame('package.json', $header->getPath());
    }

    #[Test]
    public function getPathWithEmptyPrefix(): void
    {
        $header = new Header(name: 'package.json', size: 256, mtime: 1000, prefix: '');

        self::assertSame('package.json', $header->getPath());
    }

    #[Test]
    public function getPathWithNestedPrefix(): void
    {
        $header = new Header(name: 'image.jpg', size: 50000, mtime: 1000, prefix: 'wp-content/uploads/2024/01');

        self::assertSame('wp-content/uploads/2024/01/image.jpg', $header->getPath());
    }

    #[Test]
    public function unicodeFileName(): void
    {
        $header = new Header(name: 'テスト.txt', size: 100, mtime: 1000, prefix: 'data');

        $binary = $header->toBinary();
        $restored = Header::fromBinary($binary);

        self::assertSame('テスト.txt', $restored->name);
        self::assertSame('data', $restored->prefix);
    }

    #[Test]
    public function fromPathWithNestedPath(): void
    {
        $header = Header::fromPath('wp-content/uploads/2024/01/image.jpg', 50000, 1706140800);

        self::assertSame('image.jpg', $header->name);
        self::assertSame('wp-content/uploads/2024/01', $header->prefix);
        self::assertSame(50000, $header->size);
        self::assertSame(1706140800, $header->mtime);
    }

    #[Test]
    public function fromPathWithRootFile(): void
    {
        $header = Header::fromPath('package.json', 256, 1000);

        self::assertSame('package.json', $header->name);
        self::assertSame('.', $header->prefix);
    }

    #[Test]
    public function fromPathRejectsTooLongName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Header::fromPath(str_repeat('a', 256), 100);
    }

    #[Test]
    public function zeroSizeEntry(): void
    {
        $header = new Header(name: 'empty.txt', size: 0, mtime: 1000, prefix: '.');

        $binary = $header->toBinary();
        $restored = Header::fromBinary($binary);

        self::assertSame(0, $restored->size);
        self::assertFalse($restored->isEof());
    }

    #[Test]
    public function largeFileSize(): void
    {
        $header = new Header(name: 'big.sql', size: 99999999999999, mtime: 1000, prefix: '.');

        $binary = $header->toBinary();
        $restored = Header::fromBinary($binary);

        self::assertSame(99999999999999, $restored->size);
    }
}
