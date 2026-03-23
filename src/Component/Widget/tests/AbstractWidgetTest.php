<?php

declare(strict_types=1);

namespace WpPack\Component\Widget\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Templating\TemplateRendererInterface;
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
    public function resolvesLabelFromAttribute(): void
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
    public function invokeIsCalledByWidget(): void
    {
        $widget = new ConcreteTestWidget();

        ob_start();
        $widget->widget(['before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => ''], []);
        $output = ob_get_clean();

        self::assertSame('<p>rendered</p>', $output);
    }

    #[Test]
    public function invokeReturnsString(): void
    {
        $widget = new ConcreteTestWidget();

        self::assertSame('<p>rendered</p>', $widget([], []));
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

        // Widget without configure() method does nothing
        self::assertSame('', $output);
    }

    #[Test]
    public function widgetPassesArgsAndInstanceToInvoke(): void
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

    #[Test]
    public function widgetResolvesRequestArgument(): void
    {
        $request = new Request(query: ['tab' => 'advanced']);
        $widget = new RequestInjectTestWidget();
        $widget->setInvokeArgumentResolver(static fn() => [$request]);

        ob_start();
        $widget->widget(
            ['before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => ''],
            [],
        );
        $output = ob_get_clean();

        self::assertSame('advanced', $output);
    }

    #[Test]
    public function widgetResolvesCurrentUserArgument(): void
    {
        wp_set_current_user(1);
        $user = wp_get_current_user();

        $widget = new CurrentUserInjectTestWidget();
        $widget->setInvokeArgumentResolver(static fn() => [$user]);

        ob_start();
        $widget->widget(
            ['before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => ''],
            [],
        );
        $output = ob_get_clean();

        self::assertSame($user->display_name, $output);
    }

    #[Test]
    public function renderDelegatesToTemplateRenderer(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects(self::once())
            ->method('render')
            ->with('widget/test.html.twig', ['stat' => 42])
            ->willReturn('<p>rendered</p>');

        $widget = new TemplatingTestWidget();
        $widget->setTemplateRenderer($renderer);

        self::assertSame('<p>rendered</p>', $widget([], []));
    }

    #[Test]
    public function renderThrowsLogicExceptionWithoutRenderer(): void
    {
        $widget = new TemplatingTestWidget();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('TemplateRendererInterface is not available');

        $widget([], []);
    }

    #[Test]
    public function formEchoesConfigureReturnValue(): void
    {
        $widget = new ConfigurableTestWidget();

        ob_start();
        $widget->form(['title' => 'Test']);
        $output = ob_get_clean();

        self::assertSame('<input type="text" name="setting">', $output);
    }

    #[Test]
    public function formResolvesRequestArgument(): void
    {
        $request = new Request(query: ['tab' => 'advanced']);
        $widget = new ConfigureRequestInjectTestWidget();
        $widget->setConfigureArgumentResolver(static fn() => [$request]);

        ob_start();
        $widget->form(['title' => 'Test']);
        $output = ob_get_clean();

        self::assertSame('advanced', $output);
    }

    #[Test]
    public function formWithConfigureAndRender(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects(self::once())
            ->method('render')
            ->with('widget/form.html.twig', ['instance' => ['title' => 'Test']])
            ->willReturn('<p>configure rendered</p>');

        $widget = new ConfigureTemplatingTestWidget();
        $widget->setTemplateRenderer($renderer);

        ob_start();
        $widget->form(['title' => 'Test']);
        $output = ob_get_clean();

        self::assertSame('<p>configure rendered</p>', $output);
    }
}

#[AsWidget(id: 'test_widget', label: 'Test Widget', description: 'A test widget')]
class ConcreteTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        return '<p>rendered</p>';
    }
}

#[AsWidget(id: 'context_widget', label: 'Context Widget', description: 'A context-aware widget')]
class ContextAwareTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        return '<p>' . ($instance['title'] ?? 'no title') . '</p>';
    }
}

class NoAttributeTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        return '';
    }
}

#[AsWidget(id: 'request_inject_widget', label: 'Request Inject Widget')]
class RequestInjectTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance, Request $request): string
    {
        return $request->query->get('tab', 'default');
    }
}

#[AsWidget(id: 'user_inject_widget', label: 'User Inject Widget')]
class CurrentUserInjectTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance, #[CurrentUser] \WP_User $user): string
    {
        return $user->display_name;
    }
}

#[AsWidget(id: 'templating_widget', label: 'Templating Widget')]
class TemplatingTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        return $this->render('widget/test.html.twig', ['stat' => 42]);
    }
}

#[AsWidget(id: 'configurable_widget', label: 'Configurable Widget')]
class ConfigurableTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        return '<p>configurable widget</p>';
    }

    public function configure(array $instance): string
    {
        return '<input type="text" name="setting">';
    }
}

#[AsWidget(id: 'configure_request_inject_widget', label: 'Configure Request Inject')]
class ConfigureRequestInjectTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        return '';
    }

    public function configure(array $instance, Request $request): string
    {
        return $request->query->get('tab', 'default');
    }
}

#[AsWidget(id: 'configure_templating_widget', label: 'Configure Templating')]
class ConfigureTemplatingTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        return '';
    }

    public function configure(array $instance): string
    {
        return $this->render('widget/form.html.twig', ['instance' => $instance]);
    }
}
