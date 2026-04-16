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

namespace WpPack\Component\Database;

/**
 * Converts WordPress-style placeholders (%s, %d, %f) to native ? placeholders.
 *
 * If the query already uses ? placeholders, it passes through unchanged.
 * Literal %% is preserved.
 */
final class PlaceholderConverter
{
    /**
     * @param list<mixed> $params
     *
     * @return array{string, list<mixed>}
     */
    public static function convert(string $query, array $params): array
    {
        // Strip string literals before checking for ? to avoid false positives
        $stripped = (string) preg_replace('/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"/', '', $query);

        // Already uses ? placeholders — pass through
        if (str_contains($stripped, '?') || !preg_match('/%[sdf]/', $query)) {
            return [$query, $params];
        }

        // Convert %s/%d/%f to ?
        $sql = (string) preg_replace('/%%/', "\x00LITERAL_PERCENT\x00", $query);
        $sql = (string) preg_replace('/%[sdf]/', '?', $sql);
        $sql = str_replace("\x00LITERAL_PERCENT\x00", '%%', $sql);

        return [$sql, $params];
    }
}
