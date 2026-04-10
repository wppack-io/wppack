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

    #[Test]
    public function fromPathRejectsTooLongPrefix(): void
    {
        $longPrefix = str_repeat('a/', 2049); // > 4096 bytes
        $path = $longPrefix . 'file.txt';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prefix must not exceed');

        Header::fromPath($path, 100);
    }

    #[Test]
    public function fromPathWithNullMtimeUsesCurrentTime(): void
    {
        $before = time();
        $header = Header::fromPath('dir/file.txt', 100);
        $after = time();

        self::assertGreaterThanOrEqual($before, $header->mtime);
        self::assertLessThanOrEqual($after, $header->mtime);
        self::assertSame('file.txt', $header->name);
        self::assertSame('dir', $header->prefix);
    }

    #[Test]
    public function fromPathWithExplicitMtime(): void
    {
        $header = Header::fromPath('dir/file.txt', 200, 1706140800);

        self::assertSame(1706140800, $header->mtime);
    }

    #[Test]
    public function fromBinaryWithExactSizeData(): void
    {
        // Test fromBinary with exactly SIZE bytes of all zeros (EOF header)
        $data = str_repeat("\0", Header::SIZE);
        $header = Header::fromBinary($data);

        self::assertTrue($header->isEof());
    }

    #[Test]
    public function isEofReturnsFalseWhenOnlyNameIsSet(): void
    {
        $header = new Header(name: 'test', size: 0, mtime: 0, prefix: '');

        self::assertFalse($header->isEof());
    }

    #[Test]
    public function isEofReturnsTrueForV2EofWithArchiveSize(): void
    {
        // v2 EOF: name='' mtime=0 prefix='' but size contains archive size and crc32 contains checksum
        $header = new Header(name: '', size: 12345, mtime: 0, prefix: '', crc32: 'abcd1234');

        self::assertTrue($header->isEof());
    }

    #[Test]
    public function isEofReturnsFalseWhenOnlyMtimeIsSet(): void
    {
        $header = new Header(name: '', size: 0, mtime: 1, prefix: '');

        self::assertFalse($header->isEof());
    }

    #[Test]
    public function isEofReturnsFalseWhenOnlyPrefixIsSet(): void
    {
        $header = new Header(name: '', size: 0, mtime: 0, prefix: 'something');

        self::assertFalse($header->isEof());
    }

    #[Test]
    public function toBinaryRoundTripPreservesAllFields(): void
    {
        $header = new Header(
            name: 'deeply-nested-file.php',
            size: 42,
            mtime: 1700000000,
            prefix: 'wp-content/plugins/my-plugin/src/Service',
        );

        $binary = $header->toBinary();
        self::assertSame(Header::SIZE, \strlen($binary));

        $restored = Header::fromBinary($binary);
        self::assertSame($header->name, $restored->name);
        self::assertSame($header->size, $restored->size);
        self::assertSame($header->mtime, $restored->mtime);
        self::assertSame($header->prefix, $restored->prefix);
    }

    #[Test]
    public function fromPathWithSingleLevelPath(): void
    {
        $header = Header::fromPath('dir/file.txt', 50, 1000);

        self::assertSame('file.txt', $header->name);
        self::assertSame('dir', $header->prefix);
        self::assertSame(50, $header->size);
    }

    #[Test]
    public function fromPathNameAtExactLimit(): void
    {
        $name = str_repeat('a', 255);

        $header = Header::fromPath($name, 100, 1000);

        self::assertSame($name, $header->name);
        self::assertSame('.', $header->prefix);
    }
}
