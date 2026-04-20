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

namespace WPPack\Component\Monitoring\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Monitoring\MonitoringProvider;
use WPPack\Component\Monitoring\MonitoringProviderInterface;
use WPPack\Component\Monitoring\MonitoringRegistry;
use WPPack\Component\Monitoring\ProviderSettings;

#[CoversClass(MonitoringRegistry::class)]
final class MonitoringRegistryTest extends TestCase
{
    private function provider(string $id, string $bridge = 'cloudwatch'): MonitoringProvider
    {
        return new MonitoringProvider(
            id: $id,
            label: 'Provider ' . $id,
            bridge: $bridge,
            settings: new ProviderSettings(),
        );
    }

    #[Test]
    public function emptyRegistry(): void
    {
        $registry = new MonitoringRegistry();

        self::assertSame([], $registry->all());
        self::assertSame([], $registry->bridges());
        self::assertNull($registry->get('unknown'));
    }

    #[Test]
    public function addProviderIsRetrievableById(): void
    {
        $registry = new MonitoringRegistry();
        $p = $this->provider('p1');

        $registry->addProvider($p);

        self::assertSame([$p], $registry->all());
        self::assertSame($p, $registry->get('p1'));
    }

    #[Test]
    public function getReturnsNullForMissingId(): void
    {
        $registry = new MonitoringRegistry();
        $registry->addProvider($this->provider('p1'));

        self::assertNull($registry->get('p2'));
    }

    #[Test]
    public function bridgesReturnsUniqueBridgeNames(): void
    {
        $registry = new MonitoringRegistry();
        $registry->addProvider($this->provider('p1', 'cloudwatch'));
        $registry->addProvider($this->provider('p2', 'cloudflare'));
        $registry->addProvider($this->provider('p3', 'cloudwatch'));

        self::assertSame(['cloudwatch', 'cloudflare'], $registry->bridges());
    }

    #[Test]
    public function addFromSourceMergesEveryProviderEmittedByTheSource(): void
    {
        $p1 = $this->provider('p1', 'cloudwatch');
        $p2 = $this->provider('p2', 'cloudflare');

        $source = new class ($p1, $p2) implements MonitoringProviderInterface {
            public function __construct(private MonitoringProvider $a, private MonitoringProvider $b) {}

            public function getProviders(): array
            {
                return [$this->a, $this->b];
            }
        };

        $registry = new MonitoringRegistry();
        $registry->addFromSource($source);

        self::assertSame([$p1, $p2], $registry->all());
    }

    #[Test]
    public function addProviderPreservesInsertionOrder(): void
    {
        $registry = new MonitoringRegistry();
        $a = $this->provider('a');
        $b = $this->provider('b');
        $c = $this->provider('c');

        $registry->addProvider($b);
        $registry->addProvider($c);
        $registry->addProvider($a);

        $ids = array_map(static fn(MonitoringProvider $p): string => $p->id, $registry->all());
        self::assertSame(['b', 'c', 'a'], $ids);
    }
}
