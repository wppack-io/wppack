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

namespace WpPack\Component\Wpress;

use WpPack\Component\Wpress\ContentProcessor\ContentProcessorInterface;
use WpPack\Component\Wpress\Exception\ArchiveException;

final class WpressEntry
{
    /**
     * @param resource $handle
     */
    public function __construct(
        private readonly Header $header,
        private $handle,
        private readonly int $contentOffset,
        private readonly ContentProcessorInterface $contentProcessor,
    ) {}

    public function getPath(): string
    {
        return $this->header->getPath();
    }

    public function getName(): string
    {
        return $this->header->name;
    }

    public function getPrefix(): string
    {
        return $this->header->prefix;
    }

    public function getSize(): int
    {
        return $this->header->size;
    }

    public function getMTime(): int
    {
        return $this->header->mtime;
    }

    public function getContents(): string
    {
        if ($this->header->size === 0) {
            return '';
        }

        fseek($this->handle, $this->contentOffset);

        $data = '';
        $remaining = $this->header->size;
        $chunkSize = 8192;

        while ($remaining > 0) {
            $readSize = min($chunkSize, $remaining);
            $chunk = fread($this->handle, $readSize);

            if ($chunk === false) {
                throw new ArchiveException('Failed to read entry content.');
            }

            $data .= $chunk;
            $remaining -= \strlen($chunk);
        }

        return $this->contentProcessor->decode($data);
    }

    /**
     * @return resource
     */
    public function getStream()
    {
        $decoded = $this->getContents();

        $stream = fopen('php://temp', 'r+b');

        if ($stream === false) {
            throw new ArchiveException('Failed to create temporary stream.');
        }

        fwrite($stream, $decoded);
        rewind($stream);

        return $stream;
    }
}
