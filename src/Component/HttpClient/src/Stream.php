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

namespace WpPack\Component\HttpClient;

use Psr\Http\Message\StreamInterface;

final class Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;

    private bool $readable;

    private bool $writable;

    private bool $seekable;

    /**
     * @param string|resource $body
     */
    public function __construct(mixed $body = '')
    {
        if (\is_string($body)) {
            $resource = fopen('php://temp', 'r+');
            if ($resource === false) {
                throw new \RuntimeException('Unable to open php://temp stream.');
            }
            fwrite($resource, $body);
            rewind($resource);
            $this->resource = $resource;
        } elseif (\is_resource($body)) {
            $this->resource = $body;
        } else {
            throw new \InvalidArgumentException('Stream body must be a string or resource.');
        }

        $meta = stream_get_meta_data($this->resource);
        $mode = $meta['mode'];
        $this->readable = str_contains($mode, 'r') || str_contains($mode, '+');
        $this->writable = str_contains($mode, 'w') || str_contains($mode, 'a') || str_contains($mode, 'x') || str_contains($mode, 'c') || str_contains($mode, '+');
        $this->seekable = $meta['seekable'];
    }

    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (\Throwable) {
            return '';
        }
    }

    public function close(): void
    {
        if ($this->resource !== null) {
            fclose($this->resource);
            $this->resource = null;
        }
    }

    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;

        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }

        $stats = fstat($this->resource);

        return $stats !== false ? $stats['size'] : null;
    }

    public function tell(): int
    {
        if ($this->resource === null) {
            throw new \RuntimeException('Stream is detached.');
        }

        $position = ftell($this->resource);
        if ($position === false) {
            throw new \RuntimeException('Unable to determine stream position.');
        }

        return $position;
    }

    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->resource !== null && $this->seekable;
    }

    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new \RuntimeException('Stream is not seekable.');
        }

        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new \RuntimeException('Unable to seek to stream position.');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->resource !== null && $this->writable;
    }

    public function write(string $string): int
    {
        if (!$this->isWritable()) {
            throw new \RuntimeException('Stream is not writable.');
        }

        $result = fwrite($this->resource, $string);
        if ($result === false) {
            throw new \RuntimeException('Unable to write to stream.');
        }

        return $result;
    }

    public function isReadable(): bool
    {
        return $this->resource !== null && $this->readable;
    }

    public function read(int $length): string
    {
        if (!$this->isReadable()) {
            throw new \RuntimeException('Stream is not readable.');
        }

        $result = fread($this->resource, $length);
        if ($result === false) {
            throw new \RuntimeException('Unable to read from stream.');
        }

        return $result;
    }

    public function getContents(): string
    {
        if (!$this->isReadable()) {
            throw new \RuntimeException('Stream is not readable.');
        }

        $contents = stream_get_contents($this->resource);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read stream contents.');
        }

        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($this->resource === null) {
            return $key === null ? [] : null;
        }

        $meta = stream_get_meta_data($this->resource);

        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }
}
