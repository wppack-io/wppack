<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML\Tests;

use OneLogin\Saml2\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\SamlEntryPoint;

#[CoversClass(SamlEntryPoint::class)]
final class SamlEntryPointTest extends TestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('login_url');
        remove_all_actions('login_init');
    }
    #[Test]
    public function getLoginUrl(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->expects(self::once())
            ->method('login')
            ->with(null, [], false, false, true)
            ->willReturn('https://idp.example.com/sso?SAMLRequest=encoded');

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint($factory);
        $loginUrl = $entryPoint->getLoginUrl();

        self::assertSame('https://idp.example.com/sso?SAMLRequest=encoded', $loginUrl);
    }

    #[Test]
    public function getLoginUrlWithReturnTo(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->expects(self::once())
            ->method('login')
            ->with('https://sp.example.com/dashboard', [], false, false, true)
            ->willReturn('https://idp.example.com/sso?SAMLRequest=encoded&RelayState=...');

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint($factory);
        $loginUrl = $entryPoint->getLoginUrl('https://sp.example.com/dashboard');

        self::assertSame(
            'https://idp.example.com/sso?SAMLRequest=encoded&RelayState=...',
            $loginUrl,
        );
    }

    #[Test]
    public function registerAddsLoginUrlFilter(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->with(null, [], false, false, true)
            ->willReturn('https://idp.example.com/sso?SAMLRequest=encoded');

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint($factory);
        $entryPoint->register();

        $result = apply_filters('login_url', 'https://example.com/wp-login.php', '', false);

        self::assertSame('https://idp.example.com/sso?SAMLRequest=encoded', $result);
    }

    #[Test]
    public function registerAddsLoginUrlFilterWithRedirect(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->willReturnCallback(function (?string $returnTo) {
                if ($returnTo === 'https://sp.example.com/wp-admin/') {
                    return 'https://idp.example.com/sso?SAMLRequest=encoded&RelayState=admin';
                }

                return 'https://idp.example.com/sso?SAMLRequest=encoded';
            });

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint($factory);
        $entryPoint->register();

        $result = apply_filters('login_url', 'https://example.com/wp-login.php', 'https://sp.example.com/wp-admin/', false);

        self::assertSame('https://idp.example.com/sso?SAMLRequest=encoded&RelayState=admin', $result);
    }

    #[Test]
    public function registerAddsLoginUrlFilterWithEmptyRedirect(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->willReturnCallback(function (?string $returnTo, array $params, bool $forceAuthn, bool $isPassive, bool $stay) {
                // When redirect is empty, returnTo should be null
                if ($returnTo === null && $stay === true) {
                    return 'https://idp.example.com/sso?SAMLRequest=encoded-no-relay';
                }

                return 'https://idp.example.com/sso?SAMLRequest=encoded';
            });

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint($factory);
        $entryPoint->register();

        // Empty redirect string should result in null returnTo
        $result = apply_filters('login_url', 'https://example.com/wp-login.php', '', false);

        self::assertSame('https://idp.example.com/sso?SAMLRequest=encoded-no-relay', $result);
    }

    #[Test]
    public function startCallsAuthLogin(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->expects(self::once())
            ->method('login')
            ->with('https://sp.example.com/dashboard');

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint($factory);

        // start() calls exit, so we can't test it directly in most cases
        // But we can test that it properly delegates to auth->login()
        // by testing through the login_init hook indirectly
        // For now, verify that the method delegates to auth->login()
        try {
            $entryPoint->start('https://sp.example.com/dashboard');
        } catch (\Throwable) {
            // start() is declared as returning void but annotated @return never
            // The exit will be caught if running in a test harness that converts exit
        }
    }

    #[Test]
    public function startWithNullReturnTo(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->expects(self::once())
            ->method('login')
            ->with(null);

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint($factory);

        try {
            $entryPoint->start();
        } catch (\Throwable) {
            // exit in start() method
        }
    }

    #[Test]
    public function registerLoginInitTriggersStartForGetWithoutAction(): void
    {
        $loginCalled = false;

        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->willReturnCallback(function () use (&$loginCalled): void {
                $loginCalled = true;
            });

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint($factory);
        $entryPoint->register();

        // Simulate GET request without action
        $originalMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalAction = $_GET['action'] ?? null;
        $hasAction = isset($_GET['action']);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_GET['action']);

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // start() may exit
        }

        // Restore
        if ($originalMethod !== null) {
            $_SERVER['REQUEST_METHOD'] = $originalMethod;
        }

        if ($hasAction) {
            $_GET['action'] = $originalAction;
        }

        self::assertTrue($loginCalled);
    }

    #[Test]
    public function registerLoginInitSkipsPostRequest(): void
    {
        $loginCalled = false;

        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->willReturnCallback(function () use (&$loginCalled): void {
                $loginCalled = true;
            });

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint($factory);
        $entryPoint->register();

        // Simulate POST request
        $originalMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        do_action('login_init');

        if ($originalMethod !== null) {
            $_SERVER['REQUEST_METHOD'] = $originalMethod;
        }

        self::assertFalse($loginCalled);
    }

    #[Test]
    public function registerLoginInitSkipsGetWithAction(): void
    {
        $loginCalled = false;

        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->willReturnCallback(function () use (&$loginCalled): void {
                $loginCalled = true;
            });

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint($factory);
        $entryPoint->register();

        // Simulate GET request with action (e.g., ?action=logout)
        $originalMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $hasAction = isset($_GET['action']);
        $originalAction = $_GET['action'] ?? null;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['action'] = 'logout';

        do_action('login_init');

        if ($originalMethod !== null) {
            $_SERVER['REQUEST_METHOD'] = $originalMethod;
        }

        if ($hasAction) {
            $_GET['action'] = $originalAction;
        } else {
            unset($_GET['action']);
        }

        self::assertFalse($loginCalled);
    }
}
