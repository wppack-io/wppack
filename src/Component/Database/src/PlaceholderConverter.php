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
 * Mixing both styles in the same query raises InvalidArgumentException.
 */
final class PlaceholderConverter
{
    /**
     * @param list<mixed> $params
     *
     * @return array{string, list<mixed>}
     *
     * @throws \InvalidArgumentException when a query mixes ? and %s/%d/%f placeholders
     */
    public static function convert(string $query, array $params): array
    {
        // Strip string literals before checking for ? to avoid false positives
        $stripped = (string) preg_replace('/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"/', '', $query);

        $hasQuestionMark = str_contains($stripped, '?');
        $hasPercentPlaceholder = (bool) preg_match('/%[sdf]/', $query);

        // Reject mixed styles — silent passthrough would produce invalid SQL at the DB layer
        if ($hasQuestionMark && $hasPercentPlaceholder) {
            throw new \InvalidArgumentException(
                'Query contains both ? and %s/%d/%f placeholders. Use one placeholder style consistently.',
            );
        }

        // Already uses ? placeholders or has no placeholders — pass through
        if ($hasQuestionMark || !$hasPercentPlaceholder) {
            return [$query, $params];
        }

        // Convert %s/%d/%f to ?
        $sql = (string) preg_replace('/%%/', "\x00LITERAL_PERCENT\x00", $query);
        $sql = (string) preg_replace('/%[sdf]/', '?', $sql);
        $sql = str_replace("\x00LITERAL_PERCENT\x00", '%%', $sql);

        return [$sql, $params];
    }
}
