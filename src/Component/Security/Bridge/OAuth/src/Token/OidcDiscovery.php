<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Token;

use WpPack\Component\HttpClient\HttpClient;

final class OidcDiscovery
{
    private const CACHE_TTL = 86400; // 24 hours
    private const TRANSIENT_PREFIX = '_wppack_oidc_discovery_';

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

        $cached = get_transient($cacheKey);

        if (\is_array($cached)) {
            return DiscoveryDocument::fromArray($cached);
        }

        if (!str_starts_with($discoveryUrl, 'https://')) {
            throw new \RuntimeException('Discovery URL must use HTTPS.');
        }

        $response = $this->httpClient->get($discoveryUrl);

        if (!$response->successful()) {
            throw new \RuntimeException('OIDC discovery failed.');
        }

        $data = $response->json();

        if ($data === []) {
            throw new \RuntimeException('OIDC discovery returned invalid JSON.');
        }

        $document = DiscoveryDocument::fromArray($data);

        set_transient($cacheKey, $data, self::CACHE_TTL);

        return $document;
    }
}
