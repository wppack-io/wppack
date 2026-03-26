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

namespace WpPack\Component\Security\Bridge\OAuth\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Bridge\OAuth\OAuthLogoutHandler;
use WpPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;

#[CoversClass(OAuthLogoutHandler::class)]
final class OAuthLogoutHandlerTest extends TestCase
{
    private ?\Closure $suppressCookies = null;

    protected function setUp(): void
    {
        // Prevent setcookie() calls from wp_clear_auth_cookie() which produce
        // "Cannot modify header information" warnings when running under coverage.
        $this->suppressCookies = static fn(): bool => false;
        add_filter('send_auth_cookies', $this->suppressCookies, \PHP_INT_MAX);
    }

    protected function tearDown(): void
    {
        if ($this->suppressCookies !== null) {
            remove_filter('send_auth_cookies', $this->suppressCookies, \PHP_INT_MAX);
            $this->suppressCookies = null;
        }
    }

    #[Test]
    public function initiateLogoutWithRpInitiatedLogout(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getEndSessionEndpoint')
            ->willReturn('https://idp.example.com/logout');

        $handler = new OAuthLogoutHandler(
            $provider,
            new AuthenticationSession(),
            'https://app.example.com/',
        );

        $url = $handler->initiateLogout('id-token-value', 'https://app.example.com/logged-out');

        self::assertNotNull($url);
        self::assertStringStartsWith('https://idp.example.com/logout?', $url);
        self::assertStringContainsString('id_token_hint=id-token-value', $url);
        self::assertStringContainsString('post_logout_redirect_uri=', $url);
    }

    #[Test]
    public function initiateLogoutWithLocalOnly(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getEndSessionEndpoint')->willReturn(null);

        $handler = new OAuthLogoutHandler($provider, new AuthenticationSession());

        $url = $handler->initiateLogout();

        self::assertNull($url);
    }

    #[Test]
    public function handleLocalLogout(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $handler = new OAuthLogoutHandler($provider, new AuthenticationSession());

        $handler->handleLocalLogout();

        self::assertTrue(true);
    }

    #[Test]
    public function supportsRemoteLogoutWithEndpoint(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getEndSessionEndpoint')
            ->willReturn('https://idp.example.com/logout');

        $handler = new OAuthLogoutHandler($provider, new AuthenticationSession());

        self::assertTrue($handler->supportsRemoteLogout());
    }

    #[Test]
    public function supportsRemoteLogoutWithoutEndpoint(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getEndSessionEndpoint')->willReturn(null);

        $handler = new OAuthLogoutHandler($provider, new AuthenticationSession());

        self::assertFalse($handler->supportsRemoteLogout());
    }

    #[Test]
    public function initiateLogoutWithEndpointNoIdTokenAndNoReturnTo(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getEndSessionEndpoint')
            ->willReturn('https://idp.example.com/logout');

        $handler = new OAuthLogoutHandler($provider, new AuthenticationSession());

        $url = $handler->initiateLogout();

        self::assertNotNull($url);
        // No params => no query string
        self::assertSame('https://idp.example.com/logout', $url);
    }

    #[Test]
    public function initiateLogoutUsesRedirectAfterLogoutFallback(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getEndSessionEndpoint')
            ->willReturn('https://idp.example.com/logout');

        $handler = new OAuthLogoutHandler(
            $provider,
            new AuthenticationSession(),
            'https://app.example.com/home',
        );

        // No returnTo passed, should fall back to redirectAfterLogout
        $url = $handler->initiateLogout('id-token-value');

        self::assertNotNull($url);
        self::assertStringContainsString('id_token_hint=id-token-value', $url);
        self::assertStringContainsString('post_logout_redirect_uri=', $url);
        self::assertStringContainsString(urlencode('https://app.example.com/home'), $url);
    }

    #[Test]
    public function initiateLogoutReturnToOverridesRedirectAfterLogout(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getEndSessionEndpoint')
            ->willReturn('https://idp.example.com/logout');

        $handler = new OAuthLogoutHandler(
            $provider,
            new AuthenticationSession(),
            'https://app.example.com/fallback',
        );

        $url = $handler->initiateLogout(null, 'https://app.example.com/custom');

        self::assertNotNull($url);
        self::assertStringNotContainsString('id_token_hint', $url);
        self::assertStringContainsString(urlencode('https://app.example.com/custom'), $url);
    }

    #[Test]
    public function initiateLogoutWithIdTokenOnly(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getEndSessionEndpoint')
            ->willReturn('https://idp.example.com/logout');

        $handler = new OAuthLogoutHandler($provider, new AuthenticationSession());

        $url = $handler->initiateLogout('my-id-token');

        self::assertNotNull($url);
        self::assertStringContainsString('id_token_hint=my-id-token', $url);
        self::assertStringNotContainsString('post_logout_redirect_uri', $url);
    }
}
