<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\RequestDataCollector;

final class RequestDataCollectorTest extends TestCase
{
    private RequestDataCollector $collector;

    /** @var array<string, mixed> */
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->collector = new RequestDataCollector();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    #[Test]
    public function getNameReturnsRequest(): void
    {
        self::assertSame('request', $this->collector->getName());
    }

    #[Test]
    public function collectGathersServerData(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/test-page';

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('POST', $data['method']);
        self::assertArrayHasKey('url', $data);
        self::assertArrayHasKey('status_code', $data);
        self::assertArrayHasKey('request_headers', $data);
        self::assertArrayHasKey('response_headers', $data);
        self::assertArrayHasKey('server_vars', $data);
    }

    #[Test]
    public function getBadgeValueReturnsMethodAndStatus(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->collector->collect();

        self::assertSame('GET 200', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsGreenFor200Status(): void
    {
        $this->collector->collect();

        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsYellowFor300Status(): void
    {
        // Use captureStatusCode to set a 301 status
        $this->collector->captureStatusCode('HTTP/1.1 301 Moved Permanently', 301);
        $this->collector->collect();

        self::assertSame('yellow', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedFor400Status(): void
    {
        $this->collector->captureStatusCode('HTTP/1.1 404 Not Found', 404);
        $this->collector->collect();

        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->collect();
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }
}
