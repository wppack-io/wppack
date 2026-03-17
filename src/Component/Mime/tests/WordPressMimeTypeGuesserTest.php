<?php

declare(strict_types=1);

namespace WpPack\Component\Mime\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mime\WordPressMimeTypeGuesser;

final class WordPressMimeTypeGuesserTest extends TestCase
{
    #[Test]
    public function isSupportedOnlyWhenWordPressIsLoaded(): void
    {
        $guesser = new WordPressMimeTypeGuesser();

        if (\function_exists('wp_check_filetype')) {
            self::assertTrue($guesser->isGuesserSupported());
        } else {
            self::assertFalse($guesser->isGuesserSupported());
        }
    }

    #[Test]
    public function returnsNullWhenWordPressIsNotLoaded(): void
    {
        if (\function_exists('wp_check_filetype')) {
            self::markTestSkipped('WordPress is loaded, cannot test non-WP behavior.');
        }

        $guesser = new WordPressMimeTypeGuesser();

        self::assertNull($guesser->guessMimeType('/path/to/photo.jpg'));
    }

    #[Test]
    public function guessesMimeTypeWhenWordPressIsLoaded(): void
    {
        if (!\function_exists('wp_check_filetype')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $guesser = new WordPressMimeTypeGuesser();

        self::assertSame('image/jpeg', $guesser->guessMimeType('/path/to/photo.jpg'));
        self::assertSame('image/png', $guesser->guessMimeType('/path/to/image.png'));
        self::assertSame('application/pdf', $guesser->guessMimeType('/path/to/doc.pdf'));
    }
}
