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

namespace WpPack\Component\Kernel;

trait ManagesDropin
{
    abstract private function getDropinFilename(): string;

    abstract private function getDropinSignature(): string;

    abstract private function resolveDropinSource(): ?string;

    protected function installDropin(): void
    {
        $destination = WP_CONTENT_DIR . '/' . $this->getDropinFilename();

        if (file_exists($destination) || is_link($destination) || !is_writable(WP_CONTENT_DIR)) {
            return;
        }

        $source = $this->resolveDropinSource();

        if ($source === null || !file_exists($source)) {
            return;
        }

        // Prefer symlink (source changes reflected immediately), fall back to copy
        $realSource = realpath($source);

        if ($realSource === false || !@symlink($realSource, $destination)) {
            copy($source, $destination);
        }
    }

    protected function uninstallDropin(): void
    {
        $destination = WP_CONTENT_DIR . '/' . $this->getDropinFilename();

        if (is_link($destination)) {
            $this->uninstallSymlink($destination);

            return;
        }

        if (!file_exists($destination) || !is_writable($destination)) {
            return;
        }

        $header = file_get_contents($destination, false, null, 0, 512);

        if ($header !== false && str_contains($header, $this->getDropinSignature())) {
            unlink($destination);
        }
    }

    private function uninstallSymlink(string $destination): void
    {
        $linkTarget = readlink($destination);
        $source = $this->resolveDropinSource();

        if ($linkTarget === false || $source === null) {
            return;
        }

        // Compare resolved paths (handles both valid and broken symlinks)
        $resolvedTarget = realpath($linkTarget) ?: $linkTarget;
        $resolvedSource = realpath($source) ?: $source;

        if ($resolvedTarget === $resolvedSource) {
            unlink($destination);
        }
    }
}
