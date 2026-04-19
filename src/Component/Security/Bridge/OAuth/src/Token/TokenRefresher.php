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

namespace WPPack\Component\Security\Bridge\OAuth\Token;

use WPPack\Component\HttpClient\HttpClient;

final class TokenRefresher
{
    public function __construct(
        private readonly HttpClient $httpClient,
    ) {}

    /**
     * Refresh tokens using a refresh token grant.
     *
     * @param list<string>|null $scopes
     * @throws \RuntimeException on HTTP or response errors
     */
    public function refresh(
        string $tokenEndpoint,
        string $refreshToken,
        string $clientId,
        string $clientSecret,
        ?array $scopes = null,
    ): OAuthTokenSet {
        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        if ($scopes !== null) {
            $params['scope'] = implode(' ', $scopes);
        }

        if (!str_starts_with($tokenEndpoint, 'https://')) {
            throw new \RuntimeException('Token endpoint must use HTTPS.');
        }

        $response = $this->httpClient->asForm()->post($tokenEndpoint, [
            'form_params' => $params,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Token refresh failed.');
        }

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \RuntimeException('Token refresh failed.');
        }

        return OAuthTokenSet::fromArray($data);
    }
}
