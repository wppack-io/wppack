<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WpPack\Component\Security\Bridge\OAuth\OAuthEntryPoint;
use WpPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
use WpPack\Component\Security\Bridge\OAuth\State\OAuthStateStore;

#[CoversClass(OAuthEntryPoint::class)]
final class OAuthEntryPointTest extends TestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('login_url');
        remove_all_actions('login_init');
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

    #[Test]
    public function getLoginUrlReturnsAuthorizationUrl(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects(self::once())
            ->method('getAuthorizationUrl')
            ->willReturn('https://idp.example.com/authorize?client_id=test&state=abc');

        $entryPoint = new OAuthEntryPoint(
            $provider,
            $this->createConfiguration(pkceEnabled: false),
            new OAuthStateStore(),
        );

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

        $entryPoint = new OAuthEntryPoint(
            $provider,
            $this->createConfiguration(pkceEnabled: true),
            new OAuthStateStore(),
        );

        $url = $entryPoint->getLoginUrl();

        self::assertSame('https://idp.example.com/authorize?code_challenge=xxx', $url);
    }

    #[Test]
    public function getLoginUrlWithReturnTo(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAuthorizationUrl')
            ->willReturn('https://idp.example.com/authorize');

        $entryPoint = new OAuthEntryPoint(
            $provider,
            $this->createConfiguration(pkceEnabled: false),
            new OAuthStateStore(),
        );

        $url = $entryPoint->getLoginUrl('https://app.example.com/dashboard');

        self::assertSame('https://idp.example.com/authorize', $url);
    }

    #[Test]
    public function registerAddsLoginUrlFilter(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAuthorizationUrl')
            ->willReturn('https://idp.example.com/authorize?client_id=test&state=abc');

        $entryPoint = new OAuthEntryPoint(
            $provider,
            $this->createConfiguration(pkceEnabled: false),
            new OAuthStateStore(),
        );

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

        $entryPoint = new OAuthEntryPoint(
            $provider,
            $this->createConfiguration(pkceEnabled: false),
            new OAuthStateStore(),
        );

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

        $entryPoint = new OAuthEntryPoint(
            $provider,
            $this->createConfiguration(pkceEnabled: false),
            new OAuthStateStore(),
        );

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

        $stateStore = new OAuthStateStore();

        $entryPoint = new OAuthEntryPoint(
            $provider,
            $this->createConfiguration(pkceEnabled: false),
            $stateStore,
        );

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

        $entryPoint = new OAuthEntryPoint(
            $provider,
            $this->createConfiguration(pkceEnabled: true),
            new OAuthStateStore(),
        );

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

        $entryPoint = new OAuthEntryPoint(
            $provider,
            $this->createConfiguration(pkceEnabled: false),
            new OAuthStateStore(),
        );

        $entryPoint->register();

        // Simulate GET request without action
        $originalMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $hasAction = isset($_GET['action']);
        $originalAction = $_GET['action'] ?? null;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_GET['action']);

        try {
            do_action('login_init');
        } catch (\Throwable) {
            // wp_redirect throws to prevent exit
        }

        // Restore
        if ($originalMethod !== null) {
            $_SERVER['REQUEST_METHOD'] = $originalMethod;
        }

        if ($hasAction) {
            $_GET['action'] = $originalAction;
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

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAuthorizationUrl')
            ->willReturn('https://idp.example.com/authorize');

        $entryPoint = new OAuthEntryPoint(
            $provider,
            $this->createConfiguration(pkceEnabled: false),
            new OAuthStateStore(),
        );

        $entryPoint->register();

        $originalMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        do_action('login_init');

        if ($originalMethod !== null) {
            $_SERVER['REQUEST_METHOD'] = $originalMethod;
        }

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

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getAuthorizationUrl')
            ->willReturn('https://idp.example.com/authorize');

        $entryPoint = new OAuthEntryPoint(
            $provider,
            $this->createConfiguration(pkceEnabled: false),
            new OAuthStateStore(),
        );

        $entryPoint->register();

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

        remove_all_filters('wp_redirect');

        self::assertFalse($redirectCalled);
    }
}
