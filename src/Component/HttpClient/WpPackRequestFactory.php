<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

final class WpPackRequestFactory implements RequestFactoryInterface, StreamFactoryInterface, UriFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new WpPackRequest($method, $uri instanceof UriInterface ? $uri : new WpPackUri($uri));
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return new Stream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        $resource = @fopen($filename, $mode);
        if ($resource === false) {
            throw new \RuntimeException(sprintf('Unable to open file "%s" with mode "%s".', $filename, $mode));
        }

        return new Stream($resource);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        if (!\is_resource($resource)) {
            throw new \InvalidArgumentException('Argument must be a valid PHP resource.');
        }

        return new Stream($resource);
    }

    public function createUri(string $uri = ''): UriInterface
    {
        return new WpPackUri($uri);
    }
}
