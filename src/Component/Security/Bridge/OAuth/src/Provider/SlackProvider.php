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

namespace WpPack\Component\Security\Bridge\OAuth\Provider;

use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;

final class SlackProvider implements ProviderInterface
{
    private const AUTHORIZATION_ENDPOINT = 'https://slack.com/openid/connect/authorize';
    private const TOKEN_ENDPOINT = 'https://slack.com/api/openid.connect.token';
    private const USERINFO_ENDPOINT = 'https://slack.com/api/openid.connect.userInfo';
    private const JWKS_URI = 'https://slack.com/openid/connect/keys';
    private const ISSUER = 'https://slack.com';

    public function __construct(
        private readonly OAuthConfiguration $configuration,
    ) {}

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
            'scope' => implode(' ', $this->configuration->getScopes() ?: ['openid', 'email', 'profile']),
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
        return $data;
    }

    public function supportsOidc(): bool
    {
        return true;
    }

    public function validateClaims(array $claims): void {}
}
