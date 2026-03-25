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

namespace WpPack\Component\Mime\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mime\WordPressMimeTypeGuesser;

final class WordPressMimeTypeGuesserTest extends TestCase
{
    #[Test]
    public function isSupported(): void
    {
        $guesser = new WordPressMimeTypeGuesser();

        self::assertTrue($guesser->isGuesserSupported());
    }

    #[Test]
    public function guessesMimeTypeWhenWordPressIsLoaded(): void
    {
        $guesser = new WordPressMimeTypeGuesser();

        self::assertSame('image/jpeg', $guesser->guessMimeType('/path/to/photo.jpg'));
        self::assertSame('image/png', $guesser->guessMimeType('/path/to/image.png'));
        self::assertSame('application/pdf', $guesser->guessMimeType('/path/to/doc.pdf'));
    }
}
