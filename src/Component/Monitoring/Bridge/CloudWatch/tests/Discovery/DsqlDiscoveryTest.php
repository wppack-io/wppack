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

namespace WpPack\Component\Monitoring\Bridge\CloudWatch\Tests\Discovery;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Monitoring\Bridge\CloudWatch\Discovery\DsqlDiscovery;

final class DsqlDiscoveryTest extends TestCase
{
    #[Test]
    public function returnsEmptyWhenNoDsqlEndpoint(): void
    {
        $discovery = new DsqlDiscovery();

        // Test environment has no DSQL endpoint
        self::assertSame([], $discovery->getProviders());
    }

    #[Test]
    public function implementsMonitoringProviderInterface(): void
    {
        $discovery = new DsqlDiscovery();

        self::assertInstanceOf(\WpPack\Component\Monitoring\MonitoringProviderInterface::class, $discovery);
    }
}
