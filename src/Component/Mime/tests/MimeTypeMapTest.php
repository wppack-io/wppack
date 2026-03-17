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
    public function allExtensionsToMimesHaveReverseMapping(): void
    {
        foreach (MimeTypeMap::EXTENSIONS_TO_MIMES as $ext => $mimeTypes) {
            self::assertNotEmpty($mimeTypes, sprintf('Extension "%s" should have at least one MIME type', $ext));

            foreach ($mimeTypes as $mime) {
                self::assertArrayHasKey(
                    $mime,
                    MimeTypeMap::MIMES_TO_EXTENSIONS,
                    sprintf('MIME type "%s" (from extension "%s") should exist in MIMES_TO_EXTENSIONS', $mime, $ext),
                );
                self::assertContains(
                    $ext,
                    MimeTypeMap::MIMES_TO_EXTENSIONS[$mime],
                    sprintf('MIMES_TO_EXTENSIONS["%s"] should contain extension "%s"', $mime, $ext),
                );
            }
        }
    }

    #[Test]
    public function allMimesToExtensionsHaveReverseMapping(): void
    {
        foreach (MimeTypeMap::MIMES_TO_EXTENSIONS as $mime => $extensions) {
            self::assertNotEmpty($extensions, sprintf('MIME type "%s" should have at least one extension', $mime));

            foreach ($extensions as $ext) {
                self::assertArrayHasKey(
                    $ext,
                    MimeTypeMap::EXTENSIONS_TO_MIMES,
                    sprintf('Extension "%s" (from MIME "%s") should exist in EXTENSIONS_TO_MIMES', $ext, $mime),
                );
                self::assertContains(
                    $mime,
                    MimeTypeMap::EXTENSIONS_TO_MIMES[$ext],
                    sprintf('EXTENSIONS_TO_MIMES["%s"] should contain MIME type "%s"', $ext, $mime),
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

    #[Test]
    public function extensionTypesCoversAllExtensionsToMimes(): void
    {
        $missing = array_diff(
            array_keys(MimeTypeMap::EXTENSIONS_TO_MIMES),
            array_keys(MimeTypeMap::EXTENSION_TYPES),
        );

        self::assertSame(
            [],
            $missing,
            sprintf(
                'EXTENSION_TYPES is missing entries for: %s',
                implode(', ', $missing),
            ),
        );
    }
}
