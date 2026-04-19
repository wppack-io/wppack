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

namespace WPPack\Plugin\RedisCachePlugin\DependencyInjection;

use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\Cache\CacheManager;
use WPPack\Component\Cache\ObjectCache;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Plugin\RedisCachePlugin\Admin\RedisCacheSettingsController;
use WPPack\Plugin\RedisCachePlugin\Admin\RedisCacheSettingsPage;
use WPPack\Plugin\RedisCachePlugin\Configuration\RedisCacheConfiguration;

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
    }

    public static function getObjectCache(): ObjectCache
    {
        return $GLOBALS['wp_object_cache'];
    }
}
