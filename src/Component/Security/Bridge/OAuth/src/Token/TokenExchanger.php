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

        if (!str_starts_with($tokenEndpoint, 'https://')) {
            throw new \RuntimeException('Token endpoint must use HTTPS.');
        }

        $response = $this->httpClient->asForm()->post($tokenEndpoint, [
            'form_params' => $params,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Token exchange failed.');
        }

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \RuntimeException('Token exchange failed.');
        }

        return OAuthTokenSet::fromArray($data);
    }
}
