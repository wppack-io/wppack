<?php

declare(strict_types=1);

namespace WpPack\Component\Wpress\ContentProcessor;

use WpPack\Component\Wpress\Exception\ArchiveException;

final class CompressedContentProcessor implements ContentProcessorInterface
{
    private const CHUNK_SIZE = 524288; // 512KB
    private const SIZE_HEADER_LENGTH = 4;
    private const COMPRESSION_LEVEL = 9;

    public function __construct(
        private readonly string $type = 'gzip',
    ) {
        if (!\in_array($this->type, ['gzip', 'bzip2'], true)) {
            throw new ArchiveException(\sprintf('Unsupported compression type: %s', $this->type));
        }

        if ($this->type === 'bzip2' && !\function_exists('bzcompress')) {
            throw new ArchiveException('bzip2 extension is required for bzip2 compression.');
        }
    }

    public function decode(string $data): string
    {
        $offset = 0;
        $length = \strlen($data);
        $result = '';

        while ($offset < $length) {
            if ($length - $offset < self::SIZE_HEADER_LENGTH) {
                throw new ArchiveException('Compressed data is truncated: insufficient bytes for size header.');
            }

            $sizeData = unpack('N', substr($data, $offset, self::SIZE_HEADER_LENGTH));
            $chunkSize = $sizeData[1];
            $offset += self::SIZE_HEADER_LENGTH;

            if ($length - $offset < $chunkSize) {
                throw new ArchiveException('Compressed data is truncated: insufficient bytes for chunk data.');
            }

            $compressed = substr($data, $offset, $chunkSize);
            $offset += $chunkSize;

            $decompressed = $this->decompress($compressed);
            $result .= $decompressed;
        }

        return $result;
    }

    public function encode(string $data): string
    {
        $offset = 0;
        $length = \strlen($data);
        $result = '';

        while ($offset < $length) {
            $chunk = substr($data, $offset, self::CHUNK_SIZE);
            $offset += \strlen($chunk);

            $compressed = $this->compress($chunk);

            $result .= pack('N', \strlen($compressed)) . $compressed;
        }

        return $result;
    }

    private function compress(string $data): string
    {
        if ($this->type === 'gzip') {
            $result = gzcompress($data, self::COMPRESSION_LEVEL);
        } else {
            $result = bzcompress($data, self::COMPRESSION_LEVEL);
        }

        if ($result === false || \is_int($result)) {
            throw new ArchiveException(\sprintf('Failed to compress data with %s.', $this->type));
        }

        return $result;
    }

    private function decompress(string $data): string
    {
        if ($this->type === 'gzip') {
            $result = gzuncompress($data);
        } else {
            $result = bzdecompress($data);
        }

        if ($result === false || \is_int($result)) {
            throw new ArchiveException(\sprintf('Failed to decompress data with %s.', $this->type));
        }

        return $result;
    }
}
