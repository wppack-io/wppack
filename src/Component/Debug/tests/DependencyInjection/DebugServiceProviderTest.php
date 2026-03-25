<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\Adapter\DebugBarPanelAdapter;
use WpPack\Component\Debug\DataCollector\AdminDataCollector;
use WpPack\Component\Debug\DataCollector\AjaxDataCollector;
use WpPack\Component\Debug\DataCollector\AssetDataCollector;
use WpPack\Component\Debug\DataCollector\CacheDataCollector;
use WpPack\Component\Debug\DataCollector\ContainerDataCollector;
use WpPack\Component\Debug\DataCollector\DatabaseDataCollector;
use WpPack\Component\Debug\DataCollector\DumpDataCollector;
use WpPack\Component\Debug\DataCollector\EnvironmentDataCollector;
use WpPack\Component\Debug\DataCollector\EventDataCollector;
use WpPack\Component\Debug\DataCollector\FeedDataCollector;
use WpPack\Component\Debug\DataCollector\HttpClientDataCollector;
use WpPack\Component\Debug\DataCollector\LoggerDataCollector;
use WpPack\Component\Debug\DataCollector\MailDataCollector;
use WpPack\Component\Debug\DataCollector\MemoryDataCollector;
use WpPack\Component\Debug\DataCollector\PluginDataCollector;
use WpPack\Component\Debug\DataCollector\RequestDataCollector;
use WpPack\Component\Debug\DataCollector\RestDataCollector;
use WpPack\Component\Debug\DataCollector\RouterDataCollector;
use WpPack\Component\Debug\DataCollector\SchedulerDataCollector;
use WpPack\Component\Debug\DataCollector\SecurityDataCollector;
use WpPack\Component\Debug\DataCollector\ShortcodeDataCollector;
use WpPack\Component\Debug\DataCollector\StopwatchDataCollector;
use WpPack\Component\Debug\DataCollector\ThemeDataCollector;
use WpPack\Component\Debug\DataCollector\TranslationDataCollector;
use WpPack\Component\Debug\DataCollector\WidgetDataCollector;
use WpPack\Component\Debug\DataCollector\WordPressDataCollector;
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\DependencyInjection\DebugServiceProvider;
use WpPack\Component\Debug\DependencyInjection\RegisterDataCollectorsPass;
use WpPack\Component\Debug\DependencyInjection\RegisterPanelRenderersPass;
use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\ExceptionHandler;
use WpPack\Component\Debug\ErrorHandler\WpDieHandler;
use WpPack\Component\Debug\Handler\DebugHandler;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Profiler\Profiler;
use WpPack\Component\Debug\Toolbar\Panel\AdminPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\AjaxPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\AssetPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\CachePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ContainerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DatabasePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DumpPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\EnvironmentPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\EventPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\FeedPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\HttpClientPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\LoggerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\MailPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\MemoryPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\PerformancePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\PluginPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RequestPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RestPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RouterPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\SchedulerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\SecurityPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ShortcodePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\StopwatchPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ThemePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\TranslationPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\WidgetPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\WordPressPanelRenderer;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;
use WpPack\Component\Debug\Toolbar\ToolbarSubscriber;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Stopwatch\Stopwatch;

