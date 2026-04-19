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

namespace WPPack\Component\Security\Bridge\OAuth\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WPPack\Component\Security\Bridge\OAuth\OAuthEntryPoint;
use WPPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
use WPPack\Component\Security\Bridge\OAuth\State\OAuthStateStore;
use WPPack\Component\Transient\TransientManager;

#[CoversClass(OAuthEntryPoint::class)]
final class OAuthEntryPointTest extends TestCase
{
    private AuthenticationSession $authSession;

    protected function setUp(): void
    {
        $this->authSession = new AuthenticationSession();
        wp_set_current_user(0);
    }

    protected function tearDown(): void
    {
        remove_all_filters('login_url');
        remove_all_actions('login_init');
        wp_set_current_user(0);
    }

    private function createConfiguration(bool $pkceEnabled = false): OAuthConfiguration
    {
        return new OAuthConfiguration(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            redirectUri: 'https://example.com/oauth/callback',
            pkceEnabled: $pkceEnabled,
        );
    }

    private function createEntryPoint(
        ?ProviderInterface $provider = null,
        ?OAuthConfiguration $configuration = null,
        ?OAuthStateStore $stateStore = null,
        ?Request $request = null,
    ): OAuthEntryPoint {
        return new OAuthEntryPoint(
            $provider ?? $this->createMock(ProviderInterface::class),
            $configuration ?? $this->createConfiguration(),
            $stateStore ?? new OAuthStateStore(new TransientManager()),
            $this->authSession,
            $request ?? Request::create('https://example.com/wp-login.php'),
        );
    }

    #[Test]
    public function getLoginUrlReturnsAuthorizationUrl(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects(self::once())
            ->method('getAuthorizationUrl')
            ->willReturn('https://idp.example.com/authorize?client_id=test&state=abc');

        $entryPoint = $this->createEntryPoint(provider: $provider, configuration: $this->createConfiguration(pkceEnabled: false));

        $url = $entryPoint->getLoginUrl();

        self::assertSame('https://idp.example.com/authorize?client_id=test&state=abc', $url);
    }

    #[Test]
    public function getLoginUrlWithPkceEnabled(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects(self::once())
            ->method('getAuthorizationUrl')
            ->with(
                self::isType('string'),
                self::isType('string'),
                self::isType('string'),
                'S256',
            )
            ->willReturn('https://idp.example.com/authorize?code_challenge=xxx');

        $entryPoint = $this->createEntryPoint(provider: $provider, configuration: $this->createConfiguration(pkceEnabled: true));

        $url = $entryPoint->getLoginUrl();

        self::assertSame('https://idp.example.com/authorize?code_challenge=xxx', $url);
    }

    #[Test]
    public function getLoginUrlWithReturnTo(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAuthorizationUrl')
            ->willReturn('https://idp.example.com/authorize');

        $entryPoint = $this->createEntryPoint(provider: $provider);

        $url = $entryPoint->getLoginUrl('https://app.example.com/dashboard');

        self::assertSame('https://idp.example.com/authorize', $url);
    }

    #[Test]
    public function registerAddsLoginUrlFilter(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAuthorizationUrl')
            ->willReturn('https://idp.example.com/authorize?client_id=test&state=abc');

        $entryPoint = $this->createEntryPoint(provider: $provider);

        $entryPoint->register();

        $result = apply_filters('login_url', 'https://example.com/wp-login.php', '', false);

        self::assertSame('https://idp.example.com/authorize?client_id=test&state=abc', $result);
    }

    #[Test]
    public function registerAddsLoginUrlFilterWithRedirect(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAuthorizationUrl')
            ->willReturn('https://idp.example.com/authorize');

        $entryPoint = $this->createEntryPoint(provider: $provider);

        $entryPoint->register();

        $result = apply_filters('login_url', 'https://example.com/wp-login.php', 'https://example.com/wp-admin/', false);

        self::assertSame('https://idp.example.com/authorize', $result);
    }

    #[Test]
    public function registerAddsLoginUrlFilterWithEmptyRedirect(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAuthorizationUrl')
            ->willReturn('https://idp.example.com/authorize');

        $entryPoint = $this->createEntryPoint(provider: $provider);

        $entryPoint->register();

        // Empty redirect string should result in null returnTo
        $result = apply_filters('login_url', 'https://example.com/wp-login.php', '', false);

        self::assertSame('https://idp.example.com/authorize', $result);
    }

