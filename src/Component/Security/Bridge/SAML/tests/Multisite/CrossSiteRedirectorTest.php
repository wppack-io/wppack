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

namespace WPPack\Component\Security\Bridge\SAML\Tests\Multisite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Bridge\SAML\Multisite\CrossSiteRedirector;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Component\Site\SiteRepositoryInterface;

#[CoversClass(CrossSiteRedirector::class)]
final class CrossSiteRedirectorTest extends TestCase
{
    private function multisiteContext(): BlogContextInterface
    {
        return new class implements BlogContextInterface {
            public function getCurrentBlogId(): int
            {
                return 1;
            }

            public function isMultisite(): bool
            {
                return true;
            }

            public function getMainSiteId(): int
            {
                return 1;
            }

            public function isSwitched(): bool
            {
                return false;
            }

            public function isMainSite(): bool
            {
                return true;
            }

            public function isSubdomainInstall(): bool
            {
                return false;
            }
        };
    }

    /**
     * @param array<string, \WP_Site> $sitesByUrl keyed by "host|path"
     * @param list<string> $domains
     */
    private function fixtureSiteRepository(array $sitesByUrl = [], array $domains = []): SiteRepositoryInterface
    {
        return new class ($sitesByUrl, $domains) implements SiteRepositoryInterface {
            /**
             * @param array<string, \WP_Site> $sitesByUrl
             * @param list<string> $domains
             */
            public function __construct(
                private array $sitesByUrl,
                private array $domains,
            ) {}

            public function findAll(array $args = []): array
            {
                return [];
            }

            public function find(int $blogId): ?\WP_Site
            {
                return null;
            }

            public function findByUrl(string $domain, string $path = '/'): ?\WP_Site
            {
                return $this->sitesByUrl[$domain . '|' . $path] ?? null;
            }

            public function findBySlug(string $slug): ?\WP_Site
            {
                return null;
            }

            public function getAllDomains(): array
            {
                return $this->domains;
            }

            public function getMeta(int $blogId, string $key = '', bool $single = false): mixed
            {
                return $single ? '' : [];
            }

            public function addMeta(int $blogId, string $key, mixed $value, bool $unique = false): ?int
            {
                return null;
            }

            public function updateMeta(int $blogId, string $key, mixed $value, mixed $previousValue = ''): int|bool
            {
                return false;
            }

            public function deleteMeta(int $blogId, string $key, mixed $value = ''): bool
            {
                return false;
            }
        };
    }

    #[Test]
    public function needsRedirectReturnsFalseForSameHost(): void
    {
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
        $redirector = new CrossSiteRedirector();

        self::assertTrue($redirector->needsRedirect('https://different.example.com/path'));
    }

    #[Test]
    public function needsRedirectReturnsFalseForUrlWithoutHost(): void
    {
        $redirector = new CrossSiteRedirector();

        // A path-only URL has no host
        self::assertFalse($redirector->needsRedirect('/relative/path'));
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
            acsPath: '/saml/acs',
        );

        // Should not throw -- the host is explicitly allowed
        // (will exit, so we test via reflection instead)
        $method = new \ReflectionMethod($redirector, 'isHostAllowed');

