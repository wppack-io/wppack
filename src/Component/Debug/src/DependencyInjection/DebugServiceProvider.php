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

namespace WpPack\Component\Debug\DependencyInjection;

use WpPack\Component\Debug\Adapter\DebugBarPanelAdapter;
use WpPack\Component\Debug\DataCollector\CacheDataCollector;
use WpPack\Component\Debug\DataCollector\DatabaseDataCollector;
use WpPack\Component\Debug\DataCollector\DumpDataCollector;
use WpPack\Component\Debug\DataCollector\EventDataCollector;
use WpPack\Component\Debug\DataCollector\HttpClientDataCollector;
use WpPack\Component\Debug\DataCollector\LoggerDataCollector;
use WpPack\Component\Debug\DataCollector\MailDataCollector;
use WpPack\Component\Debug\DataCollector\WpErrorDataCollector;
use WpPack\Component\Debug\Handler\DebugHandler;
use WpPack\Component\Debug\DataCollector\MemoryDataCollector;
use WpPack\Component\Debug\DataCollector\PluginDataCollector;
use WpPack\Component\Debug\DataCollector\RequestDataCollector;
use WpPack\Component\Debug\DataCollector\RouterDataCollector;
use WpPack\Component\Debug\DataCollector\SchedulerDataCollector;
use WpPack\Component\Debug\DataCollector\StopwatchDataCollector;
use WpPack\Component\Debug\DataCollector\ThemeDataCollector;
use WpPack\Component\Debug\DataCollector\AdminDataCollector;
use WpPack\Component\Debug\DataCollector\AjaxDataCollector;
use WpPack\Component\Debug\DataCollector\AssetDataCollector;
use WpPack\Component\Debug\DataCollector\ContainerDataCollector;
use WpPack\Component\Debug\DataCollector\EnvironmentDataCollector;
use WpPack\Component\Debug\DataCollector\FeedDataCollector;
use WpPack\Component\Debug\DataCollector\RestDataCollector;
use WpPack\Component\Debug\DataCollector\SecurityDataCollector;
use WpPack\Component\Debug\DataCollector\ShortcodeDataCollector;
use WpPack\Component\Debug\DataCollector\TranslationDataCollector;
use WpPack\Component\Debug\DataCollector\WidgetDataCollector;
use WpPack\Component\Debug\DataCollector\WordPressDataCollector;
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\ExceptionHandler;
use WpPack\Component\Debug\ErrorHandler\RedirectHandler;
use WpPack\Component\Debug\ErrorHandler\WpDieHandler;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Profiler\Profiler;
use WpPack\Component\Stopwatch\Stopwatch;
use WpPack\Component\Debug\Toolbar\Panel\CachePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DatabasePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DebugBarPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DumpPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\EventPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\HttpClientPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\LoggerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\MailPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\WpErrorPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\MemoryPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\PerformancePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\PluginPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RequestPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RouterPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\SchedulerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ThemePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\StopwatchPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\AdminPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\AjaxPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\AssetPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ContainerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\EnvironmentPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\FeedPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RestPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\SecurityPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\ShortcodePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\TranslationPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\WidgetPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\WordPressPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;
use WpPack\Component\Debug\Toolbar\ToolbarSubscriber;
use WpPack\Component\Logger\ErrorLogInterceptor;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Templating\PhpRenderer;

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
            interface_exists(\WpPack\Component\Logger\Handler\HandlerInterface::class)
            && $builder->hasDefinition(\WpPack\Component\Logger\LoggerFactory::class)
        ) {
            $builder->register(DebugHandler::class)->autowire();
            $builder->findDefinition(\WpPack\Component\Logger\LoggerFactory::class)
                ->addMethodCall('pushHandler', [new Reference(DebugHandler::class)]);
            $builder->register(LoggerDataCollector::class)
                ->addTag(RegisterDataCollectorsPass::TAG)
                ->autowire();
            $builder->register(LoggerPanelRenderer::class)
                ->addTag(RegisterPanelRenderersPass::TAG)
                ->autowire();
            if ($builder->hasDefinition(\WpPack\Component\Logger\ErrorHandler::class)) {
                $builder->findDefinition(\WpPack\Component\Logger\ErrorHandler::class)
                    ->addMethodCall('register');
            }
            // ErrorLogInterceptor: reuse singleton or fallback register
            if ($builder->hasDefinition(ErrorLogInterceptor::class)) {
                $builder->findDefinition(ErrorLogInterceptor::class)
                    ->setFactory([ErrorLogInterceptor::class, 'create']);
                if (ErrorLogInterceptor::getInstance() === null) {
                    $builder->findDefinition(ErrorLogInterceptor::class)
                        ->addMethodCall('register');
                }
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
