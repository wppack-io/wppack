<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML\Tests\Multisite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\SAML\Multisite\CrossSiteRedirector;

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

        // Using the current site URL should not need a redirect
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

    #[Test]
    public function redirectThrowsForInvalidTargetUrl(): void
    {
        $redirector = new CrossSiteRedirector();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid target URL');

        $redirector->redirect('not-a-valid-url', 'SAMLResponse', 'RelayState');
    }

    #[Test]
    public function redirectThrowsForDisallowedHost(): void
    {
        $redirector = new CrossSiteRedirector();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not allowed');

        $redirector->redirect('https://evil.example.com/acs', 'SAMLResponse', 'RelayState');
    }

    #[Test]
    public function redirectThrowsForLocalDomain(): void
    {
        $redirector = new CrossSiteRedirector();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not allowed');

        $redirector->redirect('https://evil.local/acs', 'SAMLResponse', 'RelayState');
    }

    #[Test]
    public function redirectThrowsForLocalhostDomain(): void
    {
        $redirector = new CrossSiteRedirector();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not allowed');

        $redirector->redirect('https://evil.localhost/acs', 'SAMLResponse', 'RelayState');
    }

    #[Test]
    public function localDomainAllowedWhenExplicitlyConfigured(): void
    {
        $redirector = new CrossSiteRedirector(
            allowedHosts: ['myapp.local'],
            acsPath: '/sso/verify',
        );

        // Should not throw — the host is explicitly allowed
        // (will exit, so we test via reflection instead)
        $method = new \ReflectionMethod($redirector, 'isHostAllowed');

        self::assertTrue($method->invoke($redirector, 'myapp.local'));
    }

    #[Test]
    public function resolveAcsUrlEnforcesHttps(): void
    {
        $redirector = new CrossSiteRedirector(acsPath: '/sso/verify');

        $method = new \ReflectionMethod($redirector, 'resolveAcsUrl');

        // Even if target uses http, the resolved ACS URL should be https
        $result = $method->invoke($redirector, 'http://example.com/path');
        self::assertStringStartsWith('https://', $result);
        self::assertSame('https://example.com/sso/verify', $result);
    }

    #[Test]
    public function resolveAcsUrlPreservesPort(): void
    {
        $redirector = new CrossSiteRedirector(acsPath: '/sso/verify');

        $method = new \ReflectionMethod($redirector, 'resolveAcsUrl');

        $result = $method->invoke($redirector, 'https://example.com:8443/path');
        self::assertSame('https://example.com:8443/sso/verify', $result);
    }
}
