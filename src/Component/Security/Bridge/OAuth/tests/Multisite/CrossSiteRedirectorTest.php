<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Multisite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\Multisite\CrossSiteRedirector;

#[CoversClass(CrossSiteRedirector::class)]
final class CrossSiteRedirectorTest extends TestCase
{
    #[Test]
    public function needsRedirectReturnsFalseForSameHost(): void
    {
        if (!function_exists('site_url')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $redirector = new CrossSiteRedirector();

        self::assertFalse($redirector->needsRedirect(site_url('/some-path')));
    }

    #[Test]
    public function needsRedirectReturnsFalseForInvalidUrl(): void
    {
        $redirector = new CrossSiteRedirector();

        self::assertFalse($redirector->needsRedirect('not-a-url'));
    }

    #[Test]
    public function needsRedirectReturnsTrueForDifferentHost(): void
    {
        if (!function_exists('site_url')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $redirector = new CrossSiteRedirector();

        self::assertTrue($redirector->needsRedirect('https://different.example.com/path'));
    }

    #[Test]
    public function redirectThrowsForInvalidTargetUrl(): void
    {
        $redirector = new CrossSiteRedirector();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid target URL');

        $redirector->redirect('not-a-valid-url', 1, '/return');
    }

    #[Test]
    public function redirectThrowsForDisallowedHost(): void
    {
        $redirector = new CrossSiteRedirector();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not allowed');

        $redirector->redirect('https://evil.example.com/path', 1, '/return');
    }

    #[Test]
    public function redirectThrowsForLocalDomain(): void
    {
        $redirector = new CrossSiteRedirector();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not allowed');

        $redirector->redirect('https://evil.local/path', 1, '/return');
    }

    #[Test]
    public function redirectThrowsForLocalhostDomain(): void
    {
        $redirector = new CrossSiteRedirector();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not allowed');

        $redirector->redirect('https://evil.localhost/path', 1, '/return');
    }

    #[Test]
    public function localDomainAllowedWhenExplicitlyConfigured(): void
    {
        $redirector = new CrossSiteRedirector(
            allowedHosts: ['myapp.local'],
            verifyPath: '/oauth/verify',
        );

        $method = new \ReflectionMethod($redirector, 'isHostAllowed');

        self::assertTrue($method->invoke($redirector, 'myapp.local'));
    }

    #[Test]
    public function resolveVerifyUrlEnforcesHttps(): void
    {
        $redirector = new CrossSiteRedirector(verifyPath: '/oauth/verify');

        $method = new \ReflectionMethod($redirector, 'resolveVerifyUrl');

        $result = $method->invoke($redirector, 'http://example.com/path');
        self::assertStringStartsWith('https://', $result);
        self::assertSame('https://example.com/oauth/verify', $result);
    }

    #[Test]
    public function resolveVerifyUrlPreservesPort(): void
    {
        $redirector = new CrossSiteRedirector(verifyPath: '/oauth/verify');

        $method = new \ReflectionMethod($redirector, 'resolveVerifyUrl');

        $result = $method->invoke($redirector, 'https://example.com:8443/path');
        self::assertSame('https://example.com:8443/oauth/verify', $result);
    }

    #[Test]
    public function verifyTokenReturnsNullForInvalidToken(): void
    {
        if (!function_exists('get_transient')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $redirector = new CrossSiteRedirector();

        self::assertNull($redirector->verifyToken('invalid-token'));
    }

    #[Test]
    public function resolveBlogIdReturnsNullWhenNotMultisite(): void
    {
        if (!function_exists('is_multisite')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        if (is_multisite()) {
            self::markTestSkipped('This test requires a non-multisite installation.');
        }

        $redirector = new CrossSiteRedirector();

        self::assertNull($redirector->resolveBlogId('https://sub.example.com'));
    }
}
