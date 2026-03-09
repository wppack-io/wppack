<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Token;

use WpPack\Component\HttpClient\HttpClient;

final class JwksProvider
{
    private const int CACHE_TTL = 3600; // 1 hour
    private const string TRANSIENT_PREFIX = '_wppack_oauth_jwks_';

    public function __construct(
        private readonly HttpClient $httpClient,
    ) {}

    /**
     * Fetch a JWKS key set, with optional transient caching.
     *
     * @return array<int, array<string, mixed>> The JWKS keys array
     * @throws \RuntimeException on HTTP or response errors
     */
    public function getKeys(string $jwksUri): array
    {
        $cacheKey = self::TRANSIENT_PREFIX . md5($jwksUri);

        if (\function_exists('get_transient')) {
            $cached = get_transient($cacheKey);

            if (\is_array($cached)) {
                return $cached;
            }
        }

        $response = $this->httpClient->get($jwksUri);

        if (!$response->successful()) {
            throw new \RuntimeException(\sprintf(
                'JWKS fetch failed for "%s" with status %d: %s',
                $jwksUri,
                $response->getStatusCode(),
                $response->body(),
            ));
        }

        $data = $response->json();

        if (!isset($data['keys']) || !\is_array($data['keys'])) {
            throw new \RuntimeException(\sprintf(
                'JWKS response from "%s" does not contain a valid "keys" array.',
                $jwksUri,
            ));
        }

        /** @var array<int, array<string, mixed>> $keys */
        $keys = $data['keys'];

        if (\function_exists('set_transient')) {
            set_transient($cacheKey, $keys, self::CACHE_TTL);
        }

        return $keys;
    }
}
