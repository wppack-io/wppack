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

namespace WPPack\Component\Hook\Tests\Attribute\Block;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Hook\Attribute\Block\Action\EnqueueBlockAssetsAction;
use WPPack\Component\Hook\Attribute\Block\Action\EnqueueBlockEditorAssetsAction;
use WPPack\Component\Hook\Attribute\Block\Filter\BlockCategoriesAllFilter;
use WPPack\Component\Hook\Attribute\Block\Filter\BlockEditorSettingsAllFilter;
use WPPack\Component\Hook\Attribute\Block\Filter\BlockTypeMetadataFilter;
use WPPack\Component\Hook\Attribute\Block\Filter\BlockTypeMetadataSettingsFilter;
use WPPack\Component\Hook\Attribute\Block\Filter\PreRenderBlockFilter;
use WPPack\Component\Hook\Attribute\Block\Filter\RegisterBlockTypeArgsFilter;
use WPPack\Component\Hook\Attribute\Block\Filter\RenderBlockDataFilter;
use WPPack\Component\Hook\Attribute\Block\Filter\RenderBlockFilter;
use WPPack\Component\Hook\Attribute\Block\Filter\RestPreInsertBlockFilter;
use WPPack\Component\Hook\Attribute\Block\Filter\RestPrepareBlockFilter;
use WPPack\Component\Hook\Attribute\Filter;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function enqueueBlockAssetsActionHasCorrectHookName(): void
    {
        $action = new EnqueueBlockAssetsAction();

        self::assertSame('enqueue_block_assets', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function enqueueBlockAssetsActionAcceptsCustomPriority(): void
    {
        $action = new EnqueueBlockAssetsAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function enqueueBlockEditorAssetsActionHasCorrectHookName(): void
    {
        $action = new EnqueueBlockEditorAssetsAction();

        self::assertSame('enqueue_block_editor_assets', $action->hook);
    }

    #[Test]
    public function blockCategoriesAllFilterHasCorrectHookName(): void
    {
        $filter = new BlockCategoriesAllFilter();

        self::assertSame('block_categories_all', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function blockEditorSettingsAllFilterHasCorrectHookName(): void
    {
        $filter = new BlockEditorSettingsAllFilter();

        self::assertSame('block_editor_settings_all', $filter->hook);
    }

    #[Test]
    public function blockTypeMetadataFilterHasCorrectHookName(): void
    {
        $filter = new BlockTypeMetadataFilter();

        self::assertSame('block_type_metadata', $filter->hook);
    }

    #[Test]
    public function blockTypeMetadataSettingsFilterHasCorrectHookName(): void
    {
        $filter = new BlockTypeMetadataSettingsFilter();

        self::assertSame('block_type_metadata_settings', $filter->hook);
    }

    #[Test]
    public function preRenderBlockFilterHasCorrectHookName(): void
    {
        $filter = new PreRenderBlockFilter();

        self::assertSame('pre_render_block', $filter->hook);
    }

    #[Test]
    public function registerBlockTypeArgsFilterHasCorrectHookName(): void
    {
        $filter = new RegisterBlockTypeArgsFilter();

        self::assertSame('register_block_type_args', $filter->hook);
    }

    #[Test]
    public function renderBlockDataFilterHasCorrectHookName(): void
    {
        $filter = new RenderBlockDataFilter();

        self::assertSame('render_block_data', $filter->hook);
    }

    #[Test]
    public function renderBlockFilterHasCorrectHookName(): void
    {
        $filter = new RenderBlockFilter();

        self::assertSame('render_block', $filter->hook);
    }

    #[Test]
    public function restPreInsertBlockFilterHasCorrectHookName(): void
    {
        $filter = new RestPreInsertBlockFilter();

        self::assertSame('rest_pre_insert_block', $filter->hook);
    }

    #[Test]
    public function restPrepareBlockFilterHasCorrectHookName(): void
    {
        $filter = new RestPrepareBlockFilter();

        self::assertSame('rest_prepare_block', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new EnqueueBlockAssetsAction());
        self::assertInstanceOf(Action::class, new EnqueueBlockEditorAssetsAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new BlockCategoriesAllFilter());
        self::assertInstanceOf(Filter::class, new BlockEditorSettingsAllFilter());
        self::assertInstanceOf(Filter::class, new BlockTypeMetadataFilter());
        self::assertInstanceOf(Filter::class, new BlockTypeMetadataSettingsFilter());
        self::assertInstanceOf(Filter::class, new PreRenderBlockFilter());
        self::assertInstanceOf(Filter::class, new RegisterBlockTypeArgsFilter());
        self::assertInstanceOf(Filter::class, new RenderBlockDataFilter());
        self::assertInstanceOf(Filter::class, new RenderBlockFilter());
        self::assertInstanceOf(Filter::class, new RestPreInsertBlockFilter());
        self::assertInstanceOf(Filter::class, new RestPrepareBlockFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[EnqueueBlockAssetsAction]
            public function onEnqueueAssets(): void {}

            #[RenderBlockFilter(priority: 5)]
            public function onRenderBlock(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onEnqueueAssets');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('enqueue_block_assets', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onRenderBlock');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('render_block', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
