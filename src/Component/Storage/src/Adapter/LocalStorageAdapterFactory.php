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

namespace WpPack\Component\Storage\Adapter;

use WpPack\Component\Storage\Exception\InvalidArgumentException;

final class LocalStorageAdapterFactory implements StorageAdapterFactoryInterface
{
    public function create(Dsn $dsn, array $options = []): StorageAdapterInterface
    {
        $rootDir = $this->parseRootDir($dsn, $options);

        if ($rootDir === null) {
            throw new InvalidArgumentException('Cannot determine root directory from local storage DSN. Use "local:///path/to/storage" format.');
        }

        $publicUrl = $dsn->getOption('public_url') ?? $options['public_url'] ?? null;

        return new LocalStorageAdapter(
            rootDir: $rootDir,
            publicUrl: $publicUrl,
        );
    }

    public function supports(Dsn $dsn): bool
    {
        return $dsn->getScheme() === 'local';
    }

    /**
     * Parse root directory from DSN.
     *
     * Supported formats:
     *   local:///absolute/path    → /absolute/path
     *   local://./relative/path   → ./relative/path
     *   local://hostname/path     → host + path combined
     *
     * @param array<string, mixed> $options
     */
    private function parseRootDir(Dsn $dsn, array $options): ?string
    {
        $host = $dsn->getHost();
        $path = $dsn->getPath();

        // local:///absolute/path → host=null, path=/absolute/path
        if ($host === null && $path !== null) {
            return $path;
        }

        // local://./relative → host='.', path=/relative
        if ($host !== null && $path !== null) {
            return $host . $path;
        }

        // local://hostname → host=hostname, path=null
        if ($host !== null) {
            return $host;
        }

        return $options['root_dir'] ?? null;
    }
}
