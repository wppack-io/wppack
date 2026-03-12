<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\RestDataCollector;

final class RestDataCollectorTest extends TestCase
{
    private RestDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new RestDataCollector();
    }

    #[Test]
    public function getNameReturnsRest(): void
    {
        self::assertSame('rest', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsRestApi(): void
    {
        self::assertSame('REST API', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressReturnsDefaults(): void
    {
        if (function_exists('rest_get_server')) {
            self::markTestSkipped('WordPress functions are available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertFalse($data['is_rest_request']);
        self::assertNull($data['current_request']);
        self::assertSame([], $data['routes']);
        self::assertSame([], $data['namespaces']);
        self::assertSame(0, $data['total_routes']);
        self::assertSame(0, $data['total_namespaces']);
    }

    #[Test]
    public function getBadgeValueReturnsEmpty(): void
    {
        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsDefaultWhenNotRestRequest(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['is_rest_request' => false]);

        self::assertSame('default', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsGreenForSuccess(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_rest_request' => true,
            'current_request' => ['status' => 200],
        ]);

        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedForClientError(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_rest_request' => true,
            'current_request' => ['status' => 404],
        ]);

        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsYellowForRedirect(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_rest_request' => true,
            'current_request' => ['status' => 301],
        ]);

        self::assertSame('yellow', $this->collector->getBadgeColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['is_rest_request' => true]);
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }
}
