<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use WpPack\Component\HttpClient\Exception\ConnectionException;
use WpPack\Component\HttpClient\Request;

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
