<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DashboardWidget\AbstractDashboardWidget;
use WpPack\Component\DashboardWidget\Attribute\AsDashboardWidget;
use WpPack\Component\DashboardWidget\DashboardWidgetRegistry;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authorization\AuthorizationCheckerInterface;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Security;
use WpPack\Component\Templating\TemplateRendererInterface;

final class DashboardWidgetRegistryTest extends TestCase
{
    private DashboardWidgetRegistry $registry;

    protected function setUp(): void
    {
        set_current_screen('dashboard');

        global $wp_meta_boxes;
        $wp_meta_boxes = [];

        $this->registry = new DashboardWidgetRegistry();
    }

    #[Test]
    public function registerCallsWidgetRegister(): void
    {
        $widget = new RegistryTestDashboardWidget();

        $this->registry->register($widget);

        // If no exception was thrown, registration succeeded
        self::assertTrue(true);
    }

    #[Test]
    public function unregisterCallsRemoveMetaBox(): void
    {
        $this->registry->unregister('test_registry_widget');

        // If no exception was thrown, removal succeeded
        self::assertTrue(true);
    }

    #[Test]
    public function registerRegistersWidgetInMetaBoxes(): void
    {
        global $wp_meta_boxes;

        $widget = new RegistryTestDashboardWidget();
        $this->registry->register($widget);

        self::assertArrayHasKey('dashboard', $wp_meta_boxes);
        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);
    }

    #[Test]
    public function unregisterRemovesWidgetFromMetaBoxes(): void
    {
        global $wp_meta_boxes;

        $widget = new RegistryTestDashboardWidget();
        $this->registry->register($widget);

        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);

        $this->registry->unregister($widget->id);

        self::assertFalse($wp_meta_boxes['dashboard'][$widget->context][$widget->priority][$widget->id]);
    }

    #[Test]
    public function registerAndUnregisterRoundTrip(): void
    {
        global $wp_meta_boxes;

        $widget = new RegistryTestDashboardWidget();

        $this->registry->register($widget);
        self::assertArrayHasKey($widget->id, $wp_meta_boxes['dashboard'][$widget->context][$widget->priority]);

        $this->registry->unregister($widget->id);
        self::assertFalse($wp_meta_boxes['dashboard'][$widget->context][$widget->priority][$widget->id]);
    }

    #[Test]
    public function registerSetsTemplateRendererWhenProvided(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $registry = new DashboardWidgetRegistry($renderer);

        $widget = new RegistryTestDashboardWidget();
        $registry->register($widget);

        $ref = new \ReflectionProperty(AbstractDashboardWidget::class, 'renderer');
        self::assertSame($renderer, $ref->getValue($widget));
    }

    #[Test]
    public function registerPassesWidgetPropertiesToMetaBox(): void
    {
        global $wp_meta_boxes;

        $widget = new RegistryTestDashboardWidget();
        $this->registry->register($widget);

        $entry = $wp_meta_boxes['dashboard'][$widget->context][$widget->priority][$widget->id];
        self::assertSame('test_registry_widget', $entry['id']);
        self::assertSame('Registry Test Widget', $entry['title']);
    }

    #[Test]
    public function registerSetsResolverForRequestParam(): void
    {
        $request = new Request(query: ['tab' => 'general']);
        $registry = new DashboardWidgetRegistry(request: $request);

        $widget = new RegistryRequestInjectTestDashboardWidget();
        $registry->register($widget);

        $ref = new \ReflectionProperty(AbstractDashboardWidget::class, 'invokeArgumentResolver');
        self::assertNotNull($ref->getValue($widget));
    }

    #[Test]
    public function registerSetsResolverForCurrentUserParam(): void
    {
        $security = $this->createSecurityMock();
        $registry = new DashboardWidgetRegistry(security: $security);

        $widget = new RegistryCurrentUserInjectTestDashboardWidget();
        $registry->register($widget);

        $ref = new \ReflectionProperty(AbstractDashboardWidget::class, 'invokeArgumentResolver');
        self::assertNotNull($ref->getValue($widget));
    }

    #[Test]
    public function registerDoesNotSetResolverForNoArgInvoke(): void
    {
        $request = new Request();
        $security = $this->createSecurityMock();
        $registry = new DashboardWidgetRegistry(request: $request, security: $security);

        $widget = new RegistryTestDashboardWidget();
        $registry->register($widget);

        $ref = new \ReflectionProperty(AbstractDashboardWidget::class, 'invokeArgumentResolver');
        self::assertNull($ref->getValue($widget));
    }

    #[Test]
    public function registerWorksWithoutRequestAndSecurity(): void
    {
        $widget = new RegistryRequestInjectTestDashboardWidget();

        $this->registry->register($widget);

        self::assertTrue(true);
    }

    #[Test]
    public function resolverInjectsRequestIntoHandleRender(): void
    {
        $request = new Request(query: ['tab' => 'advanced']);
        $registry = new DashboardWidgetRegistry(request: $request);

        $widget = new RegistryRequestInjectTestDashboardWidget();
        $registry->register($widget);

        ob_start();
        $widget->handleRender();
        $output = ob_get_clean();

        self::assertSame('advanced', $output);
    }

    #[Test]
    public function resolverInjectsCurrentUserIntoHandleRender(): void
    {
        wp_set_current_user(1);
        $user = wp_get_current_user();

        $security = $this->createSecurityMock($user);
        $registry = new DashboardWidgetRegistry(security: $security);

        $widget = new RegistryCurrentUserInjectTestDashboardWidget();
        $registry->register($widget);

        ob_start();
        $widget->handleRender();
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

#[AsDashboardWidget(id: 'test_registry_widget', label: 'Registry Test Widget')]
class RegistryTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(): string
    {
        return '<p>registry test</p>';
    }
}

#[AsDashboardWidget(id: 'registry_request_inject_widget', label: 'Registry Request Inject')]
class RegistryRequestInjectTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(Request $request): string
    {
        return $request->query->get('tab', 'default');
    }
}

#[AsDashboardWidget(id: 'registry_user_inject_widget', label: 'Registry User Inject')]
class RegistryCurrentUserInjectTestDashboardWidget extends AbstractDashboardWidget
{
    public function __invoke(#[CurrentUser] \WP_User $user): string
    {
        return $user->display_name;
    }
}
