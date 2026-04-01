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

namespace WpPack\Plugin\RedisCachePlugin\DependencyInjection;

use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\Cache\CacheManager;
use WpPack\Component\Cache\ObjectCache;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Plugin\RedisCachePlugin\Admin\RedisCacheSettingsController;
use WpPack\Plugin\RedisCachePlugin\Admin\RedisCacheSettingsPage;
use WpPack\Plugin\RedisCachePlugin\Configuration\RedisCacheConfiguration;
use WpPack\Plugin\RedisCachePlugin\Monitoring\RedisCacheMetricSourceProvider;

final class RedisCachePluginServiceProvider implements ServiceProviderInterface
{
    public function registerAdmin(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(AdminPageRegistry::class)) {
            $builder->register(AdminPageRegistry::class);
        }

        if (!$builder->hasDefinition(RestRegistry::class)) {
            $builder->register(RestRegistry::class)
                ->addArgument(new Reference(Request::class));
        }

        $builder->register(RedisCacheSettingsPage::class);
        $builder->register(RedisCacheSettingsController::class);
    }

    public function register(ContainerBuilder $builder): void
    {
        $builder->register(RedisCacheConfiguration::class, RedisCacheConfiguration::class)
            ->setFactory([RedisCacheConfiguration::class, 'fromEnvironmentOrOptions']);

        $builder->register(ObjectCache::class, ObjectCache::class)
            ->setFactory([self::class, 'getObjectCache']);

        $builder->register(CacheManager::class, CacheManager::class);

        // Monitoring integration
        $builder->register(RedisCacheMetricSourceProvider::class)
            ->addTag('monitoring.metric_source_provider');
    }

    public static function getObjectCache(): ObjectCache
    {
        return $GLOBALS['wp_object_cache'];
    }
}
