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

namespace WPPack\Component\Database\Sql;

/**
 * Quote-aware scanner for '?' placeholders in a SQL string.
 *
 * Walks the SQL one byte at a time, tracking single-quoted string literal
 * boundaries (including the two common escape forms '' and \'), and invokes
 * the user-supplied replacer for every '?' encountered *outside* a string
 * literal. Placeholders *inside* a literal are preserved verbatim, matching
 * how the database engine would treat them.
 *
 * Used by:
 *   - PostgreSQLDriver to rewrite '?' into PostgreSQL's '$1, $2, ...' form.
 *   - WPPackWpdb::interpolateForDisplay() to render a human-readable SQL
 *     for SAVEQUERIES logs by substituting each '?' with its bound value.
 */
final class PlaceholderScanner
{
    /**
     * Replace '?' placeholders (outside single-quoted literals) in $sql.
     *
     * $replacer is called for every matching placeholder with the zero-based
     * placeholder index and must return the replacement string.
     *
     * @param callable(int): string $replacer
     */
    public static function replace(string $sql, callable $replacer): string
    {
        $out = '';
        $inQuote = false;
        $index = 0;
        $length = \strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $c = $sql[$i];

            if ($c === "'") {
                if ($inQuote && ($sql[$i + 1] ?? '') === "'") {
                    // '' is a doubled-quote escape — stays inside the literal.
                    $out .= "''";
                    $i++;

                    continue;
                }

                $inQuote = !$inQuote;
                $out .= $c;

                continue;
            }

            if ($c === '\\' && $inQuote && $i + 1 < $length) {
                // Backslash escape inside a literal (MySQL default) — consume
                // the next byte verbatim.
                $out .= $c . $sql[$i + 1];
                $i++;

                continue;
            }

            if ($c === '?' && !$inQuote) {
                $out .= $replacer($index++);

                continue;
            }

            $out .= $c;
        }

        return $out;
    }
}
