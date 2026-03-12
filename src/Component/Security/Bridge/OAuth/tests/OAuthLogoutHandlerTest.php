<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\OAuthLogoutHandler;
use WpPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;

#[CoversClass(OAuthLogoutHandler::class)]
final class OAuthLogoutHandlerTest extends TestCase
{
    private ?\Closure $suppressCookies = null;

    protected function setUp(): void
    {
        if (function_exists('add_filter')) {
            // Prevent setcookie() calls from wp_clear_auth_cookie() which produce
            // "Cannot modify header information" warnings when running under coverage.
            $this->suppressCookies = static fn(): bool => false;
            add_filter('send_auth_cookies', $this->suppressCookies, \PHP_INT_MAX);
        }
    }

    protected function tearDown(): void
    {
        if ($this->suppressCookies !== null && function_exists('remove_filter')) {
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

        $handler = new OAuthLogoutHandler($provider);

        $url = $handler->initiateLogout();

        self::assertNull($url);
    }

    #[Test]
    public function handleLocalLogout(): void
    {
        if (!function_exists('wp_logout')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $provider = $this->createMock(ProviderInterface::class);
        $handler = new OAuthLogoutHandler($provider);

        $handler->handleLocalLogout();

        self::assertTrue(true);
    }

    #[Test]
    public function supportsRemoteLogoutWithEndpoint(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getEndSessionEndpoint')
            ->willReturn('https://idp.example.com/logout');

        $handler = new OAuthLogoutHandler($provider);

        self::assertTrue($handler->supportsRemoteLogout());
    }

    #[Test]
    public function supportsRemoteLogoutWithoutEndpoint(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getEndSessionEndpoint')->willReturn(null);

        $handler = new OAuthLogoutHandler($provider);

        self::assertFalse($handler->supportsRemoteLogout());
    }
}
