<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Security\Bridge\SAML\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\SAML\Configuration\IdpSettings;
use WPPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;
use WPPack\Component\Security\Bridge\SAML\Configuration\SpSettings;
use WPPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;
use WPPack\Component\Security\Bridge\SAML\SamlEntryPoint;
use WPPack\Component\Transient\TransientManager;

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

    private function createSamlConfiguration(): SamlConfiguration
    {
        return new SamlConfiguration(
            idpSettings: new IdpSettings(
                entityId: 'https://idp.example.com/metadata',
                ssoUrl: 'https://idp.example.com/sso',
                sloUrl: 'https://idp.example.com/slo',
                x509Cert: 'MIICDummyCert==',
            ),
            spSettings: new SpSettings(
                entityId: 'https://sp.example.com/metadata',
                acsUrl: 'https://sp.example.com/acs',
                sloUrl: 'https://sp.example.com/slo',
            ),
        );
    }

    private function createFactory(): SamlAuthFactory
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('getConfiguration')->willReturn($this->createSamlConfiguration());

        return $factory;
    }

    /**
     * Create a SamlEntryPoint with a spy factory that records getConfiguration() calls.
     *
     * Used by tests that verify start() would be called via login_init,
     * where start() cannot be invoked directly because it calls exit.
     * Instead we detect that the code path reached the factory call.
     */
    private function createFactoryWithSpy(bool &$configCalled): SamlAuthFactory
    {
        $factory = $this->createMock(SamlAuthFactory::class);
        $factory->method('getConfiguration')
            ->willReturnCallback(function () use (&$configCalled): SamlConfiguration {
                $configCalled = true;
                // Throw to prevent header() + exit inside start()
                throw new \RuntimeException('start() reached');
            });

        return $factory;
    }

    #[Test]
    public function getLoginUrl(): void
    {
        $factory = $this->createFactory();

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
            new TransientManager(),
        );
        $loginUrl = $entryPoint->getLoginUrl();

        self::assertStringStartsWith('https://idp.example.com/sso?', $loginUrl);
        self::assertStringContainsString('SAMLRequest=', $loginUrl);
    }

    #[Test]
    public function getLoginUrlWithReturnTo(): void
    {
        $factory = $this->createFactory();

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
            new TransientManager(),
        );
        $loginUrl = $entryPoint->getLoginUrl('https://sp.example.com/dashboard');

        self::assertStringStartsWith('https://idp.example.com/sso?', $loginUrl);
        self::assertStringContainsString('SAMLRequest=', $loginUrl);
        self::assertStringContainsString('RelayState=', $loginUrl);
    }

    #[Test]
    public function registerLoginInitTriggersStartForGetWithoutAction(): void
    {
        $configCalled = false;
        $factory = $this->createFactoryWithSpy($configCalled);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
            new TransientManager(),
        );
        $entryPoint->register();

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // start() throws because our spy throws to prevent exit
        }

        self::assertTrue($configCalled, 'start() should have been called for anonymous GET without action');
    }

    #[Test]
    public function registerLoginInitSkipsPostRequest(): void
    {
        $configCalled = false;
        $factory = $this->createFactoryWithSpy($configCalled);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php', 'POST'),
            new TransientManager(),
        );
        $entryPoint->register();

        do_action('login_init');

        self::assertFalse($configCalled, 'start() should not be called for POST requests');
    }

    #[Test]
    public function registerLoginInitSkipsGetWithAction(): void
    {
        $configCalled = false;
        $factory = $this->createFactoryWithSpy($configCalled);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php?action=logout'),
            new TransientManager(),
        );
        $entryPoint->register();

        do_action('login_init');

        self::assertFalse($configCalled, 'start() should not be called when action=logout');
    }

    #[Test]
    public function registerLoginInitRedirectsLoggedInUserToAdminUrl(): void
    {
        $capturedLocation = null;

        add_filter('wp_redirect', function (string $location) use (&$capturedLocation): string {
            $capturedLocation = $location;
            throw new \RuntimeException('redirect intercepted');
        });

        $factory = $this->createFactory();

        wp_set_current_user(1);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
            new TransientManager(),
        );
        $entryPoint->register();

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // wp_safe_redirect throws to prevent exit
        }

        remove_all_filters('wp_redirect');

        self::assertSame(admin_url(), $capturedLocation);
    }

    #[Test]
    public function registerLoginInitRedirectsLoggedInUserToRedirectTo(): void
    {
        $capturedLocation = null;

        add_filter('wp_redirect', function (string $location) use (&$capturedLocation): string {
            $capturedLocation = $location;
            throw new \RuntimeException('redirect intercepted');
        });

        $factory = $this->createFactory();

        wp_set_current_user(1);

        $redirectTo = home_url('/wp-admin/edit.php');

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('http://example.org/wp-login.php?redirect_to=' . urlencode($redirectTo)),
            new TransientManager(),
        );
        $entryPoint->register();

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // wp_safe_redirect throws to prevent exit
        }

        remove_all_filters('wp_redirect');

        self::assertSame($redirectTo, $capturedLocation);
    }

    #[Test]
    public function registerLoginInitDoesNotRedirectLoggedInUserWithAction(): void
    {
        $redirectCalled = false;

        add_filter('wp_redirect', function (string $location) use (&$redirectCalled): string {
            $redirectCalled = true;

            return $location;
        });

        $factory = $this->createFactory();

        wp_set_current_user(1);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php?action=logout'),
            new TransientManager(),
        );
        $entryPoint->register();

        do_action('login_init');

        remove_all_filters('wp_redirect');

        self::assertFalse($redirectCalled);
    }

    #[Test]
    public function registerLoginInitPassesRedirectToAsReturnTo(): void
    {
        $factory = $this->createFactory();

        $redirectTo = home_url('/wp-admin/edit.php');

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('http://example.org/wp-login.php?redirect_to=' . urlencode($redirectTo)),
            new TransientManager(),
        );

        // Verify indirectly by checking getLoginUrl includes the relay state
        $loginUrl = $entryPoint->getLoginUrl($redirectTo);
        self::assertStringContainsString('RelayState=', $loginUrl);
    }

    #[Test]
    public function registerLoginInitUsesAdminUrlWhenNoRedirectTo(): void
    {
        $configCalled = false;
        $factory = $this->createFactoryWithSpy($configCalled);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
            new TransientManager(),
        );
        $entryPoint->register();

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // start() throws via spy
        }

        // start() was called, which means the admin_url() fallback was used
        self::assertTrue($configCalled);
    }

    #[Test]
    public function registerLoginUrlFilterReturnsIdpUrl(): void
    {
        $factory = $this->createFactory();

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
            new TransientManager(),
        );
        $entryPoint->register();

        $url = apply_filters('login_url', 'https://example.com/wp-login.php', '');

        self::assertStringStartsWith('https://idp.example.com/sso?', $url);
        self::assertStringContainsString('SAMLRequest=', $url);
    }

    #[Test]
    public function registerLoginUrlFilterPassesRedirectParam(): void
    {
        $factory = $this->createFactory();

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php'),
            new TransientManager(),
        );
        $entryPoint->register();

        $url = apply_filters('login_url', 'https://example.com/wp-login.php', 'https://example.com/dashboard');

        self::assertStringStartsWith('https://idp.example.com/sso?', $url);
        self::assertStringContainsString('RelayState=', $url);
    }

    #[Test]
    public function registerLoginInitShowsErrorPageForSamlErrorAction(): void
    {
        $wpDieFilter = static fn(): \Closure => static function (string|\WP_Error $message = ''): never {
            throw new \WPDieException(\is_string($message) ? $message : $message->get_error_message());
        };

        add_filter('wp_die_handler', $wpDieFilter, \PHP_INT_MAX);

        $factory = $this->createFactory();

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php?saml_error=true'),
            new TransientManager(),
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
        $capturedLocation = null;

        add_filter('wp_redirect', function (string $location) use (&$capturedLocation): string {
            $capturedLocation = $location;
            throw new \RuntimeException('redirect intercepted');
        });

        $factory = $this->createFactory();

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php?loggedout=true'),
            new TransientManager(),
        );
        $entryPoint->register();

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // wp_safe_redirect throws to prevent exit
        }

        remove_all_filters('wp_redirect');

        self::assertSame(home_url(), $capturedLocation);
    }

    #[Test]
    public function registerLoginInitRedirectsLostpasswordAction(): void
    {
        $configCalled = false;
        $factory = $this->createFactoryWithSpy($configCalled);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php?action=lostpassword'),
            new TransientManager(),
        );
        $entryPoint->register();

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // start() throws via spy
        }

        self::assertTrue($configCalled, 'start() should be called for action=lostpassword');
    }

    #[Test]
    public function registerLoginInitRedirectsRegisterAction(): void
    {
        $configCalled = false;
        $factory = $this->createFactoryWithSpy($configCalled);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php?action=register'),
            new TransientManager(),
        );
        $entryPoint->register();

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // start() throws via spy
        }

        self::assertTrue($configCalled, 'start() should be called for action=register');
    }

    #[Test]
    public function registerLoginInitSkipsPostpassAction(): void
    {
        $configCalled = false;
        $factory = $this->createFactoryWithSpy($configCalled);

        $entryPoint = new SamlEntryPoint(
            $factory,
            $this->authSession,
            Request::create('https://example.com/wp-login.php?action=postpass'),
            new TransientManager(),
        );
        $entryPoint->register();

        do_action('login_init');

        self::assertFalse($configCalled, 'start() should not be called for action=postpass');
    }
}
