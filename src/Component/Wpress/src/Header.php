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

use WpPack\Component\Wpress\Exception\InvalidArgumentException;

/**
 * @internal
 */
final class Header
{
    public const SIZE = 4377;

    private const NAME_SIZE = 255;
    private const PREFIX_SIZE = 4096;

    private const PACK_FORMAT = 'a255a14a12a4096';
    private const UNPACK_FORMAT = 'a255name/a14size/a12mtime/a4096prefix';

    public function __construct(
        public readonly string $name,
        public readonly int $size,
        public readonly int $mtime,
        public readonly string $prefix,
    ) {}

    public static function fromBinary(string $data): self
    {
        if (\strlen($data) !== self::SIZE) {
            throw new InvalidArgumentException(\sprintf(
                'Header data must be exactly %d bytes, got %d.',
                self::SIZE,
                \strlen($data),
            ));
        }

        $fields = unpack(self::UNPACK_FORMAT, $data);

        return new self(
            name: rtrim($fields['name'], "\0"),
            size: (int) rtrim($fields['size'], "\0"),
            mtime: (int) rtrim($fields['mtime'], "\0"),
            prefix: rtrim($fields['prefix'], "\0"),
        );
    }

    public function toBinary(): string
    {
        if ($this->isEof()) {
            return str_repeat("\0", self::SIZE);
        }

        return pack(
            self::PACK_FORMAT,
            $this->name,
            (string) $this->size,
            (string) $this->mtime,
            $this->prefix,
        );
    }

    public function isEof(): bool
    {
        return $this->name === '' && $this->size === 0 && $this->mtime === 0 && $this->prefix === '';
    }

    public function getPath(): string
    {
        if ($this->prefix === '' || $this->prefix === '.') {
            return $this->name;
        }

        return $this->prefix . '/' . $this->name;
    }

    public static function eof(): self
    {
        return new self(name: '', size: 0, mtime: 0, prefix: '');
    }

    public static function fromPath(string $archivePath, int $size, ?int $mtime = null): self
    {
        $lastSlash = strrpos($archivePath, '/');

        if ($lastSlash === false) {
            $name = $archivePath;
            $prefix = '.';
        } else {
            $name = substr($archivePath, $lastSlash + 1);
            $prefix = substr($archivePath, 0, $lastSlash);
        }

        if (\strlen($name) > self::NAME_SIZE) {
            throw new InvalidArgumentException(\sprintf(
                'File name must not exceed %d bytes, got %d.',
                self::NAME_SIZE,
                \strlen($name),
            ));
        }

        if (\strlen($prefix) > self::PREFIX_SIZE) {
            throw new InvalidArgumentException(\sprintf(
                'Prefix must not exceed %d bytes, got %d.',
                self::PREFIX_SIZE,
                \strlen($prefix),
            ));
        }

        return new self(
            name: $name,
            size: $size,
            mtime: $mtime ?? time(),
            prefix: $prefix,
        );
    }
}
