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

namespace WpPack\Component\Debug\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DependencyInjection\RegisterPanelRenderersPass;
use WpPack\Component\Debug\Tests\DependencyInjection\Fixtures\DefaultPriorityRenderer;
use WpPack\Component\Debug\Tests\DependencyInjection\Fixtures\HighPriorityRenderer;
use WpPack\Component\Debug\Tests\DependencyInjection\Fixtures\LowPriorityRenderer;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;

final class RegisterPanelRenderersPassTest extends TestCase
{
    #[Test]
    public function processReturnsEarlyWhenDefinitionMissing(): void
    {
        $builder = new ContainerBuilder();
        $pass = new RegisterPanelRenderersPass();

        // ToolbarRenderer::class is not registered, so process() should return early
        $pass->process($builder);

        // No definitions were modified — nothing to assert beyond no exception thrown
        self::assertFalse($builder->hasDefinition(ToolbarRenderer::class));
    }

    #[Test]
    public function processRegistersTaggedServices(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(ToolbarRenderer::class, ToolbarRenderer::class);
        $builder->register(HighPriorityRenderer::class, HighPriorityRenderer::class);

        $pass = new RegisterPanelRenderersPass();
        $pass->process($builder);

        $toolbarDefinition = $builder->findDefinition(ToolbarRenderer::class);
        $methodCalls = $toolbarDefinition->getMethodCalls();

        self::assertCount(1, $methodCalls);
        self::assertSame('addPanelRenderer', $methodCalls[0]['method']);
        self::assertCount(1, $methodCalls[0]['arguments']);

        $reference = $methodCalls[0]['arguments'][0];
        self::assertInstanceOf(Reference::class, $reference);
        self::assertSame(HighPriorityRenderer::class, $reference->getId());
    }

    #[Test]
    public function processSortsByPriority(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(ToolbarRenderer::class, ToolbarRenderer::class);

        // Register in non-priority order
        $builder->register(LowPriorityRenderer::class, LowPriorityRenderer::class);
        $builder->register(HighPriorityRenderer::class, HighPriorityRenderer::class);
        $builder->register(DefaultPriorityRenderer::class, DefaultPriorityRenderer::class);

        $pass = new RegisterPanelRenderersPass();
        $pass->process($builder);

        $toolbarDefinition = $builder->findDefinition(ToolbarRenderer::class);
        $methodCalls = $toolbarDefinition->getMethodCalls();

        self::assertCount(3, $methodCalls);

        // Sorted by priority descending: 100, 0, -10
        $ids = array_map(
            static fn(array $call): string => $call['arguments'][0]->getId(),
            $methodCalls,
        );

        self::assertSame([
            HighPriorityRenderer::class,    // priority 100
            DefaultPriorityRenderer::class, // priority 0
            LowPriorityRenderer::class,     // priority -10
        ], $ids);

        // All method calls should be addPanelRenderer
        foreach ($methodCalls as $call) {
            self::assertSame('addPanelRenderer', $call['method']);
        }
    }

    #[Test]
    public function processSkipsNonExistentClasses(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(ToolbarRenderer::class, ToolbarRenderer::class);

        // Register a definition with a non-existent class
        $builder->register('non_existent_service', 'App\\NonExistent\\FakeRenderer');

        // Register a valid renderer
        $builder->register(HighPriorityRenderer::class, HighPriorityRenderer::class);

        $pass = new RegisterPanelRenderersPass();
        $pass->process($builder);

        $toolbarDefinition = $builder->findDefinition(ToolbarRenderer::class);
        $methodCalls = $toolbarDefinition->getMethodCalls();

        // Only the valid renderer should be registered
        self::assertCount(1, $methodCalls);
        self::assertSame(HighPriorityRenderer::class, $methodCalls[0]['arguments'][0]->getId());
    }

    #[Test]
    public function processRegistersServiceWithTagOnly(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(ToolbarRenderer::class, ToolbarRenderer::class);

        // Register a renderer using tag (HighPriorityRenderer also has the attribute,
        // but the tag detection path is also exercised)
        $builder->register(HighPriorityRenderer::class, HighPriorityRenderer::class)
            ->addTag(RegisterPanelRenderersPass::TAG);

        $pass = new RegisterPanelRenderersPass();
        $pass->process($builder);

        $toolbarDefinition = $builder->findDefinition(ToolbarRenderer::class);
        $methodCalls = $toolbarDefinition->getMethodCalls();

        self::assertCount(1, $methodCalls);
        self::assertSame('addPanelRenderer', $methodCalls[0]['method']);
    }

    #[Test]
    public function tagConstantHasExpectedValue(): void
    {
        self::assertSame('debug.panel_renderer', RegisterPanelRenderersPass::TAG);
    }
}
