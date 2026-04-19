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

namespace WPPack\Component\HttpClient\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use WPPack\Component\HttpClient\Exception\ConnectionException;
use WPPack\Component\HttpClient\Request;

#[CoversClass(ConnectionException::class)]
final class ConnectionExceptionTest extends TestCase
{
    #[Test]
    public function storesMessageAndRequest(): void
    {
        $request = new Request('GET', 'https://example.com');
        $exception = new ConnectionException('Connection failed', $request);

        self::assertSame('Connection failed', $exception->getMessage());
        self::assertSame($request, $exception->getRequest());
    }

    #[Test]
    public function storesPreviousException(): void
    {
        $request = new Request('GET', 'https://example.com');
        $previous = new \RuntimeException('DNS error');
        $exception = new ConnectionException('Connection failed', $request, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function implementsNetworkExceptionInterface(): void
    {
        $request = new Request('GET', 'https://example.com');
        $exception = new ConnectionException('Connection failed', $request);

        self::assertInstanceOf(NetworkExceptionInterface::class, $exception);
    }
}
