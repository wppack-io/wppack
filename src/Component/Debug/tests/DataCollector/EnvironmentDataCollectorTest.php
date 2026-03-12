<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\EnvironmentDataCollector;

final class EnvironmentDataCollectorTest extends TestCase
{
    private EnvironmentDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new EnvironmentDataCollector();
    }

    #[Test]
    public function getNameReturnsEnvironment(): void
    {
        self::assertSame('environment', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsEnvironment(): void
    {
        self::assertSame('Environment', $this->collector->getLabel());
    }

    #[Test]
    public function collectGathersPhpInfo(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('php', $data);

        $php = $data['php'];
        self::assertSame(PHP_VERSION, $php['version']);
        self::assertSame(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, $php['major_minor']);
        self::assertIsBool($php['zts']);
        self::assertIsBool($php['debug']);
        self::assertIsBool($php['gc_enabled']);
        self::assertSame(zend_version(), $php['zend_version']);
    }

    #[Test]
    public function collectGathersExtensions(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('extensions', $data);
        self::assertIsArray($data['extensions']);
        self::assertNotEmpty($data['extensions']);

        // Extensions should be sorted
        $extensions = $data['extensions'];
        $sorted = $extensions;
        sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);
        self::assertSame($sorted, $extensions);
    }

    #[Test]
    public function collectGathersIniSettings(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('ini', $data);
        self::assertIsArray($data['ini']);
        self::assertArrayHasKey('memory_limit', $data['ini']);
        self::assertArrayHasKey('max_execution_time', $data['ini']);
    }

    #[Test]
    public function collectGathersSapiAndOs(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(PHP_SAPI, $data['sapi']);
        self::assertSame(PHP_OS, $data['os']);
    }

    #[Test]
    public function collectGathersArchitectureAndHostname(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(PHP_INT_SIZE * 8, $data['architecture']);
        self::assertArrayHasKey('hostname', $data);
        self::assertIsString($data['hostname']);
    }

    #[Test]
    public function collectGathersOpcacheInfo(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('opcache', $data);
        self::assertArrayHasKey('enabled', $data['opcache']);
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
