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
use WpPack\Component\Mime\ExtensionMimeTypeGuesser;

final class ExtensionMimeTypeGuesserTest extends TestCase
{
    private ExtensionMimeTypeGuesser $guesser;

    protected function setUp(): void
    {
        $this->guesser = new ExtensionMimeTypeGuesser();
    }

    #[Test]
    public function isAlwaysSupported(): void
    {
        self::assertTrue($this->guesser->isGuesserSupported());
    }

    #[Test]
    public function guessesCommonExtensions(): void
    {
        self::assertSame('image/jpeg', $this->guesser->guessMimeType('/path/to/photo.jpg'));
        self::assertSame('image/png', $this->guesser->guessMimeType('/path/to/image.png'));
        self::assertSame('application/pdf', $this->guesser->guessMimeType('/path/to/doc.pdf'));
        self::assertSame('text/html', $this->guesser->guessMimeType('/path/to/page.html'));
        self::assertSame('application/json', $this->guesser->guessMimeType('/path/to/data.json'));
        self::assertSame('video/mp4', $this->guesser->guessMimeType('/path/to/video.mp4'));
        self::assertSame('audio/mpeg', $this->guesser->guessMimeType('/path/to/song.mp3'));
    }

    #[Test]
    public function handlesUppercaseExtensions(): void
    {
        self::assertSame('image/jpeg', $this->guesser->guessMimeType('/path/to/PHOTO.JPG'));
        self::assertSame('image/png', $this->guesser->guessMimeType('/path/to/IMAGE.PNG'));
    }

    #[Test]
    public function returnsNullForNoExtension(): void
    {
        self::assertNull($this->guesser->guessMimeType('/path/to/noextension'));
    }

    #[Test]
    public function returnsNullForUnknownExtension(): void
    {
        self::assertNull($this->guesser->guessMimeType('/path/to/file.xyz123'));
    }
}
