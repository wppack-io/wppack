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

namespace WpPack\Component\HttpClient\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use WpPack\Component\HttpClient\RequestFactory;

final class RequestFactoryTest extends TestCase
{
    private RequestFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new RequestFactory();
    }

    #[Test]
    public function createRequest(): void
    {
        $request = $this->factory->createRequest('GET', 'https://example.com/path');

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://example.com/path', (string) $request->getUri());
    }

    #[Test]
    public function createRequestWithUriObject(): void
    {
        $uri = $this->factory->createUri('https://example.com');
        $request = $this->factory->createRequest('POST', $uri);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://example.com', (string) $request->getUri());
    }

    #[Test]
    public function createStream(): void
    {
        $stream = $this->factory->createStream('hello');

        self::assertInstanceOf(StreamInterface::class, $stream);
        self::assertSame('hello', (string) $stream);
    }

    #[Test]
    public function createStreamEmpty(): void
    {
        $stream = $this->factory->createStream();

        self::assertSame('', (string) $stream);
    }

    #[Test]
    public function createStreamFromFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'wppack_test_');
        file_put_contents($file, 'file content');

        try {
            $stream = $this->factory->createStreamFromFile($file);

            self::assertInstanceOf(StreamInterface::class, $stream);
            self::assertSame('file content', (string) $stream);
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function createStreamFromFileThrowsOnInvalidPath(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->factory->createStreamFromFile('/nonexistent/file.txt');
    }

    #[Test]
    public function createStreamFromResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'resource content');
        rewind($resource);

        $stream = $this->factory->createStreamFromResource($resource);

        self::assertInstanceOf(StreamInterface::class, $stream);
        self::assertSame('resource content', (string) $stream);
    }

    #[Test]
    public function createStreamFromResourceThrowsOnInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->createStreamFromResource('not a resource');
    }

    #[Test]
    public function createUri(): void
    {
        $uri = $this->factory->createUri('https://example.com/path?q=1');

        self::assertInstanceOf(UriInterface::class, $uri);
        self::assertSame('https', $uri->getScheme());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame('/path', $uri->getPath());
        self::assertSame('q=1', $uri->getQuery());
    }

    #[Test]
    public function createUriEmpty(): void
    {
        $uri = $this->factory->createUri();

        self::assertSame('', (string) $uri);
    }
}
