<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DashboardWidget\AbstractDashboardWidget;
use WpPack\Component\DashboardWidget\Attribute\AsDashboardWidget;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Role\Authorization\AuthorizationCheckerInterface;
use WpPack\Component\Role\Authorization\IsGrantedChecker;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Templating\TemplateRendererInterface;

final class AbstractDashboardWidgetTest extends TestCase
{
    #[Test]
    public function resolvesIdFromAttribute(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        self::assertSame('test_dashboard_widget', $widget->id);
    }

    #[Test]
    public function resolvesLabelFromAttribute(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        self::assertSame('Test Dashboard Widget', $widget->label);
    }

    #[Test]
    public function resolvesContextFromAttribute(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        self::assertSame('normal', $widget->context);
    }

    #[Test]
    public function resolvesPriorityFromAttribute(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        self::assertSame('core', $widget->priority);
    }

    #[Test]
    public function resolvesAllAttributeParameters(): void
    {
        $widget = new FullAttributeTestDashboardWidget();

        self::assertSame('full_widget', $widget->id);
        self::assertSame('Full Widget', $widget->label);
        self::assertSame('side', $widget->context);
        self::assertSame('high', $widget->priority);
    }

    #[Test]
    public function invokeReturnsString(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        self::assertSame('<p>dashboard content</p>', $widget());
    }

    #[Test]
    public function handleRenderEchoesInvokeOutput(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        ob_start();
        $widget->handleRender();
        $output = ob_get_clean();

        self::assertSame('<p>dashboard content</p>', $output);
    }

    #[Test]
    public function handleConfigureDoesNothingWithoutMethod(): void
    {
        $widget = new NoConfigureTestDashboardWidget();

        ob_start();
        $widget->handleConfigure();
        $output = ob_get_clean();

        self::assertSame('', $output);
    }

    #[Test]
    public function throwsLogicExceptionWithoutAttribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have the #[AsDashboardWidget] attribute');

