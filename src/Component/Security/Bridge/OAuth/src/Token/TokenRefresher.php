<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Token;

use WpPack\Component\HttpClient\HttpClient;

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

        $response = $this->httpClient->asForm()->post($tokenEndpoint, [
            'form_params' => $params,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(\sprintf(
                'Token refresh failed with status %d: %s',
                $response->getStatusCode(),
                $response->body(),
            ));
        }

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \RuntimeException(\sprintf(
                'Token refresh error: %s (%s)',
                $data['error_description'] ?? $data['error'],
                $data['error'],
            ));
        }

        return OAuthTokenSet::fromArray($data);
    }
}
