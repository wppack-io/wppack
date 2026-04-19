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
use WPPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

/**
 * d Account (NTT docomo) OIDC provider.
 *
 * Uses the d Account Connect OpenID Connect endpoints.
 *
 * @see https://id.smt.docomo.ne.jp/src/index_business.html
 */
final class DAccountProvider implements ProviderInterface
{
    private const DISCOVERY_URL = 'https://conf.uw.docomo.ne.jp/.well-known/openid-configuration';
    private const AUTHORIZATION_ENDPOINT = 'https://id.smt.docomo.ne.jp/cgi8/oidc/authorize';
    private const TOKEN_ENDPOINT = 'https://conf.uw.docomo.ne.jp/common/token';
    private const USERINFO_ENDPOINT = 'https://conf.uw.docomo.ne.jp/common/userinfo';
    private const ISSUER = 'https://conf.uw.docomo.ne.jp/';

    private ?DiscoveryDocument $discoveryDocument = null;

    public function __construct(
        private readonly OAuthConfiguration $configuration,
        ?DiscoveryDocument $discoveryDocument = null,
    ) {
        $this->discoveryDocument = $discoveryDocument;
    }

    public static function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            type: 'd-account',
            label: 'dアカウント',
            dropdownLabel: 'dアカウント (d Account)',
            oidc: true,
        );
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
            ?? '';
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
