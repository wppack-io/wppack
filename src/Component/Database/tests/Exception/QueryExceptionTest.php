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

namespace WpPack\Component\Database\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Exception\ExceptionInterface;
use WpPack\Component\Database\Exception\QueryException;

#[CoversClass(QueryException::class)]
final class QueryExceptionTest extends TestCase
{
    #[Test]
    public function implementsExceptionInterface(): void
    {
        $exception = new QueryException('SELECT 1', 'some error');

        self::assertInstanceOf(ExceptionInterface::class, $exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function exposesQueryAndDbError(): void
    {
        $exception = new QueryException('SELECT * FROM wp_posts', 'Table not found');

        self::assertSame('SELECT * FROM wp_posts', $exception->query);
        self::assertSame('Table not found', $exception->dbError);
    }

    #[Test]
    public function messageContainsErrorAndQuery(): void
    {
        $exception = new QueryException('SELECT * FROM wp_posts', 'Table not found');

        self::assertStringContainsString('Table not found', $exception->getMessage());
        self::assertStringContainsString('SELECT * FROM wp_posts', $exception->getMessage());
    }

    #[Test]
    public function truncatesLongQueries(): void
    {
        $longQuery = 'SELECT ' . str_repeat('x', 300) . ' FROM wp_posts';
        $exception = new QueryException($longQuery, 'error');

        self::assertStringContainsString('...', $exception->getMessage());
        self::assertSame($longQuery, $exception->query);
    }

    #[Test]
    public function acceptsPreviousException(): void
    {
        $previous = new \RuntimeException('previous');
        $exception = new QueryException('SELECT 1', 'error', $previous);

        self::assertSame($previous, $exception->getPrevious());
    }
}
