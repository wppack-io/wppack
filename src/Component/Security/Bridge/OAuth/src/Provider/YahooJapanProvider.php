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
use WpPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

/**
 * Yahoo! JAPAN (YConnect v2) OIDC provider.
 *
 * @see https://developer.yahoo.co.jp/yconnect/v2/
 */
final class YahooJapanProvider implements ProviderInterface
{
    private const DISCOVERY_URL = 'https://auth.login.yahoo.co.jp/yconnect/v2/.well-known/openid-configuration';
    private const AUTHORIZATION_ENDPOINT = 'https://auth.login.yahoo.co.jp/yconnect/v2/authorization';
    private const TOKEN_ENDPOINT = 'https://auth.login.yahoo.co.jp/yconnect/v2/token';
    private const USERINFO_ENDPOINT = 'https://userinfo.yahooapis.jp/yconnect/v2/attribute';
    private const JWKS_URI = 'https://auth.login.yahoo.co.jp/yconnect/v2/jwks';
    private const ISSUER = 'https://auth.login.yahoo.co.jp/yconnect/v2';

    private ?DiscoveryDocument $discoveryDocument = null;

    public function __construct(
        private readonly OAuthConfiguration $configuration,
        ?DiscoveryDocument $discoveryDocument = null,
    ) {
        $this->discoveryDocument = $discoveryDocument;
    }

    public function getDiscoveryUrl(): string
    {
        return self::DISCOVERY_URL;
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
            'scope' => implode(' ', $this->configuration->getScopes() ?: ['openid', 'email', 'profile']),
            'state' => $state,
            'nonce' => $nonce,
        ];

        if ($codeChallenge !== null) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = $codeChallengeMethod;
        }

        $endpoint = $this->discoveryDocument?->getAuthorizationEndpoint()
            ?? $this->configuration->getAuthorizationEndpoint()
            ?? self::AUTHORIZATION_ENDPOINT;

        return $endpoint . '?' . http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }

    public function getTokenEndpoint(): string
    {
        return $this->discoveryDocument?->getTokenEndpoint()
            ?? $this->configuration->getTokenEndpoint()
            ?? self::TOKEN_ENDPOINT;
    }

    public function getUserInfoEndpoint(): string
    {
        return $this->discoveryDocument?->getUserinfoEndpoint()
            ?? $this->configuration->getUserinfoEndpoint()
            ?? self::USERINFO_ENDPOINT;
    }

    public function getJwksUri(): string
    {
        return $this->discoveryDocument?->getJwksUri()
            ?? $this->configuration->getJwksUri()
            ?? self::JWKS_URI;
    }

    public function getIssuer(): string
    {
        return $this->discoveryDocument?->getIssuer()
            ?? $this->configuration->getIssuer()
            ?? self::ISSUER;
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

    public function setDiscoveryDocument(DiscoveryDocument $discoveryDocument): void
    {
        $this->discoveryDocument = $discoveryDocument;
    }
}
