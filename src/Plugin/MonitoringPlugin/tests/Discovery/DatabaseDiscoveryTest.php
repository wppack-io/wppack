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

namespace WpPack\Plugin\MonitoringPlugin\Tests\Discovery;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Monitoring\MonitoringProvider;
use WpPack\Plugin\MonitoringPlugin\Discovery\DatabaseDiscovery;

final class DatabaseDiscoveryTest extends TestCase
{
    // DB_HOST is defined by WordPress bootstrap pointing to the test MySQL,
    // which is not an RDS endpoint. We verify that non-RDS hosts are ignored.

    #[Test]
    public function returnsEmptyWhenHostIsNotRds(): void
    {
        $discovery = new DatabaseDiscovery();

        // Test environment DB_HOST (localhost / docker) is not an RDS endpoint
        self::assertSame([], $discovery->getProviders());
    }

    #[Test]
    public function implementsMonitoringProviderInterface(): void
    {
        $discovery = new DatabaseDiscovery();

        self::assertInstanceOf(\WpPack\Component\Monitoring\MonitoringProviderInterface::class, $discovery);
    }
}
