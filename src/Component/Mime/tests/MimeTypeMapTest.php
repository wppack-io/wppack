<?php

declare(strict_types=1);

namespace WpPack\Component\Mime\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mime\MimeTypeMap;

final class MimeTypeMapTest extends TestCase
{
    #[Test]
    public function extensionsToMimesContainsCommonEntries(): void
    {
        self::assertSame(['image/jpeg'], MimeTypeMap::EXTENSIONS_TO_MIMES['jpg']);
        self::assertSame(['image/png'], MimeTypeMap::EXTENSIONS_TO_MIMES['png']);
        self::assertSame(['image/gif'], MimeTypeMap::EXTENSIONS_TO_MIMES['gif']);
        self::assertSame(['application/pdf'], MimeTypeMap::EXTENSIONS_TO_MIMES['pdf']);
        self::assertSame(['text/html'], MimeTypeMap::EXTENSIONS_TO_MIMES['html']);
        self::assertSame(['application/zip'], MimeTypeMap::EXTENSIONS_TO_MIMES['zip']);
    }

    #[Test]
    public function mimesToExtensionsContainsCommonEntries(): void
    {
        self::assertContains('jpg', MimeTypeMap::MIMES_TO_EXTENSIONS['image/jpeg']);
        self::assertContains('png', MimeTypeMap::MIMES_TO_EXTENSIONS['image/png']);
        self::assertContains('pdf', MimeTypeMap::MIMES_TO_EXTENSIONS['application/pdf']);
        self::assertContains('html', MimeTypeMap::MIMES_TO_EXTENSIONS['text/html']);
    }

    #[Test]
    public function bidirectionalConsistencyForCommonTypes(): void
    {
        $checkExtensions = ['jpg', 'png', 'gif', 'pdf', 'html', 'css', 'js', 'json', 'xml', 'zip', 'mp3', 'mp4'];

        foreach ($checkExtensions as $ext) {
            $mimeTypes = MimeTypeMap::EXTENSIONS_TO_MIMES[$ext] ?? [];
            self::assertNotEmpty($mimeTypes, sprintf('Extension "%s" should have MIME types', $ext));

            foreach ($mimeTypes as $mime) {
                $reverseExtensions = MimeTypeMap::MIMES_TO_EXTENSIONS[$mime] ?? [];
                self::assertContains(
                    $ext,
                    $reverseExtensions,
                    sprintf('MIME type "%s" should map back to extension "%s"', $mime, $ext),
                );
            }
        }
    }

    #[Test]
    public function allExtensionKeysAreLowerCase(): void
    {
        foreach (array_keys(MimeTypeMap::EXTENSIONS_TO_MIMES) as $ext) {
            self::assertSame(strtolower($ext), $ext, sprintf('Extension key "%s" should be lowercase', $ext));
        }
    }

    #[Test]
    public function allMimeKeysAreLowerCase(): void
    {
        foreach (array_keys(MimeTypeMap::MIMES_TO_EXTENSIONS) as $mime) {
            self::assertSame(strtolower($mime), $mime, sprintf('MIME key "%s" should be lowercase', $mime));
        }
    }

    #[Test]
    public function extensionTypesCoversCommonTypes(): void
    {
        self::assertSame('image', MimeTypeMap::EXTENSION_TYPES['jpg']);
        self::assertSame('image', MimeTypeMap::EXTENSION_TYPES['png']);
        self::assertSame('audio', MimeTypeMap::EXTENSION_TYPES['mp3']);
        self::assertSame('video', MimeTypeMap::EXTENSION_TYPES['mp4']);
        self::assertSame('document', MimeTypeMap::EXTENSION_TYPES['pdf']);
        self::assertSame('document', MimeTypeMap::EXTENSION_TYPES['docx']);
        self::assertSame('spreadsheet', MimeTypeMap::EXTENSION_TYPES['xlsx']);
        self::assertSame('archive', MimeTypeMap::EXTENSION_TYPES['zip']);
        self::assertSame('code', MimeTypeMap::EXTENSION_TYPES['html']);
        self::assertSame('font', MimeTypeMap::EXTENSION_TYPES['woff2']);
        self::assertSame('text', MimeTypeMap::EXTENSION_TYPES['txt']);
    }

    #[Test]
    public function extensionTypesAllLowerCaseKeys(): void
    {
        foreach (array_keys(MimeTypeMap::EXTENSION_TYPES) as $ext) {
            self::assertSame(strtolower($ext), $ext, sprintf('Extension type key "%s" should be lowercase', $ext));
        }
    }
}
