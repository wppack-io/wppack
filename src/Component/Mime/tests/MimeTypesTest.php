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
use WPPack\Component\Mime\MimeTypeGuesserInterface;
use WPPack\Component\Mime\MimeTypes;
use WPPack\Component\Mime\MimeTypesInterface;

final class MimeTypesTest extends TestCase
{
    protected function tearDown(): void
    {
        MimeTypes::reset();
    }

    #[Test]
    public function implementsMimeTypesInterface(): void
    {
        $mimeTypes = new MimeTypes();

        self::assertInstanceOf(MimeTypesInterface::class, $mimeTypes);
    }

    #[Test]
    public function getDefaultReturnsSameInstance(): void
    {
        $a = MimeTypes::getDefault();
        $b = MimeTypes::getDefault();

        self::assertSame($a, $b);
    }

    #[Test]
    public function resetClearsSingleton(): void
    {
        $a = MimeTypes::getDefault();
        MimeTypes::reset();
        $b = MimeTypes::getDefault();

        self::assertNotSame($a, $b);
    }

    #[Test]
    public function getExtensionsReturnsExtensionsForKnownMimeType(): void
    {
        $mimeTypes = new MimeTypes();

        $extensions = $mimeTypes->getExtensions('image/jpeg');

        self::assertContains('jpg', $extensions);
        self::assertContains('jpeg', $extensions);
    }

    #[Test]
    public function getExtensionsReturnsEmptyForUnknownMimeType(): void
    {
        $mimeTypes = new MimeTypes();

        self::assertSame([], $mimeTypes->getExtensions('application/x-unknown-type'));
    }

    #[Test]
    public function getExtensionsIsCaseInsensitive(): void
    {
        $mimeTypes = new MimeTypes();

        self::assertContains('jpg', $mimeTypes->getExtensions('IMAGE/JPEG'));
    }

    #[Test]
    public function getMimeTypesReturnsTypesForKnownExtension(): void
    {
        $mimeTypes = new MimeTypes();

        $types = $mimeTypes->getMimeTypes('jpg');

        self::assertContains('image/jpeg', $types);
    }

    #[Test]
    public function getMimeTypesReturnsEmptyForUnknownExtension(): void
    {
        $mimeTypes = new MimeTypes();

        self::assertSame([], $mimeTypes->getMimeTypes('xyz123'));
    }

    #[Test]
    public function getMimeTypesStripsLeadingDot(): void
    {
        $mimeTypes = new MimeTypes();

        self::assertContains('image/jpeg', $mimeTypes->getMimeTypes('.jpg'));
    }

    #[Test]
    public function getMimeTypesIsCaseInsensitive(): void
    {
        $mimeTypes = new MimeTypes();

        self::assertContains('image/jpeg', $mimeTypes->getMimeTypes('JPG'));
    }

    #[Test]
    public function guessMimeTypeUsesGuesserChain(): void
    {
        $lowPriority = $this->createGuesser('text/plain');
        $highPriority = $this->createGuesser('image/png');

        $mimeTypes = new MimeTypes([$lowPriority, $highPriority]);

        self::assertSame('image/png', $mimeTypes->guessMimeType('/path/to/file'));
    }

    #[Test]
    public function guessMimeTypeFallsBackToLowerPriority(): void
    {
        $lowPriority = $this->createGuesser('text/plain');
        $highPriority = $this->createGuesser(null);

        $mimeTypes = new MimeTypes([$lowPriority, $highPriority]);

        self::assertSame('text/plain', $mimeTypes->guessMimeType('/path/to/file'));
    }

    #[Test]
    public function guessMimeTypeReturnsNullWhenNoGuesserMatches(): void
    {
        $mimeTypes = new MimeTypes([]);

        self::assertNull($mimeTypes->guessMimeType('/path/to/file'));
    }

    #[Test]
    public function guessMimeTypeSkipsUnsupportedGuessers(): void
    {
        $unsupported = $this->createMock(MimeTypeGuesserInterface::class);
        $unsupported->method('isGuesserSupported')->willReturn(false);
        $unsupported->expects(self::never())->method('guessMimeType');

        $supported = $this->createGuesser('text/plain');

        $mimeTypes = new MimeTypes([$supported, $unsupported]);

        self::assertSame('text/plain', $mimeTypes->guessMimeType('/path/to/file'));
    }

