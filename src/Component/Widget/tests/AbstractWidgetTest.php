<?php

declare(strict_types=1);

namespace WpPack\Component\Widget\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Widget\AbstractWidget;
use WpPack\Component\Widget\Attribute\AsWidget;

final class AbstractWidgetTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\WP_Widget::class)) {
            self::markTestSkipped('WP_Widget class is not available.');
        }
    }

    #[Test]
    public function resolvesIdFromAttribute(): void
    {
        $widget = new ConcreteTestWidget();

        self::assertSame('test_widget', $widget->id_base);
    }

    #[Test]
    public function resolvesNameFromAttribute(): void
    {
        $widget = new ConcreteTestWidget();

        self::assertSame('Test Widget', $widget->name);
    }

    #[Test]
    public function resolvesDescriptionFromAttribute(): void
    {
        $widget = new ConcreteTestWidget();

        self::assertSame('A test widget', $widget->widget_options['description']);
    }

    #[Test]
    public function renderIsCalledByWidget(): void
    {
        $widget = new ConcreteTestWidget();

        ob_start();
        $widget->widget(['before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => ''], []);
        $output = ob_get_clean();

        self::assertSame('<p>rendered</p>', $output);
    }

    #[Test]
    public function updateReturnsNewInstance(): void
    {
        $widget = new ConcreteTestWidget();

        $result = $widget->update(['title' => 'New'], ['title' => 'Old']);

        self::assertSame(['title' => 'New'], $result);
    }

    #[Test]
    public function throwsLogicExceptionWithoutAttribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have the #[AsWidget] attribute');

        new NoAttributeTestWidget();
    }
}

#[AsWidget(id: 'test_widget', name: 'Test Widget', description: 'A test widget')]
class ConcreteTestWidget extends AbstractWidget
{
    protected function render(array $args, array $instance): string
    {
        return '<p>rendered</p>';
    }
}

class NoAttributeTestWidget extends AbstractWidget
{
    protected function render(array $args, array $instance): string
    {
        return '';
    }
}
