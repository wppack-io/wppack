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

namespace WpPack\Component\Debug\Tests\Compat;

use Debug_Bar;
use Debug_Bar_Panel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DebugBarCompatTest extends TestCase
{
    #[Test]
    public function debugBarPanelClassExists(): void
    {
        self::assertTrue(class_exists(Debug_Bar_Panel::class));
    }

    #[Test]
    public function debugBarClassExists(): void
    {
        self::assertTrue(class_exists(Debug_Bar::class));
    }

    #[Test]
    public function panelCanBeInstantiatedWithTitle(): void
    {
        $panel = new Debug_Bar_Panel('My Panel');

        self::assertSame('My Panel', $panel->title());
    }

    #[Test]
    public function panelTitleGetterAndSetter(): void
    {
        $panel = new Debug_Bar_Panel();

        self::assertSame('', $panel->title());

        $panel->title('Updated');

        self::assertSame('Updated', $panel->title());
    }

    #[Test]
    public function panelIsVisibleByDefault(): void
    {
        $panel = new Debug_Bar_Panel();

        self::assertTrue($panel->is_visible());
    }

    #[Test]
    public function panelSetVisible(): void
    {
        $panel = new Debug_Bar_Panel();

        $panel->set_visible(false);

        self::assertFalse($panel->is_visible());

        $panel->set_visible(true);

        self::assertTrue($panel->is_visible());
    }

    #[Test]
    public function panelCanBeExtended(): void
    {
        $subclass = new class ('Sub Panel') extends Debug_Bar_Panel {
            public function render(): void
            {
                echo 'rendered';
            }
        };

        self::assertInstanceOf(Debug_Bar_Panel::class, $subclass);
        self::assertSame('Sub Panel', $subclass->title());
    }

    #[Test]
    public function panelRenderAndPrerenderAreCallable(): void
    {
        $panel = new Debug_Bar_Panel();

        $panel->prerender();
        $panel->render();

        // No exception means success — these are no-op methods.
        self::assertTrue(true);
    }

    #[Test]
    public function panelDebugBarClassesReturnsInput(): void
    {
        $panel = new Debug_Bar_Panel();
        $classes = ['class-a', 'class-b'];

        self::assertSame($classes, $panel->debug_bar_classes($classes));
    }

    #[Test]
    public function debugBarCanBeInstantiated(): void
    {
        $bar = new Debug_Bar();

        self::assertInstanceOf(Debug_Bar::class, $bar);
    }

    #[Test]
    public function debugBarPanelsIsArray(): void
    {
        $bar = new Debug_Bar();

        self::assertIsArray($bar->panels);
        self::assertSame([], $bar->panels);
    }

    #[Test]
    public function debugBarEnqueueIsCallable(): void
    {
        $bar = new Debug_Bar();

        // enqueue() is a no-op stub — should not throw
        $bar->enqueue();

        self::assertTrue(true);
    }

    #[Test]
    public function debugBarInitPanelsUsesFilter(): void
    {
        $mockPanel = new Debug_Bar_Panel('Filter Panel');

        $callback = static fn(array $panels): array => array_merge($panels, [$mockPanel]);
        add_filter('debug_bar_panels', $callback, 10, 1);

        try {
            $bar = new Debug_Bar();
            $bar->init_panels();

            self::assertCount(1, $bar->panels);
            self::assertSame('Filter Panel', $bar->panels[0]->title());
        } finally {
            remove_filter('debug_bar_panels', $callback, 10);
        }
    }

    #[Test]
    public function panelInitReturnsNull(): void
    {
        $panel = new Debug_Bar_Panel();

        // init() should return null by default (void)
        $result = $panel->init();

        self::assertNull($result);
    }

    #[Test]
    public function panelConstructorWithFalseInit(): void
    {
        // When init() returns false, filter should not be registered
        $panel = new class ('Init False Panel') extends Debug_Bar_Panel {
            public function init(): bool
            {
                return false;
            }
        };

        self::assertSame('Init False Panel', $panel->title());
    }

    #[Test]
    public function panelConstructorWithDefaultTitle(): void
    {
        $panel = new Debug_Bar_Panel();

        self::assertSame('', $panel->title());
    }
}
