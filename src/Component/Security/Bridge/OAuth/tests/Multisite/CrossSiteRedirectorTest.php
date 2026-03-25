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
        $redirector = new CrossSiteRedirector();

        self::assertNull($redirector->verifyToken('invalid-token'));
    }

    #[Test]
    public function verifyTokenSucceedsWithValidToken(): void
    {
        $token = 'test-valid-token-abc123';
        $userId = 42;
        $timestamp = time();
        $payload = $userId . '|' . $timestamp . '|' . $token;
        $hmac = wp_hash($payload);

        set_transient(
            '_wppack_oauth_xsite_' . hash('sha256', $token),
            [
                'user_id' => $userId,
                'hmac' => $hmac,
                'created_at' => $timestamp,
            ],
            120,
        );

        $redirector = new CrossSiteRedirector();

        self::assertSame($userId, $redirector->verifyToken($token));
    }

    #[Test]
    public function verifyTokenReturnsNullForExpiredToken(): void
    {
        $token = 'test-expired-token-abc123';
        $userId = 42;
        $timestamp = time() - 121;
        $payload = $userId . '|' . $timestamp . '|' . $token;
        $hmac = wp_hash($payload);

        $key = '_wppack_oauth_xsite_' . hash('sha256', $token);

        set_transient(
            $key,
            [
                'user_id' => $userId,
                'hmac' => $hmac,
                'created_at' => $timestamp,
            ],
            300,
        );

        $redirector = new CrossSiteRedirector();

        self::assertNull($redirector->verifyToken($token));
        self::assertFalse(get_transient($key));
    }

    #[Test]
    public function verifyTokenEnforcesOneTimeUse(): void
    {
        $token = 'test-onetime-token-abc123';
        $userId = 42;
        $timestamp = time();
        $payload = $userId . '|' . $timestamp . '|' . $token;
        $hmac = wp_hash($payload);

        set_transient(
            '_wppack_oauth_xsite_' . hash('sha256', $token),
            [
                'user_id' => $userId,
                'hmac' => $hmac,
                'created_at' => $timestamp,
            ],
            120,
        );

        $redirector = new CrossSiteRedirector();

        self::assertSame($userId, $redirector->verifyToken($token));
        self::assertNull($redirector->verifyToken($token));
    }

    #[Test]
    public function verifyTokenReturnsNullForTamperedHmac(): void
    {
        $token = 'test-tampered-token-abc123';
        $userId = 42;
        $timestamp = time();

        set_transient(
            '_wppack_oauth_xsite_' . hash('sha256', $token),
            [
                'user_id' => $userId,
                'hmac' => 'tampered-invalid-hmac-value',
                'created_at' => $timestamp,
            ],
            120,
        );

        $redirector = new CrossSiteRedirector();

        self::assertNull($redirector->verifyToken($token));
    }

    #[Test]
    public function resolveBlogIdReturnsNullWhenNotMultisite(): void
    {
        if (is_multisite()) {
            self::markTestSkipped('This test requires a non-multisite installation.');
        }

        $redirector = new CrossSiteRedirector();

        self::assertNull($redirector->resolveBlogId('https://sub.example.com'));
    }

    #[Test]
    public function resolveBlogIdReturnsNullForInvalidUrl(): void
    {
        $redirector = new CrossSiteRedirector();

        self::assertNull($redirector->resolveBlogId('not-a-url'));
    }

    #[Test]
    public function buildAutoSubmitFormContainsFormElements(): void
    {
        $redirector = new CrossSiteRedirector();

        $method = new \ReflectionMethod($redirector, 'buildAutoSubmitForm');

        $html = $method->invoke(
            $redirector,
            'https://target.example.com/oauth/verify',
            'test-token-value',
            'https://target.example.com/dashboard',
        );

        self::assertStringContainsString('method="POST"', $html);
        self::assertStringContainsString('action="https://target.example.com/oauth/verify"', $html);
        self::assertStringContainsString('name="_wppack_oauth_token"', $html);
        self::assertStringContainsString('value="test-token-value"', $html);
        self::assertStringContainsString('name="returnTo"', $html);
        self::assertStringContainsString('value="https://target.example.com/dashboard"', $html);
        self::assertStringContainsString('document.getElementById', $html);
    }

    #[Test]
    public function buildAutoSubmitFormEscapesHtmlEntities(): void
    {
        $redirector = new CrossSiteRedirector();

        $method = new \ReflectionMethod($redirector, 'buildAutoSubmitForm');

        $html = $method->invoke(
            $redirector,
            'https://target.example.com/oauth/verify?param=value&other=1',
            'token<script>alert(1)</script>',
            'return"onclick="hack',
        );

        // Injected XSS payloads must be HTML-escaped in attribute values
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('token<script>', $html);
        self::assertStringContainsString('&amp;', $html);
        self::assertStringContainsString('&quot;', $html);
    }

    #[Test]
    public function customVerifyPath(): void
    {
        $redirector = new CrossSiteRedirector(verifyPath: '/custom/verify/path');

        $method = new \ReflectionMethod($redirector, 'resolveVerifyUrl');

        $result = $method->invoke($redirector, 'https://example.com/anything');
        self::assertSame('https://example.com/custom/verify/path', $result);
    }

    #[Test]
    public function verifyTokenReturnsNullForZeroUserId(): void
    {
        $token = 'test-zero-user-token';
        $userId = 0;
        $timestamp = time();
        $payload = $userId . '|' . $timestamp . '|' . $token;
        $hmac = wp_hash($payload);

        set_transient(
            '_wppack_oauth_xsite_' . hash('sha256', $token),
            [
                'user_id' => $userId,
                'hmac' => $hmac,
                'created_at' => $timestamp,
            ],
            120,
        );

        $redirector = new CrossSiteRedirector();

        self::assertNull($redirector->verifyToken($token));
    }

    #[Test]
    public function needsRedirectReturnsFalseForUrlWithoutHost(): void
    {
        $redirector = new CrossSiteRedirector();

        self::assertFalse($redirector->needsRedirect('/relative/path'));
    }

    #[Test]
    public function verifyTokenReturnsNullForMissingTransientData(): void
    {
        $redirector = new CrossSiteRedirector();

        // Token with no corresponding transient
        self::assertNull($redirector->verifyToken('completely-nonexistent-token'));
    }

    #[Test]
    public function verifyTokenReturnsNullForNonArrayTransient(): void
    {
        $token = 'test-non-array-token';
        set_transient(
            '_wppack_oauth_xsite_' . hash('sha256', $token),
            'string-value',
            120,
        );

        $redirector = new CrossSiteRedirector();

        self::assertNull($redirector->verifyToken($token));

        // Clean up
        delete_transient('_wppack_oauth_xsite_' . hash('sha256', $token));
    }
}
