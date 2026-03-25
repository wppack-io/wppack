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

namespace WpPack\Component\Wpress\ContentProcessor;

use WpPack\Component\Wpress\Exception\EncryptionException;

final class EncryptedContentProcessor implements ContentProcessorInterface
{
    private const CIPHER = 'aes-256-cbc';
    private const IV_SIZE = 16;
    private const CHUNK_SIZE = 524288; // 512KB

    private readonly string $key;

    public function __construct(string $password)
    {
        $this->key = substr(sha1($password, true), 0, self::IV_SIZE);
    }

    public function decode(string $data): string
    {
        $offset = 0;
        $length = \strlen($data);
        $result = '';

        // Each encrypted chunk: IV(16) + encrypted_data
        // Full plaintext chunk = 512KB → encrypted = 524304 bytes (PKCS7 adds 16 bytes)
        // So full encrypted chunk in stream = 16 + 524304 = 524320 bytes
        $fullEncryptedChunkSize = self::IV_SIZE + self::CHUNK_SIZE + self::IV_SIZE; // 524320

        while ($offset < $length) {
            if ($length - $offset < self::IV_SIZE) {
                throw new EncryptionException('Encrypted data is truncated: insufficient bytes for IV.');
            }

            $iv = substr($data, $offset, self::IV_SIZE);
            $offset += self::IV_SIZE;

            $remaining = $length - $offset;

            // Determine encrypted data size for this chunk
            if ($remaining + self::IV_SIZE >= $fullEncryptedChunkSize && ($length - $offset - (self::CHUNK_SIZE + self::IV_SIZE)) >= 0) {
                // Full chunk: encrypted size = CHUNK_SIZE + IV_SIZE (PKCS7 padding)
                $encryptedSize = self::CHUNK_SIZE + self::IV_SIZE;
            } else {
                // Last chunk: take all remaining
                $encryptedSize = $remaining;
            }

            $encrypted = substr($data, $offset, $encryptedSize);
            $offset += $encryptedSize;

            $decrypted = openssl_decrypt($encrypted, self::CIPHER, $this->key, \OPENSSL_RAW_DATA, $iv);

            if ($decrypted === false) {
                throw new EncryptionException('Failed to decrypt data. The password may be incorrect.');
            }

            $result .= $decrypted;
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

            $iv = openssl_random_pseudo_bytes(self::IV_SIZE);
            $encrypted = openssl_encrypt($chunk, self::CIPHER, $this->key, \OPENSSL_RAW_DATA, $iv);

            if ($encrypted === false) {
                throw new EncryptionException('Failed to encrypt data.');
            }

            $result .= $iv . $encrypted;
        }

        return $result;
    }
}
