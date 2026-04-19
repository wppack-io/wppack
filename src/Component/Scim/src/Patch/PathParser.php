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

namespace WPPack\Component\Scim\Patch;

use WPPack\Component\Scim\Exception\InvalidPatchException;

final class PathParser
{
    private function __construct() {}

    /**
     * Parse a SCIM attribute path into segments.
     *
     * Examples:
     *   "userName" → ["userName"]
     *   "name.givenName" → ["name", "givenName"]
     *   "emails[type eq \"work\"].value" → ["emails", "value"] (filter ignored for simplicity)
     *
     * @return list<string>
     */
    public static function parse(string $path): array
    {
        if ($path === '') {
            throw new InvalidPatchException('Empty attribute path.', 'invalidPath');
        }

        // Strip value filter expressions like [type eq "work"]
        $cleaned = preg_replace('/\[.*?\]/', '', $path);
        if ($cleaned === null || $cleaned === '') {
            throw new InvalidPatchException(sprintf('Invalid attribute path: "%s".', $path), 'invalidPath');
        }

        $segments = array_values(array_filter(explode('.', $cleaned), static fn(string $s): bool => $s !== ''));

        if ($segments === []) {
            throw new InvalidPatchException(sprintf('Invalid attribute path: "%s".', $path), 'invalidPath');
        }

        return $segments;
    }

    /**
     * Set a value at the given path in an associative array.
     *
     * @param array<string, mixed> $data
     * @param list<string> $segments
     *
     * @return array<string, mixed>
     */
    public static function setValueAtPath(array $data, array $segments, mixed $value): array
    {
        if ($segments === []) {
            return $data;
        }

        $key = $segments[0];

        if (\count($segments) === 1) {
            $data[$key] = $value;

            return $data;
        }

        $nested = $data[$key] ?? [];
        if (!\is_array($nested)) {
            $nested = [];
        }

        $data[$key] = self::setValueAtPath($nested, \array_slice($segments, 1), $value);

        return $data;
    }

    /**
     * Remove a value at the given path in an associative array.
     *
     * @param array<string, mixed> $data
     * @param list<string> $segments
     *
     * @return array<string, mixed>
     */
    public static function removeValueAtPath(array $data, array $segments): array
    {
        if ($segments === []) {
            return $data;
        }

        $key = $segments[0];

        if (\count($segments) === 1) {
            unset($data[$key]);

            return $data;
        }

        if (isset($data[$key]) && \is_array($data[$key])) {
            $data[$key] = self::removeValueAtPath($data[$key], \array_slice($segments, 1));
        }

        return $data;
    }
}
