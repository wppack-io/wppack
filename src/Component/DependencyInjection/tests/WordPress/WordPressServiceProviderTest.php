<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\WordPress;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\DependencyInjection\WordPress\WordPressServiceProvider;

final class WordPressServiceProviderTest extends TestCase
{
    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        $provider = new WordPressServiceProvider();

        self::assertInstanceOf(ServiceProviderInterface::class, $provider);
    }

    #[Test]
    #[DataProvider('serviceIdProvider')]
    public function registersService(string $serviceId): void
    {
        $builder = new ContainerBuilder();
        (new WordPressServiceProvider())->register($builder);

        self::assertTrue($builder->hasDefinition($serviceId));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function serviceIdProvider(): iterable
    {
        yield 'wpdb' => ['wpdb'];
        yield 'wp_filesystem' => ['wp_filesystem'];
        yield 'wp' => ['wp'];
        yield 'wp_rewrite' => ['wp_rewrite'];
        yield 'wp_the_query' => ['wp_the_query'];
        yield 'wp_query' => ['wp_query'];
        yield 'wp_roles' => ['wp_roles'];
        yield 'wp_locale' => ['wp_locale'];
        yield 'wp_locale_switcher' => ['wp_locale_switcher'];
        yield 'wp_object_cache' => ['wp_object_cache'];
        yield 'wp_embed' => ['wp_embed'];
        yield 'wp_widget_factory' => ['wp_widget_factory'];
        yield 'wp_textdomain_registry' => ['wp_textdomain_registry'];
        yield 'wp_scripts' => ['wp_scripts'];
        yield 'wp_styles' => ['wp_styles'];
        yield 'wp_admin_bar' => ['wp_admin_bar'];
        yield 'wp_customize' => ['wp_customize'];
    }

    #[Test]
    #[DataProvider('factoryMethodProvider')]
    public function setsFactory(string $serviceId, string $factoryMethod): void
    {
        $builder = new ContainerBuilder();
        (new WordPressServiceProvider())->register($builder);

        $definition = $builder->findDefinition($serviceId);
        $factory = $definition->getFactory();

        self::assertNotNull($factory);
        self::assertSame(WordPressServiceProvider::class, $factory[0]);
        self::assertSame($factoryMethod, $factory[1]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function factoryMethodProvider(): iterable
    {
        yield 'wpdb' => ['wpdb', 'getWpdb'];
        yield 'wp_filesystem' => ['wp_filesystem', 'getWpFilesystem'];
        yield 'wp' => ['wp', 'getWp'];
        yield 'wp_rewrite' => ['wp_rewrite', 'getWpRewrite'];
        yield 'wp_the_query' => ['wp_the_query', 'getWpTheQuery'];
        yield 'wp_query' => ['wp_query', 'getWpQuery'];
        yield 'wp_roles' => ['wp_roles', 'getWpRoles'];
        yield 'wp_locale' => ['wp_locale', 'getWpLocale'];
        yield 'wp_locale_switcher' => ['wp_locale_switcher', 'getWpLocaleSwitcher'];
        yield 'wp_object_cache' => ['wp_object_cache', 'getWpObjectCache'];
        yield 'wp_embed' => ['wp_embed', 'getWpEmbed'];
        yield 'wp_widget_factory' => ['wp_widget_factory', 'getWpWidgetFactory'];
        yield 'wp_textdomain_registry' => ['wp_textdomain_registry', 'getWpTextdomainRegistry'];
        yield 'wp_scripts' => ['wp_scripts', 'getWpScripts'];
        yield 'wp_styles' => ['wp_styles', 'getWpStyles'];
        yield 'wp_admin_bar' => ['wp_admin_bar', 'getWpAdminBar'];
        yield 'wp_customize' => ['wp_customize', 'getWpCustomize'];
    }

    #[Test]
    #[DataProvider('aliasProvider')]
    public function registersClassAlias(string $className, string $serviceId): void
    {
        $builder = new ContainerBuilder();
        (new WordPressServiceProvider())->register($builder);

        $definition = $builder->findDefinition($serviceId);

        self::assertNotNull($definition);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function aliasProvider(): iterable
    {
        yield 'WP_Filesystem_Base' => [\WP_Filesystem_Base::class, 'wp_filesystem'];
        yield 'WP' => [\WP::class, 'wp'];
        yield 'WP_Rewrite' => [\WP_Rewrite::class, 'wp_rewrite'];
        yield 'WP_Roles' => [\WP_Roles::class, 'wp_roles'];
        yield 'WP_Locale' => [\WP_Locale::class, 'wp_locale'];
        yield 'WP_Locale_Switcher' => [\WP_Locale_Switcher::class, 'wp_locale_switcher'];
        yield 'WP_Object_Cache' => [\WP_Object_Cache::class, 'wp_object_cache'];
        yield 'WP_Embed' => [\WP_Embed::class, 'wp_embed'];
        yield 'WP_Widget_Factory' => [\WP_Widget_Factory::class, 'wp_widget_factory'];
        yield 'WP_Textdomain_Registry' => [\WP_Textdomain_Registry::class, 'wp_textdomain_registry'];
        yield 'WP_Scripts' => [\WP_Scripts::class, 'wp_scripts'];
        yield 'WP_Styles' => [\WP_Styles::class, 'wp_styles'];
        yield 'WP_Admin_Bar' => [\WP_Admin_Bar::class, 'wp_admin_bar'];
        yield 'WP_Customize_Manager' => [\WP_Customize_Manager::class, 'wp_customize'];
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $builder = new ContainerBuilder();

        $result = $builder->addServiceProvider(new WordPressServiceProvider());

        self::assertSame($builder, $result);
        self::assertTrue($builder->hasDefinition('wpdb'));
        self::assertTrue($builder->hasDefinition('wp_filesystem'));
        self::assertTrue($builder->hasDefinition('wp'));
        self::assertTrue($builder->hasDefinition('wp_rewrite'));
        self::assertTrue($builder->hasDefinition('wp_the_query'));
        self::assertTrue($builder->hasDefinition('wp_query'));
        self::assertTrue($builder->hasDefinition('wp_roles'));
        self::assertTrue($builder->hasDefinition('wp_locale'));
        self::assertTrue($builder->hasDefinition('wp_locale_switcher'));
        self::assertTrue($builder->hasDefinition('wp_object_cache'));
        self::assertTrue($builder->hasDefinition('wp_embed'));
        self::assertTrue($builder->hasDefinition('wp_widget_factory'));
        self::assertTrue($builder->hasDefinition('wp_textdomain_registry'));
        self::assertTrue($builder->hasDefinition('wp_scripts'));
        self::assertTrue($builder->hasDefinition('wp_styles'));
        self::assertTrue($builder->hasDefinition('wp_admin_bar'));
        self::assertTrue($builder->hasDefinition('wp_customize'));
    }

    #[Test]
    public function wpQueryAndWpTheQueryAreSeparateServices(): void
    {
        $builder = new ContainerBuilder();
        (new WordPressServiceProvider())->register($builder);

        $queryFactory = $builder->findDefinition('wp_query')->getFactory();
        $theQueryFactory = $builder->findDefinition('wp_the_query')->getFactory();

        self::assertSame('getWpQuery', $queryFactory[1]);
        self::assertSame('getWpTheQuery', $theQueryFactory[1]);
    }
}
