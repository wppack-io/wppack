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

namespace WPPack\Component\Monitoring\Bridge\CloudWatch\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Monitoring\Bridge\CloudWatch\DependencyInjection\CloudWatchServiceProvider;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\DSQLDiscovery;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\DynamoDbDiscovery;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\ElastiCacheDiscovery;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\RdsDiscovery;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\S3Discovery;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\SesDiscovery;

#[CoversClass(CloudWatchServiceProvider::class)]
final class CloudWatchServiceProviderTest extends TestCase
{
    #[Test]
    public function registersAllDiscoveries(): void
    {
        $builder = new ContainerBuilder();

        (new CloudWatchServiceProvider())->register($builder);

        foreach ([
            RdsDiscovery::class,
            DSQLDiscovery::class,
            ElastiCacheDiscovery::class,
            SesDiscovery::class,
            S3Discovery::class,
            DynamoDbDiscovery::class,
        ] as $id) {
            self::assertTrue($builder->hasDefinition($id), "discovery not registered: {$id}");
        }
    }

    #[Test]
    public function everyDiscoveryIsTaggedAsMonitoringProvider(): void
    {
        $builder = new ContainerBuilder();

        (new CloudWatchServiceProvider())->register($builder);

        $taggedIds = array_keys($builder->findTaggedServiceIds('monitoring.provider'));

        foreach ([
            RdsDiscovery::class,
            DSQLDiscovery::class,
            ElastiCacheDiscovery::class,
            SesDiscovery::class,
            S3Discovery::class,
            DynamoDbDiscovery::class,
        ] as $id) {
            self::assertContains($id, $taggedIds);
        }
    }
}
