<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\WordPress;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\DependencyInjection\WordPress\WordPressServiceProvider;

final class WordPressServiceProviderTest extends TestCase
{
    #[Test]
    public function registersWpdbService(): void
    {
        $builder = new ContainerBuilder();
        $provider = new WordPressServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition('wpdb'));
    }

    #[Test]
    public function registersWpFilesystemService(): void
    {
        $builder = new ContainerBuilder();
        $provider = new WordPressServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition('wp_filesystem'));
    }

    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        $provider = new WordPressServiceProvider();

        self::assertInstanceOf(ServiceProviderInterface::class, $provider);
    }

    #[Test]
    public function setsWpdbFactory(): void
    {
        $builder = new ContainerBuilder();
        $provider = new WordPressServiceProvider();

        $provider->register($builder);

        $definition = $builder->findDefinition('wpdb');
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(WordPressServiceProvider::class, $factory[0]);
        self::assertSame('getWpdb', $factory[1]);
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $builder = new ContainerBuilder();

        $result = $builder->addServiceProvider(new WordPressServiceProvider());

        self::assertSame($builder, $result);
        self::assertTrue($builder->hasDefinition('wpdb'));
        self::assertTrue($builder->hasDefinition('wp_filesystem'));
    }
}
