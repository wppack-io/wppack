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

namespace WPPack\Component\Debug\DependencyInjection;

use WPPack\Component\Debug\Adapter\DebugBarPanelAdapter;
use WPPack\Component\Debug\DataCollector\CacheDataCollector;
use WPPack\Component\Debug\DataCollector\DatabaseDataCollector;
use WPPack\Component\Debug\DataCollector\DumpDataCollector;
use WPPack\Component\Debug\DataCollector\EventDataCollector;
use WPPack\Component\Debug\DataCollector\HttpClientDataCollector;
use WPPack\Component\Debug\DataCollector\LoggerDataCollector;
use WPPack\Component\Debug\DataCollector\MailDataCollector;
use WPPack\Component\Debug\DataCollector\WpErrorDataCollector;
use WPPack\Component\Debug\Handler\DebugHandler;
use WPPack\Component\Debug\DataCollector\MemoryDataCollector;
use WPPack\Component\Debug\DataCollector\PluginDataCollector;
use WPPack\Component\Debug\DataCollector\RequestDataCollector;
use WPPack\Component\Debug\DataCollector\RouterDataCollector;
use WPPack\Component\Debug\DataCollector\SchedulerDataCollector;
use WPPack\Component\Debug\DataCollector\StopwatchDataCollector;
use WPPack\Component\Debug\DataCollector\ThemeDataCollector;
use WPPack\Component\Debug\DataCollector\AdminDataCollector;
use WPPack\Component\Debug\DataCollector\AjaxDataCollector;
use WPPack\Component\Debug\DataCollector\AssetDataCollector;
use WPPack\Component\Debug\DataCollector\ContainerDataCollector;
use WPPack\Component\Debug\DataCollector\EnvironmentDataCollector;
use WPPack\Component\Debug\DataCollector\FeedDataCollector;
use WPPack\Component\Debug\DataCollector\RestDataCollector;
use WPPack\Component\Debug\DataCollector\SecurityDataCollector;
use WPPack\Component\Debug\DataCollector\ShortcodeDataCollector;
use WPPack\Component\Debug\DataCollector\TranslationDataCollector;
use WPPack\Component\Debug\DataCollector\WidgetDataCollector;
use WPPack\Component\Debug\DataCollector\WordPressDataCollector;
use WPPack\Component\Debug\DebugConfig;
use WPPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WPPack\Component\Debug\ErrorHandler\ExceptionHandler;
use WPPack\Component\Debug\ErrorHandler\RedirectHandler;
use WPPack\Component\Debug\ErrorHandler\WpDieHandler;
use WPPack\Component\Debug\Profiler\Profile;
use WPPack\Component\Debug\Profiler\Profiler;
use WPPack\Component\Stopwatch\Stopwatch;
use WPPack\Component\Debug\Toolbar\Panel\CachePanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\DatabasePanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\DebugBarPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\DumpPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\EventPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\HttpClientPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\LoggerPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\MailPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\WpErrorPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\MemoryPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\PerformancePanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\PluginPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\RequestPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\RouterPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\SchedulerPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\ThemePanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\StopwatchPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\AdminPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\AjaxPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\AssetPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\ContainerPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\EnvironmentPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\FeedPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\RestPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\SecurityPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\ShortcodePanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\TranslationPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\WidgetPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\WordPressPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters;
use WPPack\Component\Debug\Toolbar\ToolbarRenderer;
use WPPack\Component\Debug\Toolbar\ToolbarSubscriber;
use WPPack\Component\Logger\ErrorLogInterceptor;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Templating\PhpRenderer;

final class DebugServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // Templating for Debug
        $builder->register(TemplateFormatters::class);
        $builder->register('debug.php_renderer', PhpRenderer::class)
            ->addArgument([dirname(__DIR__, 2) . '/templates']);

        // Core services
        $builder->register(DebugConfig::class);
        $builder->register(Stopwatch::class);
        $builder->register(Profiler::class)->autowire();
        $builder->register(Profile::class);
        $builder->register(ToolbarRenderer::class)->autowire();
        $builder->register(ErrorRenderer::class)->autowire();
        $builder->register(ExceptionHandler::class)->autowire();
        $builder->register(WpDieHandler::class)->autowire();
        $builder->register(RedirectHandler::class)->autowire();
        $builder->register(ToolbarSubscriber::class)->autowire();

        // Built-in collectors
        $builder->register(RequestDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(DatabaseDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(MemoryDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(StopwatchDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG)->autowire();
        $builder->register(CacheDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(WordPressDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(SecurityDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(MailDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(WpErrorDataCollector::class)
            ->setFactory([WpErrorDataCollector::class, 'fromGlobal'])
            ->addMethodCall('register')
            ->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(EventDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(RouterDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(HttpClientDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(TranslationDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(DumpDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(WidgetDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(AssetDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(AdminDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(ShortcodeDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(FeedDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(RestDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(ContainerDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(AjaxDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(EnvironmentDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(PluginDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(ThemeDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(SchedulerDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);

        // Logger Component integration (optional — requires LoggerFactory service)
        if (
            interface_exists(\WPPack\Component\Logger\Handler\HandlerInterface::class)
            && $builder->hasDefinition(\WPPack\Component\Logger\LoggerFactory::class)
        ) {
            $builder->register(DebugHandler::class)->autowire();
            $builder->findDefinition(\WPPack\Component\Logger\LoggerFactory::class)
                ->addMethodCall('pushHandler', [new Reference(DebugHandler::class)]);
            $builder->register(LoggerDataCollector::class)
                ->addTag(RegisterDataCollectorsPass::TAG)
                ->autowire();
            $builder->register(LoggerPanelRenderer::class)
                ->addTag(RegisterPanelRenderersPass::TAG)
                ->autowire();
            if ($builder->hasDefinition(\WPPack\Component\Logger\ErrorHandler::class)) {
                $builder->findDefinition(\WPPack\Component\Logger\ErrorHandler::class)
                    ->addMethodCall('register');
            }
            // ErrorLogInterceptor: wire to LoggerDataCollector for toolbar display
            if ($builder->hasDefinition(ErrorLogInterceptor::class)) {
                $builder->findDefinition(LoggerDataCollector::class)
                    ->addMethodCall('setErrorLogInterceptor', [
                        new Reference(ErrorLogInterceptor::class),
                    ]);
            }
            $builder->findDefinition(ExceptionHandler::class)
                ->setArgument('$logger', new Reference('logger.debug'));
            $builder->findDefinition(WpDieHandler::class)
                ->setArgument('$logger', new Reference('logger.debug'));
        }

        // Debug Bar adapter (only when Debug Bar plugin is active)
        if (class_exists(\Debug_Bar_Panel::class)) {
            $builder->register(DebugBarPanelAdapter::class)->addTag(RegisterDataCollectorsPass::TAG);
        }

        // Built-in panel renderers
        $builder->register(DatabasePanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(StopwatchPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(MemoryPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(RequestPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(CachePanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(WordPressPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(SecurityPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(MailPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(WpErrorPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(EventPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(RouterPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(HttpClientPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(TranslationPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(DumpPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(PluginPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(ThemePanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(SchedulerPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(WidgetPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(AssetPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(AdminPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(ShortcodePanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(FeedPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(RestPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(ContainerPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(AjaxPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(EnvironmentPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(PerformancePanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
        $builder->register(DebugBarPanelRenderer::class)->addTag(RegisterPanelRenderersPass::TAG)->autowire();
    }
}
