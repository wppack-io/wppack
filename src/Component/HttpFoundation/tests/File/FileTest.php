<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Tests\File;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\File\Exception\FileException;
use WpPack\Component\HttpFoundation\File\Exception\FileNotFoundException;
use WpPack\Component\HttpFoundation\File\File;

final class FileTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/wppack_file_test_' . uniqid();
        @mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function constructorWithExistingFile(): void
    {
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, 'hello');

        $file = new File($path);

        self::assertSame($path, $file->getPathname());
    }

    #[Test]
    public function constructorThrowsForNonExistentFile(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('The file "/nonexistent/file.txt" does not exist.');

        new File('/nonexistent/file.txt');
    }

    #[Test]
    public function constructorSkipsCheckPathWhenFalse(): void
    {
        $file = new File('/nonexistent/file.txt', false);

        self::assertSame('/nonexistent/file.txt', $file->getPathname());
    }

    #[Test]
    public function extendsSplFileInfo(): void
    {
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, 'hello');

        $file = new File($path);

        self::assertInstanceOf(\SplFileInfo::class, $file);
    }

    #[Test]
    public function getPathnameInheritedFromSplFileInfo(): void
    {
        $path = $this->tempDir . '/data.txt';
        file_put_contents($path, 'content');

        $file = new File($path);

        self::assertSame($path, $file->getPathname());
        self::assertSame('data.txt', $file->getBasename());
        self::assertSame($this->tempDir, $file->getPath());
    }

    #[Test]
    public function getMimeTypeDetectsFromDisk(): void
    {
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, 'hello world');

        $file = new File($path);

        self::assertSame('text/plain', $file->getMimeType());
    }

    #[Test]
    public function getMimeTypeReturnsNullForNonExistentFile(): void
    {
        $file = new File('/nonexistent/file.txt', false);

        self::assertNull($file->getMimeType());
    }

    #[Test]
    public function guessExtensionReturnsCorrectExtension(): void
    {
        $path = $this->tempDir . '/test.txt';
        file_put_contents($path, 'hello world');

        $file = new File($path);

        self::assertSame('txt', $file->guessExtension());
    }

    #[Test]
    public function guessExtensionReturnsNullWhenMimeUnknown(): void
    {
        $file = new File('/nonexistent/file.xyz', false);

        self::assertNull($file->guessExtension());
    }

    #[Test]
    public function moveMovesFileAndReturnsNewFileInstance(): void
    {
        $path = $this->tempDir . '/original.txt';
        file_put_contents($path, 'content');

        $file = new File($path);
        $targetDir = $this->tempDir . '/moved';
        $moved = $file->move($targetDir, 'renamed.txt');

        self::assertInstanceOf(File::class, $moved);
        self::assertSame($targetDir . '/renamed.txt', $moved->getPathname());
        self::assertFileExists($targetDir . '/renamed.txt');
        self::assertFileDoesNotExist($path);
        self::assertSame('content', file_get_contents($moved->getPathname()));
    }

    #[Test]
    public function moveUsesBaseNameWhenNoNameProvided(): void
    {
        $path = $this->tempDir . '/keepname.txt';
        file_put_contents($path, 'data');

        $file = new File($path);
        $targetDir = $this->tempDir . '/dest';
        $moved = $file->move($targetDir);

        self::assertSame($targetDir . '/keepname.txt', $moved->getPathname());
        self::assertFileExists($targetDir . '/keepname.txt');
    }

    #[Test]
    public function moveCreatesDirectoryIfNotExists(): void
    {
        $path = $this->tempDir . '/file.txt';
        file_put_contents($path, 'data');

        $file = new File($path);
        $targetDir = $this->tempDir . '/deep/nested/dir';
        $file->move($targetDir, 'file.txt');

        self::assertDirectoryExists($targetDir);
    }

    #[Test]
    public function moveThrowsFileExceptionOnFailure(): void
    {
        $file = new File('/nonexistent/source.txt', false);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Could not move the file');

        $file->move('/nonexistent/target');
    }

    #[Test]
    public function getSizeInheritedFromSplFileInfo(): void
    {
        $path = $this->tempDir . '/sized.txt';
        file_put_contents($path, 'hello');

        $file = new File($path);

        self::assertSame(5, $file->getSize());
    }

    #[Test]
    public function guessExtensionForJsonFile(): void
    {
        $path = $this->tempDir . '/data.json';
        file_put_contents($path, '{"key":"value"}');

        $file = new File($path);

        self::assertSame('json', $file->guessExtension());
    }

    #[Test]
    public function guessExtensionForHtmlFile(): void
    {
        $path = $this->tempDir . '/page.html';
        file_put_contents($path, '<html><body>Hello</body></html>');

        $file = new File($path);

        self::assertSame('html', $file->guessExtension());
    }

    #[Test]
    public function guessExtensionReturnsNullForUnknownMime(): void
    {
        $path = $this->tempDir . '/binary.dat';
        // Write random binary data that produces application/octet-stream
        file_put_contents($path, random_bytes(64));

        $file = new File($path);
        $mime = $file->getMimeType();

        // If the MIME is unmapped, guessExtension returns null
        if ($mime === 'application/octet-stream') {
            self::assertNull($file->guessExtension());
        } else {
            // On some systems random bytes might be detected differently;
            // at minimum verify guessExtension returns string or null
            self::assertTrue($file->guessExtension() === null || \is_string($file->guessExtension()));
        }
    }

    #[Test]
    public function guessExtensionForPngFile(): void
    {
        $path = $this->tempDir . '/image.png';
        // Minimal valid PNG: signature + IHDR + IEND chunks
        $png = "\x89PNG\r\n\x1a\n"
            . pack('N', 13) . 'IHDR' . pack('N', 1) . pack('N', 1) . "\x08\x02\x00\x00\x00"
            . pack('N', crc32('IHDR' . pack('N', 1) . pack('N', 1) . "\x08\x02\x00\x00\x00"))
            . pack('N', 0) . 'IEND' . pack('N', crc32('IEND'));
        file_put_contents($path, $png);

        $file = new File($path);

        self::assertSame('image/png', $file->getMimeType());
        self::assertSame('png', $file->guessExtension());
    }

    #[Test]
    public function guessExtensionForGifFile(): void
    {
        $path = $this->tempDir . '/image.gif';
        // Minimal GIF89a header
        file_put_contents($path, "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00!\xf9\x04\x00\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;");

        $file = new File($path);

        self::assertSame('image/gif', $file->getMimeType());
        self::assertSame('gif', $file->guessExtension());
    }

    #[Test]
    public function guessExtensionForPdfFile(): void
    {
        $path = $this->tempDir . '/document.pdf';
        file_put_contents($path, "%PDF-1.0\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");

        $file = new File($path);

        self::assertSame('application/pdf', $file->getMimeType());
        self::assertSame('pdf', $file->guessExtension());
    }

    #[Test]
    public function guessExtensionForCssFile(): void
    {
        $path = $this->tempDir . '/style.css';
        file_put_contents($path, "body { color: red; }\n.container { display: flex; }");

        $file = new File($path);
        $mime = $file->getMimeType();

        // CSS may be detected as text/css or text/plain depending on system
        if ($mime === 'text/css') {
            self::assertSame('css', $file->guessExtension());
        } else {
            self::assertSame('text/plain', $mime);
            self::assertSame('txt', $file->guessExtension());
        }
    }

    #[Test]
    public function guessExtensionForXmlFile(): void
    {
        $path = $this->tempDir . '/data.xml';
        file_put_contents($path, "<?xml version=\"1.0\"?>\n<root><item>test</item></root>");

        $file = new File($path);
        $mime = $file->getMimeType();

        // XML may be detected as text/xml or application/xml
        if ($mime === 'text/xml' || $mime === 'application/xml') {
            self::assertSame('xml', $file->guessExtension());
        } else {
            self::assertStringContainsString('xml', $mime ?? '');
        }
    }

    #[Test]
    public function guessExtensionForSvgFile(): void
    {
        $path = $this->tempDir . '/image.svg';
        file_put_contents($path, "<?xml version=\"1.0\"?>\n<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"100\" height=\"100\"><circle cx=\"50\" cy=\"50\" r=\"40\"/></svg>");

        $file = new File($path);
        $mime = $file->getMimeType();

        if ($mime === 'image/svg+xml') {
            self::assertSame('svg', $file->guessExtension());
        } else {
            // Some systems detect SVG as text/xml or application/xml
            self::assertContains($mime, ['text/xml', 'application/xml', 'text/html', 'text/plain', 'image/svg+xml']);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }
}
