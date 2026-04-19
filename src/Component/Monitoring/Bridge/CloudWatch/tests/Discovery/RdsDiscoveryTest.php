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
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\RdsDiscovery;

final class RdsDiscoveryTest extends TestCase
{
    // DB_HOST is defined by WordPress bootstrap pointing to the test MySQL,
    // which is not an RDS endpoint. We verify that non-RDS hosts are ignored.

    #[Test]
    public function returnsEmptyWhenHostIsNotRds(): void
    {
        $discovery = new RdsDiscovery();

        // Test environment DB_HOST (localhost / docker) is not an RDS endpoint
        self::assertSame([], $discovery->getProviders());
    }

    #[Test]
    public function implementsMonitoringProviderInterface(): void
    {
        $discovery = new RdsDiscovery();

        self::assertInstanceOf(\WPPack\Component\Monitoring\MonitoringProviderInterface::class, $discovery);
    }
}
