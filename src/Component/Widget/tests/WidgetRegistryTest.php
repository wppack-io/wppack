<?php

declare(strict_types=1);

namespace WpPack\Component\Widget\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\ArgumentResolver;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\RequestValueResolver;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Authorization\AuthorizationCheckerInterface;
use WpPack\Component\Security\Security;
use WpPack\Component\Security\ValueResolver\CurrentUserValueResolver;
use WpPack\Component\Templating\TemplateRendererInterface;
use WpPack\Component\Widget\AbstractWidget;
use WpPack\Component\Widget\Attribute\AsWidget;
use WpPack\Component\Widget\WidgetRegistry;

final class WidgetRegistryTest extends TestCase
{
    private WidgetRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new WidgetRegistry();
    }

    #[Test]
    public function registerCallsWordPressFunction(): void
    {
        $widget = new RegistryConcreteTestWidget();

        $this->registry->register($widget);

        global $wp_widget_factory;
        $registered = false;
        foreach ($wp_widget_factory->widgets as $w) {
            if ($w === $widget) {
                $registered = true;
                break;
            }
        }

        self::assertTrue($registered);
    }

    #[Test]
    public function unregisterCallsWordPressFunction(): void
    {
        register_widget(\WP_Widget_Text::class);
        $this->registry->unregister(\WP_Widget_Text::class);

        global $wp_widget_factory;
        $found = false;
        foreach ($wp_widget_factory->widgets as $w) {
            if ($w instanceof \WP_Widget_Text) {
                $found = true;
                break;
            }
        }

        self::assertFalse($found);
    }

    #[Test]
    public function registerSidebarCallsWordPressFunction(): void
    {
        $this->registry->registerSidebar([
            'name' => 'Test Sidebar',
            'id' => 'test-sidebar-' . uniqid(),
            'before_widget' => '<div>',
            'after_widget' => '</div>',
            'before_title' => '<h3>',
            'after_title' => '</h3>',
        ]);

        // register_sidebar returns the sidebar ID — if no exception was thrown, it succeeded
        self::assertTrue(true);
    }

    #[Test]
    public function registerSetsTemplateRenderer(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $registry = new WidgetRegistry($renderer);

        $widget = new RegistryConcreteTestWidget();
        $registry->register($widget);

        $ref = new \ReflectionProperty(AbstractWidget::class, 'templateRenderer');
        self::assertSame($renderer, $ref->getValue($widget));
    }

    #[Test]
    public function registerSetsInvokeArgumentResolver(): void
    {
        $request = new Request(query: ['tab' => 'general']);
        $registry = new WidgetRegistry(argumentResolver: new ArgumentResolver([
            new RequestValueResolver($request),
        ]));

        $widget = new RegistryRequestInjectTestWidget();
        $registry->register($widget);

        $ref = new \ReflectionProperty(AbstractWidget::class, 'invokeArgumentResolver');
        self::assertNotNull($ref->getValue($widget));
    }

    #[Test]
    public function registerSetsConfigureArgumentResolver(): void
    {
        $request = new Request(query: ['tab' => 'general']);
        $registry = new WidgetRegistry(argumentResolver: new ArgumentResolver([
            new RequestValueResolver($request),
        ]));

        $widget = new RegistryConfigureRequestInjectTestWidget();
        $registry->register($widget);

        $ref = new \ReflectionProperty(AbstractWidget::class, 'configureArgumentResolver');
        self::assertNotNull($ref->getValue($widget));
    }

    #[Test]
    public function registerWithoutDependenciesWorks(): void
    {
        $widget = new RegistryConcreteTestWidget();

        $this->registry->register($widget);

        // No exception means success
        self::assertTrue(true);
    }

    #[Test]
    public function resolverInjectsRequestIntoWidget(): void
    {
        $request = new Request(query: ['tab' => 'advanced']);
        $registry = new WidgetRegistry(argumentResolver: new ArgumentResolver([
            new RequestValueResolver($request),
        ]));

        $widget = new RegistryRequestInjectTestWidget();
        $registry->register($widget);

        ob_start();
        $widget->widget(
            ['before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => ''],
            [],
        );
        $output = ob_get_clean();

        self::assertSame('advanced', $output);
    }

    #[Test]
    public function resolverInjectsCurrentUserIntoWidget(): void
    {
        wp_set_current_user(1);
        $user = wp_get_current_user();

        $security = $this->createSecurityMock($user);
        $registry = new WidgetRegistry(argumentResolver: new ArgumentResolver([
            new CurrentUserValueResolver($security),
        ]));

        $widget = new RegistryCurrentUserInjectTestWidget();
        $registry->register($widget);

        ob_start();
        $widget->widget(
            ['before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => ''],
            [],
        );
        $output = ob_get_clean();

        self::assertSame($user->display_name, $output);
    }

    #[Test]
    public function resolverInjectsBothIntoWidget(): void
    {
        wp_set_current_user(1);
        $request = new Request(query: ['tab' => 'general']);
        $user = wp_get_current_user();

        $security = $this->createSecurityMock($user);
        $registry = new WidgetRegistry(argumentResolver: new ArgumentResolver([
            new RequestValueResolver($request),
            new CurrentUserValueResolver($security),
        ]));

        $widget = new RegistryBothInjectTestWidget();
        $registry->register($widget);

        ob_start();
        $widget->widget(
            ['before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => ''],
            [],
        );
        $output = ob_get_clean();

        self::assertSame('general:' . $user->display_name, $output);
    }

    #[Test]
    public function resolverInjectsRequestIntoConfigure(): void
    {
        $request = new Request(query: ['tab' => 'advanced']);
        $registry = new WidgetRegistry(argumentResolver: new ArgumentResolver([
            new RequestValueResolver($request),
        ]));

        $widget = new RegistryConfigureRequestInjectTestWidget();
        $registry->register($widget);

        ob_start();
        $widget->form([]);
        $output = ob_get_clean();

        self::assertSame('advanced', $output);
    }

    #[Test]
    public function resolverInjectsCurrentUserIntoConfigure(): void
    {
        wp_set_current_user(1);
        $user = wp_get_current_user();

        $security = $this->createSecurityMock($user);
        $registry = new WidgetRegistry(argumentResolver: new ArgumentResolver([
            new CurrentUserValueResolver($security),
        ]));

        $widget = new RegistryConfigureCurrentUserInjectTestWidget();
        $registry->register($widget);

        ob_start();
        $widget->form([]);
        $output = ob_get_clean();

        self::assertSame($user->display_name, $output);
    }

    private function createSecurityMock(?\WP_User $user = null): Security
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authManager = $this->createMock(AuthenticationManagerInterface::class);

        if ($user !== null) {
            $token = $this->createMock(TokenInterface::class);
            $token->method('isAuthenticated')->willReturn(true);
            $token->method('getUser')->willReturn($user);
            $authManager->method('getToken')->willReturn($token);
        }

        return new Security($authChecker, $authManager);
    }
}

#[AsWidget(id: 'registry_concrete_widget', label: 'Registry Concrete Widget')]
class RegistryConcreteTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        return '<p>registry test</p>';
    }
}

