<?php

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
}
