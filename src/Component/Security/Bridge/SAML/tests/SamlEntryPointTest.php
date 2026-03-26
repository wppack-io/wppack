<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML\Tests;

use OneLogin\Saml2\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WpPack\Component\Security\Bridge\SAML\SamlEntryPoint;

#[CoversClass(SamlEntryPoint::class)]
final class SamlEntryPointTest extends TestCase
{
    private AuthenticationSession $authSession;

    protected function setUp(): void
    {
        $this->authSession = new AuthenticationSession();
        // Ensure no user is logged in by default
        wp_set_current_user(0);
    }

    protected function tearDown(): void
    {
        remove_all_actions('login_init');
        remove_all_filters('login_url');
        wp_set_current_user(0);
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

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
        );
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

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
        );
        $loginUrl = $entryPoint->getLoginUrl('https://sp.example.com/dashboard');

        self::assertSame(
            'https://idp.example.com/sso?SAMLRequest=encoded&RelayState=...',
            $loginUrl,
        );
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

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
        );

        try {
            $entryPoint->start('https://sp.example.com/dashboard');
        } catch (\Throwable) {
            // start() is declared as returning void but annotated @return never
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

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
        );

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

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
        );
        $entryPoint->register();

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // start() may exit
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

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php', 'POST'),
        );
        $entryPoint->register();

        do_action('login_init');

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

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php?action=logout'),
        );
        $entryPoint->register();

        do_action('login_init');

        self::assertFalse($loginCalled);
    }

    #[Test]
    public function registerLoginInitSkipsLoggedInUser(): void
    {
        $loginCalled = false;

        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->willReturnCallback(function () use (&$loginCalled): void {
                $loginCalled = true;
            });

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        // Simulate logged-in user
        wp_set_current_user(1);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
        );
        $entryPoint->register();

        do_action('login_init');

        self::assertFalse($loginCalled);
    }

    #[Test]
    public function registerLoginInitPassesRedirectToAsReturnTo(): void
    {
        $capturedReturnTo = null;

        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->willReturnCallback(function (?string $returnTo) use (&$capturedReturnTo): void {
                $capturedReturnTo = $returnTo;
            });

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $redirectTo = home_url('/wp-admin/edit.php');

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('http://example.org/wp-login.php?redirect_to=' . urlencode($redirectTo)),
        );
        $entryPoint->register();

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // start() may exit
        }

        self::assertSame($redirectTo, $capturedReturnTo);
    }

    #[Test]
    public function registerLoginInitUsesAdminUrlWhenNoRedirectTo(): void
    {
        $capturedReturnTo = null;

        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->willReturnCallback(function (?string $returnTo) use (&$capturedReturnTo): void {
                $capturedReturnTo = $returnTo;
            });

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
        );
        $entryPoint->register();

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // start() may exit
        }

        self::assertSame(admin_url(), $capturedReturnTo);
    }

    #[Test]
    public function registerLoginUrlFilterReturnsIdpUrl(): void
    {
        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->with(null, [], false, false, true)
            ->willReturn('https://idp.example.com/sso?SAMLRequest=encoded');

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
        );
        $entryPoint->register();

        $url = apply_filters('login_url', 'https://example.com/wp-login.php', '');

        self::assertSame('https://idp.example.com/sso?SAMLRequest=encoded', $url);
    }

    #[Test]
    public function registerLoginUrlFilterPassesRedirectParam(): void
    {
        $capturedReturnTo = null;

        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->willReturnCallback(function (?string $returnTo) use (&$capturedReturnTo): string {
                $capturedReturnTo = $returnTo;

                return 'https://idp.example.com/sso';
            });

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
        );
        $entryPoint->register();

        apply_filters('login_url', 'https://example.com/wp-login.php', 'https://example.com/dashboard');

        self::assertSame('https://example.com/dashboard', $capturedReturnTo);
    }

    #[Test]
    public function registerLoginInitShowsErrorPageForSamlErrorAction(): void
    {
        $wpDieFilter = static fn(): \Closure => static function (string|\WP_Error $message = ''): never {
            throw new \WPDieException(\is_string($message) ? $message : $message->get_error_message());
        };

        add_filter('wp_die_handler', $wpDieFilter, \PHP_INT_MAX);

        $factory = $this->createMock(SamlAuthFactory::class);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php?action=saml_error'),
        );
        $entryPoint->register();

        try {
            do_action('login_init');
            self::fail('Expected wp_die() to be called');
        } catch (\WPDieException $e) {
            self::assertStringContainsString('SAML authentication failed', $e->getMessage());
        } finally {
            remove_filter('wp_die_handler', $wpDieFilter, \PHP_INT_MAX);
        }
    }

    #[Test]
    public function registerLoginInitSkipsLoggedOutParam(): void
    {
        $loginCalled = false;

        $auth = $this->createMock(Auth::class);
        $auth->method('login')
            ->willReturnCallback(function () use (&$loginCalled): void {
                $loginCalled = true;
            });

        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('create')->willReturn($auth);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php?loggedout=true'),
        );
        $entryPoint->register();

        do_action('login_init');

        self::assertFalse($loginCalled);
    }
}
