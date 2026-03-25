<?php

declare(strict_types=1);

namespace WpPack\Plugin\RedisCachePlugin\DependencyInjection;

use WpPack\Component\Cache\CacheManager;
use WpPack\Component\Cache\ObjectCache;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Plugin\RedisCachePlugin\Configuration\RedisCacheConfiguration;

final class RedisCachePluginServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->register(RedisCacheConfiguration::class, RedisCacheConfiguration::class)
            ->setFactory([RedisCacheConfiguration::class, 'fromEnvironment']);

        $builder->register(ObjectCache::class, ObjectCache::class)
            ->setFactory([self::class, 'getObjectCache']);

        $builder->register(CacheManager::class, CacheManager::class);
    }

    public static function getObjectCache(): ObjectCache
    {
        return $GLOBALS['wp_object_cache'];
    }
}
