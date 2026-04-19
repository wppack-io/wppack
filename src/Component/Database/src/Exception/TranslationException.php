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

namespace WPPack\Component\Database\Exception;

/**
 * Raised when the query translator cannot safely convert a MySQL-dialect
 * query to the target engine's dialect.
 *
 * Production code should never swallow this: silent pass-through on a
 * parser failure risks executing subtly different semantics on the target
 * engine (e.g. MariaDB optimizer hints, vendor-specific DDL). WPPackWpdb
 * catches this and surfaces the failure through $wpdb->last_error so the
 * caller can detect + retry with a simpler query.
 */
class TranslationException extends \RuntimeException implements ExceptionInterface
{
    private const MAX_QUERY_LENGTH = 200;

    /**
     * @param list<string> $parserErrors
     */
    public function __construct(
        public readonly string $query,
        public readonly string $engine,
        public readonly array $parserErrors = [],
        ?\Throwable $previous = null,
    ) {
        $truncated = mb_strlen($query) > self::MAX_QUERY_LENGTH
            ? mb_substr($query, 0, self::MAX_QUERY_LENGTH) . '...'
            : $query;

        $detail = $parserErrors === []
            ? 'parser returned no statement'
            : 'parser errors: ' . implode('; ', array_slice($parserErrors, 0, 3));

        parent::__construct(
            \sprintf('Query translation to %s failed (%s) [Query: %s]', $engine, $detail, $truncated),
            0,
            $previous,
        );
    }
}
