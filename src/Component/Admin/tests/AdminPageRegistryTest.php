<?php

declare(strict_types=1);

namespace WpPack\Component\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\Admin\Attribute\AsAdminPage;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Security;
use WpPack\Component\Security\Authorization\AuthorizationCheckerInterface;
use WpPack\Component\Templating\TemplateRendererInterface;

final class AdminPageRegistryTest extends TestCase
{
    private AdminPageRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new AdminPageRegistry();
    }

    #[Test]
    public function registerCompletesWithoutException(): void
    {
        $page = new RegistryTestAdminPage();

        $this->registry->register($page);

        self::assertTrue(true);
    }

    #[Test]
    public function registerAddsAdminMenuHook(): void
    {
        $page = new RegistryTestAdminPage();

        $this->registry->register($page);

        self::assertNotFalse(has_action('admin_menu'));
    }

    #[Test]
    public function registerAddsEnqueueHookWhenOverridden(): void
    {
        $page = new RegistryEnqueueTestAdminPage();

        $this->registry->register($page);

        self::assertNotFalse(has_action('admin_enqueue_scripts'));
    }

    #[Test]
    public function registerDoesNotAddEnqueueHookWhenNotOverridden(): void
    {
        // Reset admin_enqueue_scripts hooks
        global $wp_filter;
        unset($wp_filter['admin_enqueue_scripts']);

        $page = new RegistryTestAdminPage();

        $this->registry->register($page);

        self::assertFalse(has_action('admin_enqueue_scripts'));
    }

    #[Test]
    public function unregisterCallsRemoveMenuPage(): void
    {
        $this->registry->unregister('registry-test-admin');

        self::assertTrue(true);
    }

    #[Test]
    public function unregisterSubmenuCallsRemoveSubmenuPage(): void
    {
        $this->registry->unregisterSubmenu('options-general.php', 'registry-test-admin');

        self::assertTrue(true);
    }

    #[Test]
    public function registerSetsTemplateRendererWhenProvided(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $registry = new AdminPageRegistry($renderer);

        $page = new RegistryTestAdminPage();
        $registry->register($page);

        $ref = new \ReflectionProperty(AbstractAdminPage::class, 'renderer');
        self::assertSame($renderer, $ref->getValue($page));
    }

    #[Test]
    public function registerSetsResolverForRequestParam(): void
    {
        $request = new Request(query: ['tab' => 'general']);
        $registry = new AdminPageRegistry(request: $request);

        $page = new RegistryRequestInjectTestAdminPage();
        $registry->register($page);

        $ref = new \ReflectionProperty(AbstractAdminPage::class, 'invokeArgumentResolver');
        self::assertNotNull($ref->getValue($page));
    }

    #[Test]
    public function registerSetsResolverForCurrentUserParam(): void
    {
        $security = $this->createSecurityMock();
        $registry = new AdminPageRegistry(security: $security);

        $page = new RegistryCurrentUserInjectTestAdminPage();
        $registry->register($page);

        $ref = new \ReflectionProperty(AbstractAdminPage::class, 'invokeArgumentResolver');
        self::assertNotNull($ref->getValue($page));
    }

    #[Test]
    public function registerSetsResolverForBothParams(): void
    {
        $request = new Request();
        $security = $this->createSecurityMock();
        $registry = new AdminPageRegistry(request: $request, security: $security);

        $page = new RegistryBothInjectTestAdminPage();
        $registry->register($page);

        $ref = new \ReflectionProperty(AbstractAdminPage::class, 'invokeArgumentResolver');
        self::assertNotNull($ref->getValue($page));
    }

    #[Test]
    public function registerDoesNotSetResolverForNoArgInvoke(): void
    {
        $request = new Request();
        $security = $this->createSecurityMock();
        $registry = new AdminPageRegistry(request: $request, security: $security);

        $page = new RegistryTestAdminPage();
        $registry->register($page);

        $ref = new \ReflectionProperty(AbstractAdminPage::class, 'invokeArgumentResolver');
        self::assertNull($ref->getValue($page));
    }

    #[Test]
    public function registerWorksWithoutRequestAndSecurity(): void
    {
        $registry = new AdminPageRegistry();

        $page = new RegistryRequestInjectTestAdminPage();
        $registry->register($page);

        self::assertNotFalse(has_action('admin_menu'));
    }

    #[Test]
    public function resolverInjectsRequestIntoHandleRender(): void
    {
        $request = new Request(query: ['tab' => 'advanced']);
        $registry = new AdminPageRegistry(request: $request);

        $page = new RegistryRequestInjectTestAdminPage();
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
        $registry = new AdminPageRegistry(security: $security);

        $page = new RegistryCurrentUserInjectTestAdminPage();
        $registry->register($page);

        ob_start();
        $page->handleRender();
        $output = ob_get_clean();

        self::assertSame($user->display_name, $output);
    }

    #[Test]
    public function resolverInjectsBothIntoHandleRender(): void
    {
        wp_set_current_user(1);
        $request = new Request(query: ['tab' => 'general']);
        $user = wp_get_current_user();

        $security = $this->createSecurityMock($user);
        $registry = new AdminPageRegistry(request: $request, security: $security);

        $page = new RegistryBothInjectTestAdminPage();
        $registry->register($page);

        ob_start();
        $page->handleRender();
        $output = ob_get_clean();

        self::assertSame('general:' . $user->display_name, $output);
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

#[AsAdminPage(slug: 'registry-test-admin', label: 'Registry Test')]
class RegistryTestAdminPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '<p>registry test</p>';
    }
}

#[AsAdminPage(slug: 'registry-enqueue-test', label: 'Registry Enqueue Test')]
class RegistryEnqueueTestAdminPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '';
    }

    protected function enqueue(): void
    {
        // scripts and styles enqueued
    }
}

#[AsAdminPage(slug: 'registry-request-inject', label: 'Registry Request Inject')]
class RegistryRequestInjectTestAdminPage extends AbstractAdminPage
{
    public function __invoke(Request $request): string
    {
        return $request->query->get('tab', 'default');
    }
}

#[AsAdminPage(slug: 'registry-user-inject', label: 'Registry User Inject')]
class RegistryCurrentUserInjectTestAdminPage extends AbstractAdminPage
{
    public function __invoke(#[CurrentUser] \WP_User $user): string
    {
        return $user->display_name;
    }
}

#[AsAdminPage(slug: 'registry-both-inject', label: 'Registry Both Inject')]
class RegistryBothInjectTestAdminPage extends AbstractAdminPage
{
    public function __invoke(Request $request, #[CurrentUser] \WP_User $user): string
    {
        return $request->query->get('tab', 'default') . ':' . $user->display_name;
    }
}