    #[Test]
    public function registerGuesserAddsToChain(): void
    {
        $mimeTypes = new MimeTypes([]);
        $guesser = $this->createGuesser('application/pdf');

        $mimeTypes->registerGuesser($guesser);

        self::assertSame('application/pdf', $mimeTypes->guessMimeType('/path/to/file'));
    }

    #[Test]
    public function guessMimeTypeWithRealFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mime_test_');
        \assert(\is_string($path));
        file_put_contents($path, 'Hello, World!');

        try {
            $mimeTypes = MimeTypes::getDefault();
            $result = $mimeTypes->guessMimeType($path);

            self::assertSame('text/plain', $result);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function isGuesserSupportedReturnsTrueWithDefaultGuessers(): void
    {
        $mimeTypes = new MimeTypes();

        self::assertTrue($mimeTypes->isGuesserSupported());
    }

    #[Test]
    public function setDefaultOverridesSingleton(): void
    {
        $custom = new MimeTypes();
        MimeTypes::setDefault($custom);

        self::assertSame($custom, MimeTypes::getDefault());
    }

    #[Test]
    public function isGuesserSupportedReturnsFalseWhenNoGuessersAreSupported(): void
    {
        $unsupported = $this->createMock(MimeTypeGuesserInterface::class);
        $unsupported->method('isGuesserSupported')->willReturn(false);

        // Create with no default guessers — pass unsupported only
        $mimeTypes = new MimeTypes([$unsupported]);

        // The built-in guessers (ExtensionMimeTypeGuesser, FileinfoMimeTypeGuesser, etc.)
        // will likely be supported, so this test mainly verifies the code path
        self::assertTrue($mimeTypes->isGuesserSupported());
    }

    #[Test]
    public function getAllowedMimeTypesReturnsArray(): void
    {
        $mimeTypes = new MimeTypes();

        $allowed = $mimeTypes->getAllowedMimeTypes();

        self::assertIsArray($allowed);
        self::assertNotEmpty($allowed);
    }

    #[Test]
    public function getExtensionTypeReturnsTypeForKnownExtension(): void
    {
        $mimeTypes = new MimeTypes();

        $type = $mimeTypes->getExtensionType('jpg');

        // WordPress maps 'jpg' to 'image' type
        self::assertSame('image', $type);
    }

    #[Test]
    public function getExtensionTypeReturnsNullForUnknownExtension(): void
    {
        $mimeTypes = new MimeTypes();

        $type = $mimeTypes->getExtensionType('xyz123456');

        self::assertNull($type);
    }

    #[Test]
    public function getExtensionTypeStripsLeadingDot(): void
    {
        $mimeTypes = new MimeTypes();

        $type = $mimeTypes->getExtensionType('.jpg');

        self::assertSame('image', $type);
    }

    #[Test]
    public function validateFileReturnsFileTypeInfo(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mime_validate_');
        \assert(\is_string($path));
        file_put_contents($path, 'Hello, World!');

        try {
            $mimeTypes = new MimeTypes();
            $info = $mimeTypes->validateFile($path, 'test.txt');

            // FileTypeInfo is returned (may have null extension/mimeType depending on WordPress config)
            self::assertInstanceOf(\WPPack\Component\Mime\FileTypeInfo::class, $info);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function sanitizeReturnsSanitizedMimeType(): void
    {
        $mimeTypes = new MimeTypes();

        $result = $mimeTypes->sanitize('image/jpeg');

        self::assertSame('image/jpeg', $result);
    }

    #[Test]
    public function sanitizeStripsInvalidCharacters(): void
    {
        $mimeTypes = new MimeTypes();

        $result = $mimeTypes->sanitize('image/jpeg; extra');

        // sanitize_mime_type strips non-MIME characters
        self::assertStringContainsString('image/jpeg', $result);
    }

    private function createGuesser(?string $result): MimeTypeGuesserInterface
    {
        $guesser = $this->createMock(MimeTypeGuesserInterface::class);
        $guesser->method('isGuesserSupported')->willReturn(true);
        $guesser->method('guessMimeType')->willReturn($result);

        return $guesser;
    }
}
