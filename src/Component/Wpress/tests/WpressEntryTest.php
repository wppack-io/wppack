<?php

declare(strict_types=1);

namespace WpPack\Component\Wpress\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Wpress\ContentProcessor\PlainContentProcessor;
use WpPack\Component\Wpress\Header;
use WpPack\Component\Wpress\WpressEntry;

final class WpressEntryTest extends TestCase
{
    #[Test]
    public function gettersReturnHeaderValues(): void
    {
        $handle = $this->createEntryHandle('Hello, World!');
        $header = new Header(name: 'test.txt', size: 13, mtime: 1706140800, prefix: 'data');

        $entry = new WpressEntry($header, $handle, 0, new PlainContentProcessor());

        self::assertSame('data/test.txt', $entry->getPath());
        self::assertSame('test.txt', $entry->getName());
        self::assertSame('data', $entry->getPrefix());
        self::assertSame(13, $entry->getSize());
        self::assertSame(1706140800, $entry->getMTime());

        fclose($handle);
    }

    #[Test]
    public function getContentsReturnsFileData(): void
    {
        $content = 'Hello, World!';
        $handle = $this->createEntryHandle($content);
        $header = new Header(name: 'test.txt', size: \strlen($content), mtime: 1000, prefix: '.');

        $entry = new WpressEntry($header, $handle, 0, new PlainContentProcessor());

        self::assertSame($content, $entry->getContents());

        fclose($handle);
    }

    #[Test]
    public function getContentsWithOffset(): void
    {
        $prefix = 'SKIPME';
        $content = 'Actual content';
        $handle = $this->createEntryHandle($prefix . $content);
        $header = new Header(name: 'test.txt', size: \strlen($content), mtime: 1000, prefix: '.');

        $entry = new WpressEntry($header, $handle, \strlen($prefix), new PlainContentProcessor());

        self::assertSame($content, $entry->getContents());

        fclose($handle);
    }

    #[Test]
    public function getContentsOfEmptyEntry(): void
    {
        $handle = $this->createEntryHandle('');
        $header = new Header(name: 'empty.txt', size: 0, mtime: 1000, prefix: '.');

        $entry = new WpressEntry($header, $handle, 0, new PlainContentProcessor());

        self::assertSame('', $entry->getContents());

        fclose($handle);
    }

    #[Test]
    public function getStreamReturnsReadableResource(): void
    {
        $content = 'Stream content test';
        $handle = $this->createEntryHandle($content);
        $header = new Header(name: 'stream.txt', size: \strlen($content), mtime: 1000, prefix: '.');

        $entry = new WpressEntry($header, $handle, 0, new PlainContentProcessor());

        $stream = $entry->getStream();

        self::assertIsResource($stream);
        self::assertSame($content, stream_get_contents($stream));

        fclose($stream);
        fclose($handle);
    }

    #[Test]
    public function getContentsCanBeCalledMultipleTimes(): void
    {
        $content = 'Repeatable read';
        $handle = $this->createEntryHandle($content);
        $header = new Header(name: 'test.txt', size: \strlen($content), mtime: 1000, prefix: '.');

        $entry = new WpressEntry($header, $handle, 0, new PlainContentProcessor());

        self::assertSame($content, $entry->getContents());
        self::assertSame($content, $entry->getContents());

        fclose($handle);
    }

    /**
     * @return resource
     */
    private function createEntryHandle(string $content)
    {
        $handle = fopen('php://temp', 'r+b');
        fwrite($handle, $content);
        rewind($handle);

        return $handle;
    }
}