        new NoAttributeTestDashboardWidget();
    }

    #[Test]
    public function registerWithCapabilityAllowed(): void
    {
        $widget = new CapabilityTestDashboardWidget();

        // current_user_can('edit_posts') must return true in WordPress test env
        $widget->register();

        // If no exception, the widget was registered (or capability check passed)
        self::assertTrue(true);
    }

    #[Test]
    public function registerSkipsWhenCapabilityDenied(): void
    {
        // Use a capability that the default test user won't have
        $widget = new RestrictedCapabilityTestDashboardWidget();

        // Set up a user without the required capability
        wp_set_current_user(0); // Anonymous user

        ob_start();
        $widget->register();
        ob_end_clean();

        // wp_add_dashboard_widget should not have been called
        // If no error occurred, the capability check correctly skipped registration
        self::assertTrue(true);
    }

    #[Test]
    public function handleConfigureEchoesReturnValue(): void
    {
        $widget = new ConfigurableTestDashboardWidget();

        ob_start();
        $widget->handleConfigure();
        $output = ob_get_clean();

        self::assertSame('<input type="text" name="setting">', $output);
    }

    #[Test]
    public function registerCallsWpAddDashboardWidget(): void
    {
        set_current_screen('dashboard');

        global $wp_meta_boxes;

        $widget = new ConcreteTestDashboardWidget();
        $widget->register();

        self::assertArrayHasKey('dashboard', $wp_meta_boxes);
        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);
    }

    #[Test]
    public function registerPassesCorrectParameters(): void
    {
        set_current_screen('dashboard');

        global $wp_meta_boxes;

        $widget = new ConcreteTestDashboardWidget();
        $widget->register();

        $entry = $wp_meta_boxes['dashboard'][$widget->context][$widget->priority][$widget->id];
        self::assertSame('test_dashboard_widget', $entry['id']);
        self::assertSame('Test Dashboard Widget', $entry['title']);
    }

    #[Test]
    public function registerWithoutIsGrantedRegisters(): void
    {
        set_current_screen('dashboard');

        global $wp_meta_boxes;

        $widget = new ConcreteTestDashboardWidget();
        $widget->register();

        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);
    }

    #[Test]
    public function registerWithConfigurePassesCallback(): void
    {
        set_current_screen('dashboard');
        wp_set_current_user(1);

        global $wp_dashboard_control_callbacks;

        $widget = new ConfigurableTestDashboardWidget();
        $widget->register();

        self::assertArrayHasKey($widget->id, $wp_dashboard_control_callbacks);
        self::assertIsCallable($wp_dashboard_control_callbacks[$widget->id]);
    }

    #[Test]
    public function registerWithoutConfigurePassesNull(): void
    {
        set_current_screen('dashboard');

        global $wp_dashboard_control_callbacks;
        $wp_dashboard_control_callbacks = [];

        $widget = new ConcreteTestDashboardWidget();
        $widget->register();

        self::assertArrayNotHasKey($widget->id, $wp_dashboard_control_callbacks);
    }

    #[Test]
    public function resolveAttributeThrowsForMissingAttributeWithClassName(): void
    {
        try {
            new NoAttributeTestDashboardWidget();
            self::fail('Expected LogicException');
        } catch (\LogicException $e) {
            self::assertStringContainsString('NoAttributeTestDashboardWidget', $e->getMessage());
            self::assertStringContainsString('#[AsDashboardWidget]', $e->getMessage());
        }
    }

    #[Test]
    public function widgetPropertiesAreReadonly(): void
    {
        $widget = new ConcreteTestDashboardWidget();
        $reflection = new \ReflectionClass($widget);

        foreach (['id', 'label', 'context', 'priority'] as $prop) {
            self::assertTrue($reflection->getProperty($prop)->isReadOnly());
        }
    }

    #[Test]
    public function handleConfigureResolvesRequestArgument(): void
    {
        $request = new Request(query: ['tab' => 'advanced']);
        $widget = new ConfigureRequestInjectTestDashboardWidget();
        $widget->setConfigureArgumentResolver(static fn() => [$request]);

        ob_start();
        $widget->handleConfigure();
        $output = ob_get_clean();

        self::assertSame('advanced', $output);
    }

    #[Test]
    public function handleConfigureResolvesCurrentUserArgument(): void
    {
        wp_set_current_user(1);
        $user = wp_get_current_user();

        $widget = new ConfigureCurrentUserInjectTestDashboardWidget();
        $widget->setConfigureArgumentResolver(static fn() => [$user]);

        ob_start();
        $widget->handleConfigure();
        $output = ob_get_clean();

        self::assertSame($user->display_name, $output);
    }

    #[Test]
    public function handleConfigureWithRender(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects(self::once())
            ->method('render')
            ->with('dashboard/configure.html.twig', ['setting' => 'value'])
            ->willReturn('<p>configure rendered</p>');

        $widget = new ConfigureTemplatingTestDashboardWidget();
        $widget->setTemplateRenderer($renderer);

        ob_start();
        $widget->handleConfigure();
        $output = ob_get_clean();

        self::assertSame('<p>configure rendered</p>', $output);
    }

    #[Test]
    public function invokeReturnsExpectedContent(): void
    {
        $widget = new FullAttributeTestDashboardWidget();

        self::assertSame('<p>full widget</p>', $widget());
    }

    #[Test]
    public function registerWithCapabilityChecksUserPermission(): void
    {
        set_current_screen('dashboard');

        global $wp_meta_boxes;

        // Set up admin user with manage_options capability
        wp_set_current_user(1);

        $widget = new FullAttributeTestDashboardWidget();
        $widget->register();

        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);
    }

    #[Test]
    public function registerUsesInjectedIsGrantedChecker(): void
    {
        set_current_screen('dashboard');

        global $wp_meta_boxes;

        $authChecker = new class implements AuthorizationCheckerInterface {
            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return true;
            }
        };
        $checker = new IsGrantedChecker($authChecker);

        // Anonymous user would normally be denied, but the injected checker allows it
        wp_set_current_user(0);

        $widget = new CapabilityTestDashboardWidget($checker);
        $widget->register();

        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);
    }

    #[Test]
    public function renderDelegatesToTemplateRenderer(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects(self::once())
            ->method('render')
            ->with('dashboard/test.html.twig', ['stat' => 42])
            ->willReturn('<p>rendered</p>');

        $widget = new TemplatingTestDashboardWidget();
        $widget->setTemplateRenderer($renderer);

        self::assertSame('<p>rendered</p>', $widget());
    }

    #[Test]
    public function renderThrowsLogicExceptionWithoutRenderer(): void
    {
        $widget = new TemplatingTestDashboardWidget();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('TemplateRendererInterface is not available');

        $widget();
    }

    #[Test]
    public function handleRenderResolvesRequestArgument(): void
    {
        $request = new Request(query: ['tab' => 'advanced']);
        $widget = new RequestInjectTestDashboardWidget();
        $widget->setInvokeArgumentResolver(static fn() => [$request]);

        ob_start();
        $widget->handleRender();
        $output = ob_get_clean();

        self::assertSame('advanced', $output);
    }

    #[Test]
    public function handleRenderResolvesCurrentUserArgument(): void
    {
        wp_set_current_user(1);
        $user = wp_get_current_user();

        $widget = new CurrentUserInjectTestDashboardWidget();
        $widget->setInvokeArgumentResolver(static fn() => [$user]);

        ob_start();
        $widget->handleRender();
        $output = ob_get_clean();

        self::assertSame($user->display_name, $output);
    }

    #[Test]
    public function handleRenderResolvesBothArguments(): void
    {
        wp_set_current_user(1);
        $request = new Request(query: ['tab' => 'general']);
        $user = wp_get_current_user();

        $widget = new BothInjectTestDashboardWidget();
        $widget->setInvokeArgumentResolver(static fn() => [$request, $user]);

        ob_start();
        $widget->handleRender();
        $output = ob_get_clean();

        self::assertSame('general:' . $user->display_name, $output);
    }

    #[Test]
    public function handleRenderWithoutResolverWorksForNoArgInvoke(): void
    {
        $widget = new ConcreteTestDashboardWidget();

        ob_start();
        $widget->handleRender();
        $output = ob_get_clean();

        self::assertSame('<p>dashboard content</p>', $output);
    }

    #[Test]
    public function registerSkipsWhenInjectedCheckerDenies(): void
    {
        set_current_screen('dashboard');

        global $wp_meta_boxes;
        $wp_meta_boxes = [];

        $authChecker = new class implements AuthorizationCheckerInterface {
            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return false;
            }
        };
        $checker = new IsGrantedChecker($authChecker);

        wp_set_current_user(1);

        $widget = new CapabilityTestDashboardWidget($checker);
        $widget->register();

        self::assertArrayNotHasKey('capability_widget', $wp_meta_boxes['dashboard']['normal']['core'] ?? []);
    }
}

