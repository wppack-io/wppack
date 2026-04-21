<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Wpress\ContentProcessor;

use WPPack\Component\Wpress\Exception\ArchiveException;
use WPPack\Component\Wpress\Exception\EncryptionException;

/**
 * Handles combined compression + encryption.
 *
 * Export order: compress → encrypt → prepend size header
 * Import order: read size header → decrypt → decompress
 */
final class ChainContentProcessor implements ContentProcessorInterface
{
    private const CIPHER = 'aes-256-cbc';
    private const IV_SIZE = 16;
    private const CHUNK_SIZE = 524288; // 512KB
    private const SIZE_HEADER_LENGTH = 4;
    private const COMPRESSION_LEVEL = 9;

    private readonly string $key;

    public function __construct(
        private readonly string $password,
        private readonly string $compressionType = 'gzip',
    ) {
        if (!\in_array($this->compressionType, ['gzip', 'bzip2'], true)) {
            throw new ArchiveException(\sprintf('Unsupported compression type: %s', $this->compressionType));
        }

        if ($this->compressionType === 'bzip2' && !\function_exists('bzcompress')) {
            throw new ArchiveException('bzip2 extension is required for bzip2 compression.');
        }

        $this->key = substr(sha1($this->password, true), 0, self::IV_SIZE);
    }

    public function decode(string $data): string
    {
        $offset = 0;
        $length = \strlen($data);
        $result = '';

        while ($offset < $length) {
            // Read size header (4 bytes, big-endian unsigned 32-bit)
            if ($length - $offset < self::SIZE_HEADER_LENGTH) {
                throw new ArchiveException('Chain data is truncated: insufficient bytes for size header.');
            }

            $sizeData = unpack('N', substr($data, $offset, self::SIZE_HEADER_LENGTH));
            if ($sizeData === false) {
                throw new ArchiveException('Chain data is truncated: failed to unpack size header.');
            }
            $chunkSize = $sizeData[1];
            $offset += self::SIZE_HEADER_LENGTH;

            if ($length - $offset < $chunkSize) {
                throw new ArchiveException('Chain data is truncated: insufficient bytes for chunk data.');
            }

            // Read IV (16 bytes)
            $iv = substr($data, $offset, self::IV_SIZE);
            $offset += self::IV_SIZE;

            // Read encrypted compressed data
            $encrypted = substr($data, $offset, $chunkSize - self::IV_SIZE);
            $offset += $chunkSize - self::IV_SIZE;

            // Decrypt
            $compressed = openssl_decrypt($encrypted, self::CIPHER, $this->key, \OPENSSL_RAW_DATA, $iv);

            if ($compressed === false) {
                throw new EncryptionException('Failed to decrypt data. The password may be incorrect.');
            }

            // Decompress — if decompression fails after decryption, the password is incorrect
            try {
                $result .= $this->decompress($compressed);
            } catch (ArchiveException) {
                throw new EncryptionException('Failed to decrypt data. The password may be incorrect.');
            }
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

            // Compress
            $compressed = $this->compress($chunk);

            // Encrypt
            $iv = openssl_random_pseudo_bytes(self::IV_SIZE);
            $encrypted = openssl_encrypt($compressed, self::CIPHER, $this->key, \OPENSSL_RAW_DATA, $iv);

            if ($encrypted === false) {
                throw new EncryptionException('Failed to encrypt data.');
            }

            // Prepend size header: size = len(IV + encrypted)
            $chunkData = $iv . $encrypted;
            $result .= pack('N', \strlen($chunkData)) . $chunkData;
        }

        return $result;
    }

    private function compress(string $data): string
    {
        if ($this->compressionType === 'gzip') {
            $result = gzcompress($data, self::COMPRESSION_LEVEL);
        } else {
            $result = bzcompress($data, self::COMPRESSION_LEVEL);
        }

        if ($result === false || \is_int($result)) {
            throw new ArchiveException(\sprintf('Failed to compress data with %s.', $this->compressionType));
        }

        return $result;
    }

    private function decompress(string $data): string
    {
        if ($this->compressionType === 'gzip') {
            $result = gzuncompress($data);
        } else {
            $result = bzdecompress($data);
        }

        if ($result === false || \is_int($result)) {
            throw new ArchiveException(\sprintf('Failed to decompress data with %s.', $this->compressionType));
        }

        return $result;
    }
}
