<?php

declare(strict_types=1);

namespace WpPack\Component\Mime\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mime\MimeTypeGuesserInterface;
use WpPack\Component\Mime\MimeTypes;
use WpPack\Component\Mime\MimeTypesInterface;

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
    public function getAllowedMimeTypesWithoutWordPress(): void
    {
        if (\function_exists('get_allowed_mime_types')) {
            self::markTestSkipped('WordPress is loaded.');
        }

        $mimeTypes = new MimeTypes();
        $allowed = $mimeTypes->getAllowedMimeTypes();

        self::assertNotEmpty($allowed);
        self::assertContains('image/jpeg', $allowed);
    }

    #[Test]
    public function getExtensionTypeWithoutWordPress(): void
    {
        if (\function_exists('wp_ext2type')) {
            self::markTestSkipped('WordPress is loaded.');
        }

        $mimeTypes = new MimeTypes();

        self::assertSame('image', $mimeTypes->getExtensionType('jpg'));
        self::assertSame('video', $mimeTypes->getExtensionType('mp4'));
        self::assertSame('audio', $mimeTypes->getExtensionType('mp3'));
        self::assertSame('document', $mimeTypes->getExtensionType('pdf'));
        self::assertNull($mimeTypes->getExtensionType('xyz123'));
    }

    #[Test]
    public function getExtensionTypeStripsLeadingDot(): void
    {
        if (\function_exists('wp_ext2type')) {
            self::markTestSkipped('WordPress is loaded.');
        }

        $mimeTypes = new MimeTypes();

        self::assertSame('image', $mimeTypes->getExtensionType('.jpg'));
    }

    #[Test]
    public function validateFileWithoutWordPress(): void
    {
        if (\function_exists('wp_check_filetype_and_ext')) {
            self::markTestSkipped('WordPress is loaded.');
        }

        $path = tempnam(sys_get_temp_dir(), 'mime_test_');
        \assert(\is_string($path));
        file_put_contents($path, 'Hello, World!');

        try {
            $mimeTypes = MimeTypes::getDefault();
            $info = $mimeTypes->validateFile($path, 'document.txt');

            self::assertTrue($info->isValid());
            self::assertSame('txt', $info->extension);
            self::assertSame('text/plain', $info->mimeType);
            self::assertNull($info->properFilename);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function validateFileWithNoExtension(): void
    {
        if (\function_exists('wp_check_filetype_and_ext')) {
            self::markTestSkipped('WordPress is loaded.');
        }

        $path = tempnam(sys_get_temp_dir(), 'mime_test_');
        \assert(\is_string($path));
        file_put_contents($path, 'data');

        try {
            $mimeTypes = MimeTypes::getDefault();
            $info = $mimeTypes->validateFile($path, 'noextension');

            self::assertNull($info->extension);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function sanitizeWithoutWordPress(): void
    {
        if (\function_exists('sanitize_mime_type')) {
            self::markTestSkipped('WordPress is loaded.');
        }

        $mimeTypes = new MimeTypes();

        self::assertSame('image/jpeg', $mimeTypes->sanitize('image/jpeg'));
        self::assertSame('image/svg+xml', $mimeTypes->sanitize('image/svg+xml'));
        self::assertSame('application/vnd.ms-excel', $mimeTypes->sanitize('application/vnd.ms-excel'));
    }

    #[Test]
    public function sanitizeRemovesInvalidCharacters(): void
    {
        if (\function_exists('sanitize_mime_type')) {
            self::markTestSkipped('WordPress is loaded.');
        }

        $mimeTypes = new MimeTypes();

        self::assertSame('image/jpeg', $mimeTypes->sanitize('image/jpeg; charset=utf-8'));
    }

    private function createGuesser(?string $result): MimeTypeGuesserInterface
    {
        $guesser = $this->createMock(MimeTypeGuesserInterface::class);
        $guesser->method('isGuesserSupported')->willReturn(true);
        $guesser->method('guessMimeType')->willReturn($result);

        return $guesser;
    }
}
