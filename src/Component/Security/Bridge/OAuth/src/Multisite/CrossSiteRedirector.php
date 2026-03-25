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

namespace WpPack\Component\Security\Bridge\OAuth\Multisite;

use WpPack\Component\Site\BlogContext;
use WpPack\Component\Site\BlogContextInterface;
use WpPack\Component\Site\SiteRepository;
use WpPack\Component\Site\SiteRepositoryInterface;

final class CrossSiteRedirector
{
    private const TOKEN_TTL = 120;
    private const TRANSIENT_PREFIX = '_wppack_oauth_xsite_';

    /**
     * @param list<string> $allowedHosts
     */
    public function __construct(
        private readonly array $allowedHosts = [],
        private readonly string $verifyPath = '/oauth/verify',
        private readonly BlogContextInterface $blogContext = new BlogContext(),
        private readonly SiteRepositoryInterface $siteRepository = new SiteRepository(),
    ) {}

    public function needsRedirect(string $targetUrl): bool
    {
        $targetHost = parse_url($targetUrl, \PHP_URL_HOST);

        if ($targetHost === null || $targetHost === false) {
            return false;
        }

        $currentHost = parse_url(site_url(), \PHP_URL_HOST);

        if ($currentHost === null) {
            return false;
        }

        return $targetHost !== $currentHost;
    }

    /**
     * Generate HMAC-signed one-time token, store in transient, output auto-submit form.
     *
     * @return never
     */
    public function redirect(string $targetUrl, int $userId, string $returnTo): never
    {
        $targetHost = parse_url($targetUrl, \PHP_URL_HOST);

        if ($targetHost === null || $targetHost === false) {
            throw new \RuntimeException('Invalid target URL for cross-site redirect.');
        }

        if (!$this->isHostAllowed($targetHost)) {
            throw new \RuntimeException(\sprintf('Host "%s" is not allowed for cross-site redirect.', $targetHost));
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $timestamp = time();

        $payload = $userId . '|' . $timestamp . '|' . $token;
        $hmac = wp_hash($payload);

        set_transient(
            self::TRANSIENT_PREFIX . hash('sha256', $token),
            [
                'user_id' => $userId,
                'hmac' => $hmac,
                'created_at' => $timestamp,
            ],
            self::TOKEN_TTL,
        );

        do_action('wppack_oauth_cross_site_redirect', $targetUrl);

        $verifyUrl = $this->resolveVerifyUrl($targetUrl);

        echo $this->buildAutoSubmitForm($verifyUrl, $token, $returnTo);

        exit;
    }

    /**
     * Verify a cross-site token. One-time use (deleted after verification).
     *
     * @return int|null User ID or null if invalid
     */
    public function verifyToken(string $token): ?int
    {
        $key = self::TRANSIENT_PREFIX . hash('sha256', $token);
        $data = get_transient($key);

        if (!\is_array($data)) {
            return null;
        }

        $userId = (int) ($data['user_id'] ?? 0);
        $storedHmac = (string) ($data['hmac'] ?? '');
        $createdAt = (int) ($data['created_at'] ?? 0);

        // Check expiry
        if ((time() - $createdAt) > self::TOKEN_TTL) {
            delete_transient($key);
            return null;
        }

        // Verify HMAC
        $payload = $userId . '|' . $createdAt . '|' . $token;
        $expectedHmac = wp_hash($payload);

        if (!hash_equals($expectedHmac, $storedHmac)) {
            return null;
        }

        // One-time use: delete after successful verification
        delete_transient($key);

        return $userId > 0 ? $userId : null;
    }

    public function resolveBlogId(string $url): ?int
    {
        if (!$this->blogContext->isMultisite()) {
            return null;
        }

        $host = parse_url($url, \PHP_URL_HOST);
        $path = parse_url($url, \PHP_URL_PATH) ?: '/';

        if ($host === null || $host === false) {
            return null;
        }

        $site = $this->siteRepository->findByUrl($host, $path);

        return $site !== null ? (int) $site->blog_id : null;
    }

    private function isHostAllowed(string $host): bool
    {
        if (in_array($host, $this->allowedHosts, true)) {
            return true;
        }

        if ($this->blogContext->isMultisite()) {
            $domains = $this->siteRepository->getAllDomains();

            if (in_array($host, $domains, true)) {
                return true;
            }
        }

        return false;
    }

    private function resolveVerifyUrl(string $targetUrl): string
    {
        $parsed = parse_url($targetUrl);
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        return 'https://' . $host . $port . $this->verifyPath;
    }

    private function buildAutoSubmitForm(string $actionUrl, string $token, string $returnTo): string
    {
        $escapedUrl = htmlspecialchars($actionUrl, \ENT_QUOTES, 'UTF-8');
        $escapedToken = htmlspecialchars($token, \ENT_QUOTES, 'UTF-8');
        $escapedReturnTo = htmlspecialchars($returnTo, \ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head><title>Redirecting...</title></head>
            <body>
            <form id="oauth-redirect" method="POST" action="{$escapedUrl}">
            <input type="hidden" name="_wppack_oauth_token" value="{$escapedToken}" />
            <input type="hidden" name="returnTo" value="{$escapedReturnTo}" />
            <noscript><button type="submit">Continue</button></noscript>
            </form>
            <script>document.getElementById('oauth-redirect').submit();</script>
            </body>
            </html>
            HTML;
    }
}
