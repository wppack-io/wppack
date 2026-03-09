<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Token;

use WpPack\Component\HttpClient\HttpClient;

final class TokenExchanger
{
    public function __construct(
        private readonly HttpClient $httpClient,
    ) {}

    /**
     * Exchange an authorization code for tokens.
     *
     * @throws \RuntimeException on HTTP or response errors
     */
    public function exchange(
        string $tokenEndpoint,
        string $code,
        string $redirectUri,
        string $clientId,
        string $clientSecret,
        ?string $codeVerifier = null,
    ): OAuthTokenSet {
        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        if ($codeVerifier !== null) {
            $params['code_verifier'] = $codeVerifier;
        }

        $response = $this->httpClient->asForm()->post($tokenEndpoint, [
            'form_params' => $params,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(\sprintf(
                'Token exchange failed with status %d: %s',
                $response->getStatusCode(),
                $response->body(),
            ));
        }

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \RuntimeException(\sprintf(
                'Token exchange error: %s (%s)',
                $data['error_description'] ?? $data['error'],
                $data['error'],
            ));
        }

        return OAuthTokenSet::fromArray($data);
    }
}
