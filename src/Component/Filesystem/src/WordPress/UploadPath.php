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

namespace WpPack\Component\Filesystem\WordPress;

/**
 * DI-injectable wrapper around wp_upload_dir().
 */
final class UploadPath
{
    public function getBasePath(): string
    {
        return $this->uploadDir()['basedir'];
    }

    public function getBaseUrl(): string
    {
        return $this->uploadDir()['baseurl'];
    }

    public function getCurrentPath(): string
    {
        return $this->uploadDir()['path'];
    }

    public function getCurrentUrl(): string
    {
        return $this->uploadDir()['url'];
    }

    /**
     * Returns a sub-directory path under the base upload directory.
     * Creates the directory if it does not exist.
     */
    public function subdir(string $name): string
    {
        $path = $this->getBasePath() . '/' . $name;

        if (!is_dir($path)) {
            wp_mkdir_p($path);
        }

        return $path;
    }

    /**
     * @return array{path: string, url: string, subdir: string, basedir: string, baseurl: string, error: string|false}
     */
    private function uploadDir(): array
    {
        return wp_upload_dir();
    }
}