#[AsWidget(id: 'registry_request_inject_widget', label: 'Registry Request Inject')]
class RegistryRequestInjectTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance, Request $request): string
    {
        return $request->query->get('tab', 'default');
    }
}

#[AsWidget(id: 'registry_user_inject_widget', label: 'Registry User Inject')]
class RegistryCurrentUserInjectTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance, #[CurrentUser] \WP_User $user): string
    {
        return $user->display_name;
    }
}

#[AsWidget(id: 'registry_both_inject_widget', label: 'Registry Both Inject')]
class RegistryBothInjectTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance, Request $request, #[CurrentUser] \WP_User $user): string
    {
        return $request->query->get('tab', 'default') . ':' . $user->display_name;
    }
}

#[AsWidget(id: 'registry_configure_request_inject_widget', label: 'Registry Configure Request Inject')]
class RegistryConfigureRequestInjectTestWidget extends AbstractWidget
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

#[AsWidget(id: 'registry_configure_user_inject_widget', label: 'Registry Configure User Inject')]
class RegistryConfigureCurrentUserInjectTestWidget extends AbstractWidget
{
    public function __invoke(array $args, array $instance): string
    {
        return '';
    }

    public function configure(array $instance, #[CurrentUser] \WP_User $user): string
    {
        return $user->display_name;
    }
}
