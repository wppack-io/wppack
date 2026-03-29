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

namespace WpPack\Plugin\DebugPlugin\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\ErrorHandler\ExceptionHandler;
use WpPack\Component\Debug\ErrorHandler\RedirectHandler;
use WpPack\Component\Debug\ErrorHandler\WpDieHandler;
use WpPack\Component\Debug\Profiler\Profiler;
use WpPack\Component\Debug\Toolbar\ToolbarSubscriber;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Logger\LoggerFactory;
use WpPack\Plugin\DebugPlugin\DependencyInjection\DebugPluginServiceProvider;

#[CoversClass(DebugPluginServiceProvider::class)]
final class DebugPluginServiceProviderTest extends TestCase
{
    private ContainerBuilder $builder;
    private DebugPluginServiceProvider $provider;

    protected function setUp(): void
    {
        $this->builder = new ContainerBuilder();
        $this->provider = new DebugPluginServiceProvider();
    }

    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        self::assertInstanceOf(ServiceProviderInterface::class, $this->provider);
    }

    #[Test]
    public function registersDebugConfig(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(DebugConfig::class));

        $definition = $this->builder->findDefinition(DebugConfig::class);
        $arguments = $definition->getArguments();
        self::assertCount(2, $arguments);
        self::assertTrue($arguments[0]);  // enabled
        self::assertTrue($arguments[1]);  // showToolbar
    }

    #[Test]
    public function registersToolbarSubscriber(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(ToolbarSubscriber::class));
    }

    #[Test]
    public function registersProfiler(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(Profiler::class));
    }

    #[Test]
    public function registersExceptionHandler(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(ExceptionHandler::class));
    }

    #[Test]
    public function registersRedirectHandler(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(RedirectHandler::class));
    }

    #[Test]
    public function registersWpDieHandler(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(WpDieHandler::class));
    }

    #[Test]
    public function autoRegistersLoggerServiceProvider(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(LoggerFactory::class));
    }

    #[Test]
    public function doesNotOverrideExistingLoggerFactory(): void
    {
        $this->builder->register(LoggerFactory::class)
            ->addArgument('custom');

        $this->provider->register($this->builder);

        $definition = $this->builder->findDefinition(LoggerFactory::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertSame('custom', $arguments[0]);
    }

    #[Test]
    public function registersDebugLoggerChannel(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition('logger.debug'));

        $definition = $this->builder->findDefinition('logger.debug');
        self::assertSame(\Psr\Log\LoggerInterface::class, $definition->getClass());
    }

    #[Test]
    public function doesNotOverrideExistingDebugLoggerChannel(): void
    {
        // Pre-register logger.debug
        $this->builder->register('logger.debug', \Psr\Log\LoggerInterface::class)
            ->addArgument('custom');

        // Pre-register LoggerFactory so provider skips its own registration
        $this->builder->register(LoggerFactory::class);

        $this->provider->register($this->builder);

        $definition = $this->builder->findDefinition('logger.debug');
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertSame('custom', $arguments[0]);
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $result = $this->builder->addServiceProvider($this->provider);

        self::assertSame($this->builder, $result);
        self::assertTrue($this->builder->hasDefinition(DebugConfig::class));
        self::assertTrue($this->builder->hasDefinition(ToolbarSubscriber::class));
        self::assertTrue($this->builder->hasDefinition(Profiler::class));
        self::assertTrue($this->builder->hasDefinition(ExceptionHandler::class));
        self::assertTrue($this->builder->hasDefinition(RedirectHandler::class));
        self::assertTrue($this->builder->hasDefinition(WpDieHandler::class));
        self::assertTrue($this->builder->hasDefinition(LoggerFactory::class));
        self::assertTrue($this->builder->hasDefinition('logger.debug'));
    }
}
