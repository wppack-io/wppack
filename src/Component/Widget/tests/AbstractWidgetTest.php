<?php

declare(strict_types=1);

namespace WpPack\Component\Widget\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Widget\AbstractWidget;
use WpPack\Component\Widget\Attribute\AsWidget;

final class AbstractWidgetTest extends TestCase
{
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

    #[Test]
    public function formDoesNothingByDefault(): void
    {
        $widget = new ConcreteTestWidget();

        ob_start();
        $widget->form(['title' => 'Test']);
        $output = ob_get_clean();

        // Default form() implementation does nothing
        self::assertSame('', $output);
    }

    #[Test]
    public function widgetPassesArgsAndInstanceToRender(): void
    {
        $widget = new ContextAwareTestWidget();

        ob_start();
        $widget->widget(
            ['before_widget' => '<div>', 'after_widget' => '</div>', 'before_title' => '', 'after_title' => ''],
            ['title' => 'My Title'],
        );
        $output = ob_get_clean();

        self::assertStringContainsString('My Title', $output);
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

#[AsWidget(id: 'context_widget', name: 'Context Widget', description: 'A context-aware widget')]
class ContextAwareTestWidget extends AbstractWidget
{
    protected function render(array $args, array $instance): string
    {
        return '<p>' . ($instance['title'] ?? 'no title') . '</p>';
    }
}

class NoAttributeTestWidget extends AbstractWidget
{
    protected function render(array $args, array $instance): string
    {
        return '';
    }
}
