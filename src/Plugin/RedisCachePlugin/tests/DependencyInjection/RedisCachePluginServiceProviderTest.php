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

namespace WpPack\Plugin\RedisCachePlugin\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\CacheManager;
use WpPack\Component\Cache\ObjectCache;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Plugin\RedisCachePlugin\Configuration\RedisCacheConfiguration;
use WpPack\Plugin\RedisCachePlugin\DependencyInjection\RedisCachePluginServiceProvider;

#[CoversClass(RedisCachePluginServiceProvider::class)]
final class RedisCachePluginServiceProviderTest extends TestCase
{
    private ContainerBuilder $builder;
    private RedisCachePluginServiceProvider $provider;

    protected function setUp(): void
    {
        $this->builder = new ContainerBuilder();
        $this->provider = new RedisCachePluginServiceProvider();
    }

    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        self::assertInstanceOf(ServiceProviderInterface::class, $this->provider);
    }

    #[Test]
    public function registersConfiguration(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(RedisCacheConfiguration::class));

        $definition = $this->builder->findDefinition(RedisCacheConfiguration::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(RedisCacheConfiguration::class, $factory[0]);
        self::assertSame('fromEnvironment', $factory[1]);
    }

    #[Test]
    public function registersObjectCache(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(ObjectCache::class));

        $definition = $this->builder->findDefinition(ObjectCache::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(RedisCachePluginServiceProvider::class, $factory[0]);
        self::assertSame('getObjectCache', $factory[1]);
    }

    #[Test]
    public function registersCacheManager(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(CacheManager::class));
    }

    #[Test]
    public function getObjectCacheReturnsGlobal(): void
    {
        $objectCache = new ObjectCache(null);
        $GLOBALS['wp_object_cache'] = $objectCache;

        try {
            $result = RedisCachePluginServiceProvider::getObjectCache();

            self::assertSame($objectCache, $result);
        } finally {
            unset($GLOBALS['wp_object_cache']);
        }
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $result = $this->builder->addServiceProvider($this->provider);

        self::assertSame($this->builder, $result);
        self::assertTrue($this->builder->hasDefinition(RedisCacheConfiguration::class));
        self::assertTrue($this->builder->hasDefinition(ObjectCache::class));
        self::assertTrue($this->builder->hasDefinition(CacheManager::class));
    }
}
