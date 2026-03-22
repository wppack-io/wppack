<?php

declare(strict_types=1);

namespace WpPack\Component\Kernel\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Kernel\AbstractTheme;
use WpPack\Component\Kernel\Exception\KernelAlreadyBootedException;
use WpPack\Component\Kernel\Kernel;
use WpPack\Component\Kernel\Tests\Fixtures\AnotherPlugin;
use WpPack\Component\Kernel\Tests\Fixtures\TestPlugin;
use WpPack\Component\Kernel\Tests\Fixtures\TestService;
use WpPack\Component\Kernel\Tests\Fixtures\TestTheme;

final class KernelTest extends TestCase
{
    protected function setUp(): void
    {
        Kernel::resetInstance();
    }

    #[Test]
    public function bootsWithSinglePlugin(): void
    {
        $plugin = new TestPlugin();
        $kernel = new Kernel();
        $kernel->addPlugin($plugin);

        $kernel->boot();

        self::assertTrue($plugin->registered);
        self::assertTrue($plugin->booted);
    }

    #[Test]
    public function bootsWithSingleTheme(): void
    {
        $theme = new TestTheme();
        $kernel = new Kernel();
        $kernel->addTheme($theme);

        $kernel->boot();

        self::assertTrue($theme->registered);
        self::assertTrue($theme->booted);
    }

    #[Test]
    public function bootsWithMultiplePluginsAndThemes(): void
    {
        $plugin1 = new TestPlugin();
        $plugin2 = new AnotherPlugin();
        $theme = new TestTheme();

        $kernel = new Kernel();
        $kernel->addPlugin($plugin1);
        $kernel->addPlugin($plugin2);
        $kernel->addTheme($theme);

        $kernel->boot();

        self::assertTrue($plugin1->registered);
        self::assertTrue($plugin1->booted);
        self::assertTrue($plugin2->registered);
        self::assertTrue($plugin2->booted);
        self::assertTrue($theme->registered);
        self::assertTrue($theme->booted);
    }

    #[Test]
    public function bootsWithNoPluginsOrThemes(): void
    {
        $kernel = new Kernel();

        $container = $kernel->boot();

        self::assertInstanceOf(Container::class, $container);
        self::assertTrue($kernel->isBooted());
    }

    #[Test]
    public function bootReturnsContainer(): void
    {
        $kernel = new Kernel();
        $plugin = new TestPlugin();
        $kernel->addPlugin($plugin);

        $container = $kernel->boot();

        self::assertInstanceOf(Container::class, $container);
    }

    #[Test]
    public function callsRegisterBeforeCompilerPasses(): void
    {
        $order = [];

        $pass = new class ($order) implements CompilerPassInterface {
            /** @param list<string> $order */
            public function __construct(private array &$order) {}

            public function process(ContainerBuilder $builder): void
            {
                $this->order[] = 'compiler_pass';
            }
        };

        $plugin = new class (__FILE__, $order, $pass) extends AbstractPlugin {
            /** @param list<string> $order */
            public function __construct(
                string $pluginFile,
                private array &$order,
                private readonly CompilerPassInterface $pass,
            ) {
                parent::__construct($pluginFile);
            }

            public function register(ContainerBuilder $builder): void
            {
                $this->order[] = 'register';
            }

            public function getCompilerPasses(): array
            {
                return [$this->pass];
            }
        };

        $kernel = new Kernel();
        $kernel->addPlugin($plugin);
        $kernel->boot();

        self::assertSame(['register', 'compiler_pass'], $order);
    }

    #[Test]
    public function callsBootAfterCompile(): void
    {
        $plugin = new TestPlugin();
        $kernel = new Kernel();
        $kernel->addPlugin($plugin);

        $kernel->boot();

        self::assertNotNull($plugin->bootedContainer);
        self::assertTrue($plugin->bootedContainer->has(TestService::class));
        self::assertInstanceOf(TestService::class, $plugin->bootedContainer->get(TestService::class));
    }

    #[Test]
    public function registersPluginsBeforeThemes(): void
    {
        $order = [];

        $plugin = new class (__FILE__, $order) extends AbstractPlugin {
            /** @param list<string> $order */
            public function __construct(string $pluginFile, private array &$order)
            {
                parent::__construct($pluginFile);
            }

            public function register(ContainerBuilder $builder): void
            {
                $this->order[] = 'plugin';
            }
        };

        $theme = new class (__FILE__, $order) extends AbstractTheme {
            /** @param list<string> $order */
            public function __construct(string $themeFile, private array &$order)
            {
                parent::__construct($themeFile);
            }

            public function register(ContainerBuilder $builder): void
            {
                $this->order[] = 'theme';
            }
        };

        $kernel = new Kernel();
        $kernel->addPlugin($plugin);
        $kernel->addTheme($theme);
        $kernel->boot();

        self::assertSame(['plugin', 'theme'], $order);
    }

    #[Test]
    public function throwsWhenBootedTwice(): void
    {
        $kernel = new Kernel();
        $kernel->boot();

        $this->expectException(KernelAlreadyBootedException::class);
        $kernel->boot();
    }

