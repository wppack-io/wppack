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

namespace WPPack\Component\Monitoring\Bridge\CloudWatch\DependencyInjection;

use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\DSQLDiscovery;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\DynamoDbDiscovery;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\ElastiCacheDiscovery;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\RdsDiscovery;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\S3Discovery;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\SesDiscovery;

/**
 * Registers all AWS CloudWatch discovery providers.
 */
class CloudWatchServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->register(RdsDiscovery::class)
            ->addTag('monitoring.provider');

        $builder->register(DSQLDiscovery::class)
            ->addTag('monitoring.provider');

        $builder->register(ElastiCacheDiscovery::class)
            ->addTag('monitoring.provider');

        $builder->register(SesDiscovery::class)
            ->addTag('monitoring.provider');

        $builder->register(S3Discovery::class)
            ->addTag('monitoring.provider');

        $builder->register(DynamoDbDiscovery::class)
            ->addTag('monitoring.provider');
    }
}
