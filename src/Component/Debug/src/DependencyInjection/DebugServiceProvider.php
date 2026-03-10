<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DependencyInjection;

use WpPack\Component\Debug\Adapter\DebugBarPanelAdapter;
use WpPack\Component\Debug\Adapter\QueryMonitorCollectorAdapter;
use WpPack\Component\Debug\DataCollector\CacheDataCollector;
use WpPack\Component\Debug\DataCollector\DatabaseDataCollector;
use WpPack\Component\Debug\DataCollector\MemoryDataCollector;
use WpPack\Component\Debug\DataCollector\RequestDataCollector;
use WpPack\Component\Debug\DataCollector\TimeDataCollector;
use WpPack\Component\Debug\DataCollector\WordPressDataCollector;
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\ExceptionHandler;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Profiler\Profiler;
use WpPack\Component\Debug\Profiler\Stopwatch;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;
use WpPack\Component\Debug\Toolbar\ToolbarSubscriber;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;

final class DebugServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // Core services
        $builder->register(DebugConfig::class);
        $builder->register(Stopwatch::class);
        $builder->register(Profiler::class)->autowire();
        $builder->register(Profile::class);
        $builder->register(ToolbarRenderer::class);
        $builder->register(ErrorRenderer::class);
        $builder->register(ExceptionHandler::class)->autowire();
        $builder->register(ToolbarSubscriber::class)->autowire();

        // Built-in collectors
        $builder->register(RequestDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(DatabaseDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(MemoryDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(TimeDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG)->autowire();
        $builder->register(CacheDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(WordPressDataCollector::class)->addTag(RegisterDataCollectorsPass::TAG);

        // Adapters
        $builder->register(DebugBarPanelAdapter::class)->addTag(RegisterDataCollectorsPass::TAG);
        $builder->register(QueryMonitorCollectorAdapter::class)->addTag(RegisterDataCollectorsPass::TAG);
    }
}
