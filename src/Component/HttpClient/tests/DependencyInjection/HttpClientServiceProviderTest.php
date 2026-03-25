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

namespace WpPack\Component\HttpClient\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\HttpClient\DependencyInjection\HttpClientServiceProvider;
use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\HttpClient\SafeHttpClient;

final class HttpClientServiceProviderTest extends TestCase
{
    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        $provider = new HttpClientServiceProvider();

        self::assertInstanceOf(ServiceProviderInterface::class, $provider);
    }

    #[Test]
    public function registersHttpClient(): void
    {
        $builder = new ContainerBuilder();
        $provider = new HttpClientServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(HttpClient::class));
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $builder = new ContainerBuilder();

        $result = $builder->addServiceProvider(new HttpClientServiceProvider());

        self::assertSame($builder, $result);
        self::assertTrue($builder->hasDefinition(HttpClient::class));
    }

    #[Test]
    public function compiledContainerResolvesHttpClient(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new HttpClientServiceProvider());

        $container = $builder->compile();

        self::assertTrue($container->has(HttpClient::class));
        self::assertInstanceOf(HttpClient::class, $container->get(HttpClient::class));
    }

    #[Test]
    public function registersSafeHttpClient(): void
    {
        $builder = new ContainerBuilder();
        $provider = new HttpClientServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(SafeHttpClient::class));
    }

    #[Test]
    public function compiledContainerResolvesSafeHttpClient(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new HttpClientServiceProvider());

        $container = $builder->compile();

        self::assertTrue($container->has(SafeHttpClient::class));
        self::assertInstanceOf(SafeHttpClient::class, $container->get(SafeHttpClient::class));
    }

    #[Test]
    public function compiledContainerResolvesClientInterfaceAlias(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new HttpClientServiceProvider());

        $container = $builder->compile();

        self::assertTrue($container->has(ClientInterface::class));
        self::assertInstanceOf(HttpClient::class, $container->get(ClientInterface::class));
    }
}
