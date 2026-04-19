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

namespace WPPack\Component\Security\Bridge\OAuth\Provider;

use WPPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;

final class LineProvider implements ProviderInterface
{
    private const AUTHORIZATION_ENDPOINT = 'https://access.line.me/oauth2/v2.1/authorize';
    private const TOKEN_ENDPOINT = 'https://api.line.me/oauth2/v2.1/token';
    private const USERINFO_ENDPOINT = 'https://api.line.me/v2/profile';
    private const JWKS_URI = 'https://api.line.me/oauth2/v2.1/certs';
    private const ISSUER = 'https://access.line.me';

    public function __construct(
        private readonly OAuthConfiguration $configuration,
    ) {}

    public static function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            type: 'line',
            label: 'LINE',
            dropdownLabel: 'LINE',
            oidc: true,
        );
    }

    public function getAuthorizationUrl(
        string $state,
        string $nonce,
        ?string $codeChallenge = null,
        string $codeChallengeMethod = 'S256',
    ): string {
        $params = [
            'client_id' => $this->configuration->getClientId(),
            'redirect_uri' => $this->configuration->getRedirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->configuration->getScopes() ?: ['openid', 'profile', 'email']),
            'state' => $state,
            'nonce' => $nonce,
        ];

        if ($codeChallenge !== null) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = $codeChallengeMethod;
        }

        $endpoint = $this->configuration->getAuthorizationEndpoint() ?? self::AUTHORIZATION_ENDPOINT;

        return $endpoint . '?' . http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }

    public function getTokenEndpoint(): string
    {
        return $this->configuration->getTokenEndpoint() ?? self::TOKEN_ENDPOINT;
    }

    public function getUserInfoEndpoint(): string
    {
        return $this->configuration->getUserinfoEndpoint() ?? self::USERINFO_ENDPOINT;
    }

    public function getJwksUri(): string
    {
        return $this->configuration->getJwksUri() ?? self::JWKS_URI;
    }

    public function getIssuer(): string
    {
        return $this->configuration->getIssuer() ?? self::ISSUER;
    }

    public function getEndSessionEndpoint(): ?string
    {
        return null;
    }

    public function normalizeUserInfo(array $data): array
    {
        $normalized = [];

        if (isset($data['userId'])) {
            $normalized['sub'] = (string) $data['userId'];
        }

        if (isset($data['displayName'])) {
            $normalized['name'] = $data['displayName'];
        }

        if (isset($data['pictureUrl'])) {
            $normalized['picture'] = $data['pictureUrl'];
        }

        if (isset($data['email'])) {
            $normalized['email'] = $data['email'];
        }

        return $normalized;
    }

    public function supportsOidc(): bool
    {
        return true;
    }

    public function validateClaims(array $claims): void {}
}
