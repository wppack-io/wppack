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

namespace WPPack\Component\Mime\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Mime\FileTypeInfo;

final class FileTypeInfoTest extends TestCase
{
    #[Test]
    public function isValidReturnsTrueWhenBothExtensionAndMimeTypeAreSet(): void
    {
        $info = new FileTypeInfo(extension: 'jpg', mimeType: 'image/jpeg');

        self::assertTrue($info->isValid());
    }

    #[Test]
    public function isValidReturnsFalseWhenExtensionIsNull(): void
    {
        $info = new FileTypeInfo(extension: null, mimeType: 'image/jpeg');

        self::assertFalse($info->isValid());
    }

    #[Test]
    public function isValidReturnsFalseWhenMimeTypeIsNull(): void
    {
        $info = new FileTypeInfo(extension: 'jpg', mimeType: null);

        self::assertFalse($info->isValid());
    }

    #[Test]
    public function isValidReturnsFalseWhenBothAreNull(): void
    {
        $info = new FileTypeInfo(extension: null, mimeType: null);

        self::assertFalse($info->isValid());
    }

    #[Test]
    public function properFilenameDefaultsToNull(): void
    {
        $info = new FileTypeInfo(extension: 'jpg', mimeType: 'image/jpeg');

        self::assertNull($info->properFilename);
    }

    #[Test]
    public function properFilenameCanBeSet(): void
    {
        $info = new FileTypeInfo(
            extension: 'jpg',
            mimeType: 'image/jpeg',
            properFilename: 'corrected.jpg',
        );

        self::assertSame('corrected.jpg', $info->properFilename);
    }

    #[Test]
    public function isValidReturnsTrueForEmptyStrings(): void
    {
        $info = new FileTypeInfo(extension: '', mimeType: '');

        // Empty strings are non-null, so isValid() returns true.
        // Callers (e.g. MimeTypes::validateFile) are responsible for
        // converting empty strings to null before constructing FileTypeInfo.
        self::assertTrue($info->isValid());
    }
}