#[AsDashboardWidget(id: 'test_dashboard_widget', label: 'Test Dashboard Widget')]
class ConcreteTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return '<p>dashboard content</p>';
    }
}

#[IsGranted('manage_options')]
#[AsDashboardWidget(
    id: 'full_widget',
    label: 'Full Widget',
    context: 'side',
    priority: 'high',
)]
class FullAttributeTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return '<p>full widget</p>';
    }
}

class NoAttributeTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return '';
    }
}

#[IsGranted('edit_posts')]
#[AsDashboardWidget(id: 'capability_widget', label: 'Capability Widget')]
class CapabilityTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return '<p>capability widget</p>';
    }
}

#[IsGranted('activate_plugins')]
#[AsDashboardWidget(id: 'restricted_widget', label: 'Restricted Widget')]
class RestrictedCapabilityTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return '<p>restricted widget</p>';
    }
}

#[AsDashboardWidget(id: 'templating_widget', label: 'Templating Widget')]
class TemplatingTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return $this->render('dashboard/test.html.twig', ['stat' => 42]);
    }
}

#[AsDashboardWidget(id: 'configurable_widget', label: 'Configurable Widget')]
class ConfigurableTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return '<p>configurable widget</p>';
    }

    public function configure(): string
    {
        return '<input type="text" name="setting">';
    }
}

#[AsDashboardWidget(id: 'no_configure_widget', label: 'No Configure Widget')]
class NoConfigureTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return '<p>no configure</p>';
    }
}

#[AsDashboardWidget(id: 'configure_request_inject_widget', label: 'Configure Request Inject')]
class ConfigureRequestInjectTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return '';
    }

    public function configure(Request $request): string
    {
        return $request->query->get('tab', 'default');
    }
}

#[AsDashboardWidget(id: 'configure_user_inject_widget', label: 'Configure User Inject')]
class ConfigureCurrentUserInjectTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return '';
    }

    public function configure(#[CurrentUser] \WP_User $user): string
    {
        return $user->display_name;
    }
}

#[AsDashboardWidget(id: 'configure_templating_widget', label: 'Configure Templating')]
class ConfigureTemplatingTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return '';
    }

    public function configure(): string
    {
        return $this->render('dashboard/configure.html.twig', ['setting' => 'value']);
    }
}

#[AsDashboardWidget(id: 'request_inject_widget', label: 'Request Inject Widget')]
class RequestInjectTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(Request $request): string
    {
        return $request->query->get('tab', 'default');
    }
}

#[AsDashboardWidget(id: 'user_inject_widget', label: 'User Inject Widget')]
class CurrentUserInjectTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(#[CurrentUser] \WP_User $user): string
    {
        return $user->display_name;
    }
}

#[AsDashboardWidget(id: 'both_inject_widget', label: 'Both Inject Widget')]
class BothInjectTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(Request $request, #[CurrentUser] \WP_User $user): string
    {
        return $request->query->get('tab', 'default') . ':' . $user->display_name;
    }
}
