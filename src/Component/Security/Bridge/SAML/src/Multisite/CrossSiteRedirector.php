<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML\Multisite;

final class CrossSiteRedirector
{
    /**
     * @param list<string> $allowedHosts
     */
    public function __construct(
        private readonly array $allowedHosts = [],
        private readonly string $acsPath = '/sso/verify',
    ) {}

    public function needsRedirect(string $targetUrl): bool
    {
        $targetHost = parse_url($targetUrl, \PHP_URL_HOST);

        if ($targetHost === null || $targetHost === false) {
            return false;
        }

        $currentHost = parse_url(site_url(), \PHP_URL_HOST);

        // @codeCoverageIgnoreStart
        if ($currentHost === null) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        return $targetHost !== $currentHost;
    }

    /**
     * @codeCoverageIgnore
     */
    public function redirect(string $targetUrl, string $samlResponse, string $relayState): never
    {
        $targetHost = parse_url($targetUrl, \PHP_URL_HOST);

        if ($targetHost === null || $targetHost === false) {
            throw new \RuntimeException('Invalid target URL for cross-site redirect.');
        }

        if (!$this->isHostAllowed($targetHost)) {
            throw new \RuntimeException(\sprintf('Host "%s" is not allowed for cross-site redirect.', $targetHost));
        }

        do_action('wppack_saml_cross_site_redirect', $targetUrl);

        $targetAcsUrl = $this->resolveAcsUrl($targetUrl);

        echo $this->buildAutoSubmitForm($targetAcsUrl, $samlResponse, $relayState);

        exit;
    }

    /**
     * @codeCoverageIgnore Requires multisite installation
     */
    public function resolveBlogId(string $url): ?int
    {
        if (!is_multisite()) {
            return null;
        }

        $host = parse_url($url, \PHP_URL_HOST);
        $path = parse_url($url, \PHP_URL_PATH) ?: '/';

        if ($host === null || $host === false) {
            return null;
        }

        $blogId = get_blog_id_from_url($host, $path);

        return $blogId > 0 ? $blogId : null;
    }

    private function isHostAllowed(string $host): bool
    {
        if (in_array($host, $this->allowedHosts, true)) {
            return true;
        }

        // @codeCoverageIgnoreStart
        if (is_multisite()) {
            $sites = get_sites(['number' => 0]);

            foreach ($sites as $site) {
                if ($site->domain === $host) {
                    return true;
                }
            }
        }
        // @codeCoverageIgnoreEnd

        return false;
    }

    private function resolveAcsUrl(string $targetUrl): string
    {
        $parsed = parse_url($targetUrl);
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        return 'https://' . $host . $port . $this->acsPath;
    }

    private function buildAutoSubmitForm(string $actionUrl, string $samlResponse, string $relayState): string
    {
        $escapedUrl = htmlspecialchars($actionUrl, \ENT_QUOTES, 'UTF-8');
        $escapedResponse = htmlspecialchars($samlResponse, \ENT_QUOTES, 'UTF-8');
        $escapedState = htmlspecialchars($relayState, \ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head><title>Redirecting...</title></head>
            <body>
            <form id="saml-redirect" method="POST" action="{$escapedUrl}">
            <input type="hidden" name="SAMLResponse" value="{$escapedResponse}" />
            <input type="hidden" name="RelayState" value="{$escapedState}" />
            <noscript><button type="submit">Continue</button></noscript>
            </form>
            <script>document.getElementById('saml-redirect').submit();</script>
            </body>
            </html>
            HTML;
    }
}
