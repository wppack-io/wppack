<?php

declare(strict_types=1);

namespace WpPack\Component\Mime\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mime\FileinfoMimeTypeGuesser;

final class FileinfoMimeTypeGuesserTest extends TestCase
{
    private FileinfoMimeTypeGuesser $guesser;

    protected function setUp(): void
    {
        if (!\function_exists('finfo_open')) {
            self::markTestSkipped('finfo extension is not available.');
        }

        $this->guesser = new FileinfoMimeTypeGuesser();
    }

    #[Test]
    public function isSupported(): void
    {
        self::assertTrue($this->guesser->isGuesserSupported());
    }

    #[Test]
    public function guessesTextFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mime_test_');
        \assert(\is_string($path));
        file_put_contents($path, 'Hello, World!');

        try {
            self::assertSame('text/plain', $this->guesser->guessMimeType($path));
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function guessesPngFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mime_test_') . '.png';

        // Create a minimal valid 1x1 PNG using GD
        if (!\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension is not available.');
        }

        $img = imagecreatetruecolor(1, 1);
        \assert($img !== false);
        imagepng($img, $path);
        imagedestroy($img);

        try {
            self::assertSame('image/png', $this->guesser->guessMimeType($path));
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function returnsNullForNonExistentFile(): void
    {
        self::assertNull($this->guesser->guessMimeType('/nonexistent/file.txt'));
    }

    #[Test]
    public function returnsNullForDirectory(): void
    {
        self::assertNull($this->guesser->guessMimeType(sys_get_temp_dir()));
    }
}
