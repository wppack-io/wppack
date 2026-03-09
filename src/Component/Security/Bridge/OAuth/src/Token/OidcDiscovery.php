<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Token;

use WpPack\Component\HttpClient\HttpClient;

final class OidcDiscovery
{
    private const int CACHE_TTL = 86400; // 24 hours
    private const string TRANSIENT_PREFIX = '_wppack_oidc_discovery_';

    public function __construct(
        private readonly HttpClient $httpClient,
    ) {}

    /**
     * Fetch an OIDC discovery document, with optional transient caching.
     *
     * @throws \RuntimeException on HTTP or response errors
     */
    public function discover(string $discoveryUrl): DiscoveryDocument
    {
        $cacheKey = self::TRANSIENT_PREFIX . md5($discoveryUrl);

        if (\function_exists('get_transient')) {
            $cached = get_transient($cacheKey);

            if (\is_array($cached)) {
                return DiscoveryDocument::fromArray($cached);
            }
        }

        $response = $this->httpClient->get($discoveryUrl);

        if (!$response->successful()) {
            throw new \RuntimeException(\sprintf(
                'OIDC discovery failed for "%s" with status %d: %s',
                $discoveryUrl,
                $response->getStatusCode(),
                $response->body(),
            ));
        }

        $data = $response->json();

        if ($data === []) {
            throw new \RuntimeException(\sprintf(
                'OIDC discovery returned invalid JSON from "%s".',
                $discoveryUrl,
            ));
        }

        $document = DiscoveryDocument::fromArray($data);

        if (\function_exists('set_transient')) {
            set_transient($cacheKey, $data, self::CACHE_TTL);
        }

        return $document;
    }
}
