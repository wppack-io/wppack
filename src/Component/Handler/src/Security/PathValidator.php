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

namespace WpPack\Component\Handler\Security;

use WpPack\Component\Handler\Exception\SecurityException;

class PathValidator
{
    private readonly string $webRoot;

    public function __construct(
        string $webRoot,
        private readonly bool $checkSymlinks = true,
    ) {
        $realPath = realpath($webRoot);
        if ($realPath === false) {
            throw new \InvalidArgumentException(\sprintf('Invalid web root path: %s', $webRoot));
        }
        $this->webRoot = $realPath;
    }

    /**
     * @throws SecurityException
     */
    public function validate(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new SecurityException('Null byte detected in path');
        }

        $patterns = [
            '../', '..\\',
            '%2e%2e/', '%2e%2e\\',
            '%252e%252e%252f', '%252e%252e%255c',
        ];
        foreach ($patterns as $pattern) {
            if (stripos($path, $pattern) !== false) {
                throw new SecurityException('Directory traversal attempt detected');
            }
        }

        if (preg_match('/[\x00-\x1f\x7f]/', $path)) {
            throw new SecurityException('Invalid characters in path');
        }

        return $path;
    }

    /**
     * @throws SecurityException
     */
    public function validateFilePath(string $filePath): string
    {
        if (!$this->checkSymlinks) {
            $normalizedPath = $this->normalizePath($filePath);
            $normalizedWebRoot = $this->normalizePath($this->webRoot);

            if (!str_starts_with($normalizedPath, $normalizedWebRoot)) {
                throw new SecurityException('Path outside web root');
            }

            return $filePath;
        }

        $realPath = realpath($filePath);

        if ($realPath === false) {
            $dir = \dirname($filePath);
            $realDir = realpath($dir);
            if ($realDir === false || !str_starts_with($realDir, $this->webRoot)) {
                throw new SecurityException('Path outside web root');
            }

            return $filePath;
        }

        if (!str_starts_with($realPath, $this->webRoot)) {
            throw new SecurityException('Path outside web root');
        }

        return $realPath;
    }

    public function isHiddenPath(string $path): bool
    {
        $parts = explode('/', trim($path, '/'));
        foreach ($parts as $part) {
            if ($part !== '' && $part[0] === '.' && $part !== '.' && $part !== '..') {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