    #[Test]
    public function throwsWhenAddingPluginAfterBoot(): void
    {
        $kernel = new Kernel();
        $kernel->boot();

        $this->expectException(KernelAlreadyBootedException::class);
        $kernel->addPlugin(new TestPlugin());
    }

    #[Test]
    public function throwsWhenAddingThemeAfterBoot(): void
    {
        $kernel = new Kernel();
        $kernel->boot();

        $this->expectException(KernelAlreadyBootedException::class);
        $kernel->addTheme(new TestTheme());
    }

    #[Test]
    public function throwsWhenGettingContainerBeforeBoot(): void
    {
        $kernel = new Kernel();

        $this->expectException(\LogicException::class);
        $kernel->getContainer();
    }

    #[Test]
    public function getContainerReturnsCompiledContainer(): void
    {
        $kernel = new Kernel();
        $plugin = new TestPlugin();
        $kernel->addPlugin($plugin);

        $bootContainer = $kernel->boot();
        $getContainer = $kernel->getContainer();

        self::assertSame($bootContainer, $getContainer);
        self::assertTrue($getContainer->has(TestService::class));
    }

    #[Test]
    public function isBootedReturnsFalseBeforeBoot(): void
    {
        $kernel = new Kernel();

        self::assertFalse($kernel->isBooted());
    }

    #[Test]
    public function isBootedReturnsTrueAfterBoot(): void
    {
        $kernel = new Kernel();
        $kernel->boot();

        self::assertTrue($kernel->isBooted());
    }

    #[Test]
    public function addPluginReturnsSelf(): void
    {
        $kernel = new Kernel();

        $result = $kernel->addPlugin(new TestPlugin());

        self::assertSame($kernel, $result);
    }

    #[Test]
    public function addThemeReturnsSelf(): void
    {
        $kernel = new Kernel();

        $result = $kernel->addTheme(new TestTheme());

        self::assertSame($kernel, $result);
    }

    #[Test]
    public function registerPluginAddsToInstance(): void
    {
        Kernel::registerPlugin(new TestPlugin(__FILE__));

        $instance = Kernel::getInstance();

        self::assertCount(1, $instance->getPlugins());
        self::assertInstanceOf(TestPlugin::class, $instance->getPlugins()[0]);
    }

    #[Test]
    public function registerThemeAddsToInstance(): void
    {
        Kernel::registerTheme(new TestTheme());

        $instance = Kernel::getInstance();

        self::assertCount(1, $instance->getThemes());
        self::assertInstanceOf(TestTheme::class, $instance->getThemes()[0]);
    }

    #[Test]
    public function getInstanceReturnsSameInstance(): void
    {
        $first = Kernel::getInstance();
        $second = Kernel::getInstance();

        self::assertSame($first, $second);
    }

    #[Test]
    public function resetInstanceClearsState(): void
    {
        Kernel::registerPlugin(new TestPlugin(__FILE__));
        $first = Kernel::getInstance();

        Kernel::resetInstance();

        $second = Kernel::getInstance();
        self::assertNotSame($first, $second);
        self::assertCount(0, $second->getPlugins());
    }

    #[Test]
    public function defaultsToWordPressEnvironmentType(): void
    {
        $kernel = new Kernel(autoBoot: false);

        $expected = wp_get_environment_type();

        self::assertSame($expected, $kernel->getEnvironment());
    }

    #[Test]
    public function acceptsCustomEnvironment(): void
    {
        $kernel = new Kernel(environment: 'development', autoBoot: false);

        self::assertSame('development', $kernel->getEnvironment());
    }

    #[Test]
    public function defaultsToWpDebugConstant(): void
    {
        $kernel = new Kernel(autoBoot: false);

        $expected = defined('WP_DEBUG') && WP_DEBUG;

        self::assertSame($expected, $kernel->isDebug());
    }

    #[Test]
    public function acceptsExplicitDebugTrue(): void
    {
        $kernel = new Kernel(debug: true, autoBoot: false);

        self::assertTrue($kernel->isDebug());
    }

    #[Test]
    public function acceptsExplicitDebugFalse(): void
    {
        $kernel = new Kernel(debug: false, autoBoot: false);

        self::assertFalse($kernel->isDebug());
    }

    #[Test]
    public function autoBootBootsInstance(): void
    {
        $plugin = new TestPlugin();
        Kernel::registerPlugin($plugin);

        Kernel::autoBoot();

        self::assertTrue(Kernel::getInstance()->isBooted());
        self::assertTrue($plugin->booted);
    }

    #[Test]
    public function autoBootSkipsWhenAlreadyBooted(): void
    {
        Kernel::getInstance()->boot();

        // Should not throw KernelAlreadyBootedException
        Kernel::autoBoot();

        self::assertTrue(Kernel::getInstance()->isBooted());
    }

    #[Test]
    public function bootRegistersRequestAsSyntheticService(): void
    {
        $kernel = new Kernel();

        $container = $kernel->boot();

        self::assertTrue($container->has(Request::class));
        self::assertInstanceOf(Request::class, $container->get(Request::class));
    }
}
