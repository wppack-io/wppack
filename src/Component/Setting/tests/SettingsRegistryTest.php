<?php

declare(strict_types=1);

namespace WpPack\Component\Setting\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authorization\AuthorizationCheckerInterface;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Security;
use WpPack\Component\Setting\AbstractSettingsPage;
use WpPack\Component\Setting\Attribute\AsSettingsPage;
use WpPack\Component\Setting\SettingsConfigurator;
use WpPack\Component\Setting\SettingsRegistry;
use WpPack\Component\Templating\TemplateRendererInterface;

final class SettingsRegistryTest extends TestCase
{
    private SettingsRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new SettingsRegistry();
    }

    #[Test]
    public function registerCompletesWithoutException(): void
    {
        $page = new RegistryTestSettingsPage();

        $this->registry->register($page);

        self::assertTrue(true);
    }

    #[Test]
    public function registerAddsAdminMenuHook(): void
    {
        $page = new RegistryTestSettingsPage();

        $this->registry->register($page);

        self::assertNotFalse(has_action('admin_menu'));
    }

    #[Test]
    public function registerAddsAdminInitHook(): void
    {
        $page = new RegistryTestSettingsPage();

        $this->registry->register($page);

        self::assertNotFalse(has_action('admin_init'));
    }

    #[Test]
    public function registerSetsTemplateRendererWhenProvided(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $registry = new SettingsRegistry($renderer);

        $page = new RegistryTestSettingsPage();
        $registry->register($page);

        $ref = new \ReflectionProperty(AbstractSettingsPage::class, 'templateRenderer');
        self::assertSame($renderer, $ref->getValue($page));
    }

    #[Test]
    public function registerMultiplePagesAddsMultipleHooks(): void
    {
        global $wp_filter;

        $countCallbacks = static function (string $hook) use (&$wp_filter): int {
            if (!isset($wp_filter[$hook])) {
                return 0;
            }
            $count = 0;
            foreach ($wp_filter[$hook]->callbacks as $priorityCallbacks) {
                $count += \count($priorityCallbacks);
            }
            return $count;
        };

        $adminMenuBefore = $countCallbacks('admin_menu');
        $adminInitBefore = $countCallbacks('admin_init');

        $page1 = new RegistryTestSettingsPage();
        $page2 = new RegistryTestSecondSettingsPage();

        $this->registry->register($page1);
        $this->registry->register($page2);

        self::assertSame($adminMenuBefore + 2, $countCallbacks('admin_menu'));
        self::assertSame($adminInitBefore + 2, $countCallbacks('admin_init'));
    }

    #[Test]
    public function registerSetsResolverForRequestParam(): void
    {
        $request = new Request(query: ['tab' => 'general']);
        $registry = new SettingsRegistry(request: $request);

        $page = new RegistryRequestInjectTestSettingsPage();
        $registry->register($page);

        $ref = new \ReflectionProperty(AbstractSettingsPage::class, 'invokeArgumentResolver');
        self::assertNotNull($ref->getValue($page));
    }

    #[Test]
    public function registerSetsResolverForCurrentUserParam(): void
    {
        $security = $this->createSecurityMock();
        $registry = new SettingsRegistry(security: $security);

        $page = new RegistryCurrentUserInjectTestSettingsPage();
        $registry->register($page);

        $ref = new \ReflectionProperty(AbstractSettingsPage::class, 'invokeArgumentResolver');
        self::assertNotNull($ref->getValue($page));
    }

    #[Test]
    public function registerDoesNotSetResolverForNoArgInvoke(): void
    {
        $request = new Request();
        $security = $this->createSecurityMock();
        $registry = new SettingsRegistry(request: $request, security: $security);

        $page = new RegistryTestSettingsPage();
        $registry->register($page);

        $ref = new \ReflectionProperty(AbstractSettingsPage::class, 'invokeArgumentResolver');
        self::assertNull($ref->getValue($page));
    }

    #[Test]
    public function registerWorksWithoutRequestAndSecurity(): void
    {
        $page = new RegistryRequestInjectTestSettingsPage();

        $this->registry->register($page);

        self::assertNotFalse(has_action('admin_menu'));
    }

    #[Test]
    public function resolverInjectsRequestIntoHandleRender(): void
    {
        $request = new Request(query: ['tab' => 'advanced']);
        $registry = new SettingsRegistry(request: $request);

        $page = new RegistryRequestInjectTestSettingsPage();
        $registry->register($page);

        ob_start();
        $page->handleRender();
        $output = ob_get_clean();

        self::assertSame('advanced', $output);
    }

    #[Test]
    public function resolverInjectsCurrentUserIntoHandleRender(): void
    {
        wp_set_current_user(1);
        $user = wp_get_current_user();

        $security = $this->createSecurityMock($user);
        $registry = new SettingsRegistry(security: $security);

        $page = new RegistryCurrentUserInjectTestSettingsPage();
        $registry->register($page);

        ob_start();
        $page->handleRender();
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

#[AsSettingsPage(slug: 'registry-test', label: 'Registry Test')]
class RegistryTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void
    {
        $settings->section('general', 'General')
            ->field('test_field', 'Test Field', fn(array $args) => null);
    }
}

#[AsSettingsPage(slug: 'registry-test-second', label: 'Registry Test Second')]
class RegistryTestSecondSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void
    {
        $settings->section('options', 'Options')
            ->field('another_field', 'Another Field', fn(array $args) => null);
    }
}

#[AsSettingsPage(slug: 'registry-request-inject', label: 'Registry Request Inject')]
class RegistryRequestInjectTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}

    public function __invoke(Request $request): string
    {
        return $request->query->get('tab', 'default');
    }
}

#[AsSettingsPage(slug: 'registry-user-inject', label: 'Registry User Inject')]
class RegistryCurrentUserInjectTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}

    public function __invoke(#[CurrentUser] \WP_User $user): string
    {
        return $user->display_name;
    }
}