        self::assertTrue($method->invoke($redirector, 'myapp.local'));
    }

    #[Test]
    public function isHostAllowedReturnsFalseForUnknownHost(): void
    {
        $redirector = new CrossSiteRedirector(allowedHosts: ['allowed.example.com']);

        $method = new \ReflectionMethod($redirector, 'isHostAllowed');

        self::assertFalse($method->invoke($redirector, 'unknown.example.com'));
    }

    #[Test]
    public function resolveAcsUrlEnforcesHttps(): void
    {
        $redirector = new CrossSiteRedirector(acsPath: '/saml/acs');

        $method = new \ReflectionMethod($redirector, 'resolveAcsUrl');

        // Even if target uses http, the resolved ACS URL should be https
        $result = $method->invoke($redirector, 'http://example.com/path');
        self::assertStringStartsWith('https://', $result);
        self::assertSame('https://example.com/saml/acs', $result);
    }

    #[Test]
    public function resolveAcsUrlPreservesPort(): void
    {
        $redirector = new CrossSiteRedirector(acsPath: '/saml/acs');

        $method = new \ReflectionMethod($redirector, 'resolveAcsUrl');

        $result = $method->invoke($redirector, 'https://example.com:8443/path');
        self::assertSame('https://example.com:8443/saml/acs', $result);
    }

    #[Test]
    public function buildAutoSubmitFormContainsFormElements(): void
    {
        $redirector = new CrossSiteRedirector();

        $method = new \ReflectionMethod($redirector, 'buildAutoSubmitForm');

        $html = $method->invoke(
            $redirector,
            'https://target.example.com/saml/acs',
            'base64SAMLResponse',
            'https://target.example.com/dashboard',
        );

        self::assertStringContainsString('method="POST"', $html);
        self::assertStringContainsString('action="https://target.example.com/saml/acs"', $html);
        self::assertStringContainsString('name="SAMLResponse"', $html);
        self::assertStringContainsString('value="base64SAMLResponse"', $html);
        self::assertStringContainsString('name="RelayState"', $html);
        self::assertStringContainsString('document.getElementById', $html);
    }

    #[Test]
    public function buildAutoSubmitFormEscapesHtmlEntities(): void
    {
        $redirector = new CrossSiteRedirector();

        $method = new \ReflectionMethod($redirector, 'buildAutoSubmitForm');

        $html = $method->invoke(
            $redirector,
            'https://target.example.com/saml/acs?param=value&other=1',
            'response<script>alert(1)</script>',
            'state"onclick="hack',
        );

        // Injected XSS payloads must be HTML-escaped in attribute values
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('response<script>', $html);
        self::assertStringContainsString('&amp;', $html);
        self::assertStringContainsString('&quot;', $html);
    }

    #[Test]
    public function customAcsPath(): void
    {
        $redirector = new CrossSiteRedirector(acsPath: '/custom/acs/path');

        $method = new \ReflectionMethod($redirector, 'resolveAcsUrl');

        $result = $method->invoke($redirector, 'https://example.com/anything');
        self::assertSame('https://example.com/custom/acs/path', $result);
    }

    #[Test]
    public function resolveBlogIdReturnsNullForUrlWithoutHost(): void
    {
        $redirector = new CrossSiteRedirector();

        self::assertNull($redirector->resolveBlogId('/relative/path'));
    }

    #[Test]
    public function isHostAllowedReturnsFalseForEmptyAllowedHosts(): void
    {
        if (is_multisite()) {
            self::markTestSkipped('This test requires a non-multisite installation.');
        }

        $redirector = new CrossSiteRedirector(allowedHosts: []);

        $method = new \ReflectionMethod($redirector, 'isHostAllowed');

        self::assertFalse($method->invoke($redirector, 'any.example.com'));
    }

    #[Test]
    public function resolveAcsUrlWithoutPort(): void
    {
        $redirector = new CrossSiteRedirector(acsPath: '/saml/acs');

        $method = new \ReflectionMethod($redirector, 'resolveAcsUrl');

        $result = $method->invoke($redirector, 'https://example.com/path/to/resource');
        self::assertSame('https://example.com/saml/acs', $result);
    }

    #[Test]
    public function needsRedirectReturnsFalseForRelativePath(): void
    {
        $redirector = new CrossSiteRedirector();

        self::assertFalse($redirector->needsRedirect('/some-path'));
    }

    #[Test]
    public function resolveBlogIdReturnsNullForNonMultisite(): void
    {
        if (is_multisite()) {
            self::markTestSkipped('This test requires a non-multisite installation.');
        }

        $redirector = new CrossSiteRedirector();

        // Even with a valid URL, should return null in non-multisite
        self::assertNull($redirector->resolveBlogId('https://example.com/some-path'));
    }

    #[Test]
    public function resolveBlogIdReturnsNullForRelativePathOnly(): void
    {
        $redirector = new CrossSiteRedirector();

        // A relative path has no host
        self::assertNull($redirector->resolveBlogId('/just-a-path'));
    }

    #[Test]
    public function isHostAllowedReturnsTrueForExplicitlyAllowedHost(): void
    {
        $redirector = new CrossSiteRedirector(
            allowedHosts: ['allowed1.example.com', 'allowed2.example.com'],
        );

        $method = new \ReflectionMethod($redirector, 'isHostAllowed');

        self::assertTrue($method->invoke($redirector, 'allowed1.example.com'));
        self::assertTrue($method->invoke($redirector, 'allowed2.example.com'));
        self::assertFalse($method->invoke($redirector, 'notallowed.example.com'));
    }

    #[Test]
    public function buildAutoSubmitFormContainsNoScriptButton(): void
    {
        $redirector = new CrossSiteRedirector();

        $method = new \ReflectionMethod($redirector, 'buildAutoSubmitForm');

        $html = $method->invoke(
            $redirector,
            'https://target.example.com/acs',
            'saml-response',
            'relay-state',
        );

        self::assertStringContainsString('<noscript>', $html);
        self::assertStringContainsString('<button type="submit">', $html);
    }

    #[Test]
    public function resolveBlogIdReturnsBlogIdFromSiteRepositoryOnMultisite(): void
    {
        if (!class_exists(\WP_Site::class)) {
            self::markTestSkipped('WP_Site is not loaded in this runtime.');
        }

        $site = new \WP_Site((object) ['blog_id' => '7']);
        $repo = $this->fixtureSiteRepository(['sub.example.com|/app' => $site]);

        $redirector = new CrossSiteRedirector(
            blogContext: $this->multisiteContext(),
            siteRepository: $repo,
        );

        self::assertSame(7, $redirector->resolveBlogId('https://sub.example.com/app'));
    }

    #[Test]
    public function resolveBlogIdReturnsNullWhenSiteRepositoryFindsNothing(): void
    {
        if (!class_exists(\WP_Site::class)) {
            self::markTestSkipped('WP_Site is not loaded in this runtime.');
        }

        $repo = $this->fixtureSiteRepository();

        $redirector = new CrossSiteRedirector(
            blogContext: $this->multisiteContext(),
            siteRepository: $repo,
        );

        self::assertNull($redirector->resolveBlogId('https://sub.example.com/unknown'));
    }

    #[Test]
    public function isHostAllowedAcceptsNetworkDomainsOnMultisite(): void
    {
        $repo = $this->fixtureSiteRepository(domains: ['a.example.com', 'b.example.com']);

        $redirector = new CrossSiteRedirector(
            blogContext: $this->multisiteContext(),
            siteRepository: $repo,
        );

        $method = new \ReflectionMethod($redirector, 'isHostAllowed');

        self::assertTrue($method->invoke($redirector, 'a.example.com'));
        self::assertTrue($method->invoke($redirector, 'b.example.com'));
        self::assertFalse($method->invoke($redirector, 'unknown.example.com'));
    }
}
