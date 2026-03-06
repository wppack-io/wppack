<?php

declare(strict_types=1);

namespace WpPack\Component\Database\Exception;

final class QueryException extends \RuntimeException implements ExceptionInterface
{
    private const MAX_QUERY_LENGTH = 200;

    public function __construct(
        public readonly string $query,
        public readonly string $dbError,
        ?\Throwable $previous = null,
    ) {
        $truncatedQuery = mb_strlen($query) > self::MAX_QUERY_LENGTH
            ? mb_substr($query, 0, self::MAX_QUERY_LENGTH) . '...'
            : $query;

        parent::__construct(
            sprintf('Database query failed: %s [Query: %s]', $dbError, $truncatedQuery),
            0,
            $previous,
        );
    }
}
