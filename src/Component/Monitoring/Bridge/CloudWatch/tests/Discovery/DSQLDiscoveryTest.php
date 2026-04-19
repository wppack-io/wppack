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

namespace WPPack\Component\Monitoring\Bridge\CloudWatch\Tests\Discovery;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\DSQLDiscovery;

final class DSQLDiscoveryTest extends TestCase
{
    #[Test]
    public function returnsEmptyWhenNoDSQLEndpoint(): void
    {
        $discovery = new DSQLDiscovery();

        // Test environment has no DSQL endpoint
        self::assertSame([], $discovery->getProviders());
    }

    #[Test]
    public function implementsMonitoringProviderInterface(): void
    {
        $discovery = new DSQLDiscovery();

        self::assertInstanceOf(\WPPack\Component\Monitoring\MonitoringProviderInterface::class, $discovery);
    }
}