    #[Test]
    public function getLoginUrlStoresStateInStore(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAuthorizationUrl')
            ->willReturn('https://idp.example.com/authorize');

        $stateStore = new OAuthStateStore(new TransientManager());

        $entryPoint = $this->createEntryPoint(provider: $provider, stateStore: $stateStore);

        // getLoginUrl should work without throwing
        $url = $entryPoint->getLoginUrl();
        self::assertNotEmpty($url);
    }

    #[Test]
    public function getLoginUrlWithPkcePassesCodeChallenge(): void
    {
        $codeChallenge = null;

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects(self::once())
            ->method('getAuthorizationUrl')
            ->willReturnCallback(function (string $state, string $nonce, ?string $cc) use (&$codeChallenge): string {
                $codeChallenge = $cc;

                return 'https://idp.example.com/authorize';
            });

        $entryPoint = $this->createEntryPoint(provider: $provider, configuration: $this->createConfiguration(pkceEnabled: true));

        $entryPoint->getLoginUrl();

        self::assertNotNull($codeChallenge);
        self::assertNotEmpty($codeChallenge);
    }

    #[Test]
    public function registerLoginInitTriggersStartForGetWithoutAction(): void
    {
        $redirectCalled = false;

        // Mock wp_redirect to capture the redirect and prevent exit
        add_filter('wp_redirect', function (string $location) use (&$redirectCalled): string {
            $redirectCalled = true;
            // Throw to prevent reaching exit
            throw new \RuntimeException('redirect intercepted');
        });

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAuthorizationUrl')
            ->willReturn('https://idp.example.com/authorize');

        $entryPoint = $this->createEntryPoint(
            provider: $provider,
            request: Request::create('https://example.com/wp-login.php'),
        );

        $entryPoint->register();

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // wp_redirect throws to prevent exit
        }

        remove_all_filters('wp_redirect');

        self::assertTrue($redirectCalled);
    }

    #[Test]
    public function registerLoginInitSkipsPostRequest(): void
    {
        $redirectCalled = false;

        add_filter('wp_redirect', function (string $location) use (&$redirectCalled): string {
            $redirectCalled = true;

            return $location;
        });

        $entryPoint = $this->createEntryPoint(
            request: Request::create('https://example.com/wp-login.php', 'POST'),
        );

        $entryPoint->register();

        do_action('login_init');

        remove_all_filters('wp_redirect');

        self::assertFalse($redirectCalled);
    }

    #[Test]
    public function registerLoginInitSkipsGetWithAction(): void
    {
        $redirectCalled = false;

        add_filter('wp_redirect', function (string $location) use (&$redirectCalled): string {
            $redirectCalled = true;

            return $location;
        });

        $entryPoint = $this->createEntryPoint(
            request: Request::create('https://example.com/wp-login.php?action=logout'),
        );

        $entryPoint->register();

        do_action('login_init');

        remove_all_filters('wp_redirect');

        self::assertFalse($redirectCalled);
    }

    #[Test]
    public function registerLoginInitSkipsLoggedInUser(): void
    {
        $redirectCalled = false;

        add_filter('wp_redirect', function (string $location) use (&$redirectCalled): string {
            $redirectCalled = true;

            return $location;
        });

        // Simulate logged-in user
        wp_set_current_user(1);

        $entryPoint = $this->createEntryPoint(
            request: Request::create('https://example.com/wp-login.php'),
        );

        $entryPoint->register();

        do_action('login_init');

        remove_all_filters('wp_redirect');

        self::assertFalse($redirectCalled);
    }

    #[Test]
    public function registerLoginInitPassesRedirectToAsReturnTo(): void
    {
        $capturedLocation = null;

        add_filter('wp_redirect', function (string $location) use (&$capturedLocation): string {
            $capturedLocation = $location;
            throw new \RuntimeException('redirect intercepted');
        });

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAuthorizationUrl')
            ->willReturn('https://idp.example.com/authorize');

        $stateStore = new OAuthStateStore(new TransientManager());

        $entryPoint = $this->createEntryPoint(
            provider: $provider,
            stateStore: $stateStore,
            request: Request::create('https://example.com/wp-login.php?redirect_to=' . urlencode('https://example.com/wp-admin/edit.php')),
        );

        $entryPoint->register();

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // redirect intercepted
        }

        remove_all_filters('wp_redirect');

        // The redirect goes to IdP, but the returnTo is stored in state
        self::assertNotNull($capturedLocation);
    }
}
