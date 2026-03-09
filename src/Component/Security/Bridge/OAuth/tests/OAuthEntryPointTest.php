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
        if (function_exists('remove_all_filters')) {
            remove_all_filters('login_url');
        }
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
        if (!function_exists('add_filter')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

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
        if (!function_exists('add_filter')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

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
}