final class DebugServiceProviderTest extends TestCase
{
    private ContainerBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ContainerBuilder();
        $provider = new DebugServiceProvider();
        $provider->register($this->builder);
    }

    #[Test]
    public function registerRegistersAllCoreServices(): void
    {
        $coreServices = [
            DebugConfig::class,
            Stopwatch::class,
            Profiler::class,
            Profile::class,
            ToolbarRenderer::class,
            ErrorRenderer::class,
            ExceptionHandler::class,
            WpDieHandler::class,
            ToolbarSubscriber::class,
        ];

        foreach ($coreServices as $serviceId) {
            self::assertTrue(
                $this->builder->hasDefinition($serviceId),
                sprintf('Core service "%s" should be registered.', $serviceId),
            );
        }
    }

    #[Test]
    public function registerTagsCollectorsCorrectly(): void
    {
        $expectedCollectors = [
            RequestDataCollector::class,
            DatabaseDataCollector::class,
            MemoryDataCollector::class,
            StopwatchDataCollector::class,
            CacheDataCollector::class,
            WordPressDataCollector::class,
            SecurityDataCollector::class,
            MailDataCollector::class,
            EventDataCollector::class,
            RouterDataCollector::class,
            HttpClientDataCollector::class,
            TranslationDataCollector::class,
            DumpDataCollector::class,
            WidgetDataCollector::class,
            AssetDataCollector::class,
            AdminDataCollector::class,
            ShortcodeDataCollector::class,
            FeedDataCollector::class,
            RestDataCollector::class,
            ContainerDataCollector::class,
            AjaxDataCollector::class,
            EnvironmentDataCollector::class,
            PluginDataCollector::class,
            ThemeDataCollector::class,
            SchedulerDataCollector::class,
        ];

        $taggedIds = $this->builder->findTaggedServiceIds(RegisterDataCollectorsPass::TAG);

        foreach ($expectedCollectors as $collectorClass) {
            self::assertArrayHasKey(
                $collectorClass,
                $taggedIds,
                sprintf('Collector "%s" should be tagged with "%s".', $collectorClass, RegisterDataCollectorsPass::TAG),
            );
        }
    }

    #[Test]
    public function registerTagsPanelRenderersCorrectly(): void
    {
        $expectedRenderers = [
            DatabasePanelRenderer::class,
            StopwatchPanelRenderer::class,
            MemoryPanelRenderer::class,
            RequestPanelRenderer::class,
            CachePanelRenderer::class,
            WordPressPanelRenderer::class,
            SecurityPanelRenderer::class,
            MailPanelRenderer::class,
            EventPanelRenderer::class,
            RouterPanelRenderer::class,
            HttpClientPanelRenderer::class,
            TranslationPanelRenderer::class,
            DumpPanelRenderer::class,
            PluginPanelRenderer::class,
            ThemePanelRenderer::class,
            SchedulerPanelRenderer::class,
            WidgetPanelRenderer::class,
            AssetPanelRenderer::class,
            AdminPanelRenderer::class,
            ShortcodePanelRenderer::class,
            FeedPanelRenderer::class,
            RestPanelRenderer::class,
            ContainerPanelRenderer::class,
            AjaxPanelRenderer::class,
            EnvironmentPanelRenderer::class,
            PerformancePanelRenderer::class,
        ];

        $taggedIds = $this->builder->findTaggedServiceIds(RegisterPanelRenderersPass::TAG);

        foreach ($expectedRenderers as $rendererClass) {
            self::assertArrayHasKey(
                $rendererClass,
                $taggedIds,
                sprintf('Panel renderer "%s" should be tagged with "%s".', $rendererClass, RegisterPanelRenderersPass::TAG),
            );
        }
    }

    #[Test]
    public function registerRegistersDebugBarAdapterWhenPluginActive(): void
    {
        // Debug Bar adapter is only registered when Debug_Bar_Panel class exists
        // In test environment it does not exist, so it should not be registered
        if (class_exists(\Debug_Bar_Panel::class)) {
            self::assertTrue(
                $this->builder->hasDefinition(DebugBarPanelAdapter::class),
                'DebugBarPanelAdapter should be registered when Debug Bar is active.',
            );
        } else {
            self::assertFalse(
                $this->builder->hasDefinition(DebugBarPanelAdapter::class),
                'DebugBarPanelAdapter should not be registered when Debug Bar is inactive.',
            );
        }
    }

    #[Test]
    public function registerRegistersDebugHandlerWhenLoggerFactoryAvailable(): void
    {
        if (!interface_exists(\WpPack\Component\Logger\Handler\HandlerInterface::class)) {
            self::markTestSkipped('Logger component HandlerInterface is not available.');
        }

        // Re-register with LoggerFactory pre-registered
        $builder = new ContainerBuilder();
        $builder->register(\WpPack\Component\Logger\LoggerFactory::class);
        $provider = new DebugServiceProvider();
        $provider->register($builder);

        self::assertTrue(
            $builder->hasDefinition(DebugHandler::class),
            'DebugHandler should be registered when LoggerFactory service is available.',
        );

        $definition = $builder->findDefinition(DebugHandler::class);
        self::assertTrue(
            $definition->isAutowired(),
            'DebugHandler should be autowired.',
        );
    }

    #[Test]
    public function registerSkipsLoggerIntegrationWhenLoggerFactoryMissing(): void
    {
        // Default builder has no LoggerFactory registered
        self::assertFalse(
            $this->builder->hasDefinition(DebugHandler::class),
            'DebugHandler should not be registered when LoggerFactory service is missing.',
        );
    }

    #[Test]
    public function registerAutowiresCorrectServices(): void
    {
        $autowiredServices = [
            Profiler::class,
            ExceptionHandler::class,
            WpDieHandler::class,
            ToolbarSubscriber::class,
            StopwatchDataCollector::class,
        ];

        foreach ($autowiredServices as $serviceId) {
            $definition = $this->builder->findDefinition($serviceId);
            self::assertTrue(
                $definition->isAutowired(),
                sprintf('Service "%s" should be autowired.', $serviceId),
            );
        }
    }
}
