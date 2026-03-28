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

namespace WpPack\Component\Security\Bridge\OAuth\Token;

use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\Transient\TransientManager;

final class JwksProvider
{
    private const CACHE_TTL = 3600; // 1 hour
    private const TRANSIENT_PREFIX = '_wppack_oauth_jwks_';

    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly TransientManager $transientManager,
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

        $cached = $this->transientManager->get($cacheKey);

        if (\is_array($cached)) {
            return $cached;
        }

        if (!str_starts_with($jwksUri, 'https://')) {
            throw new \RuntimeException('JWKS URI must use HTTPS.');
        }

        $response = $this->httpClient->get($jwksUri);

        if (!$response->successful()) {
            throw new \RuntimeException('JWKS fetch failed.');
        }

        $data = $response->json();

        if (!isset($data['keys']) || !\is_array($data['keys'])) {
            throw new \RuntimeException('JWKS response does not contain a valid "keys" array.');
        }

        /** @var array<int, array<string, mixed>> $keys */
        $keys = $data['keys'];

        $this->transientManager->set($cacheKey, $keys, self::CACHE_TTL);

        return $keys;
    }
}
