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

final class GenericOidcProvider implements ProviderInterface
{
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
            type: 'oidc',
            label: 'OIDC',
            dropdownLabel: 'Generic OIDC',
            oidc: true,
            requiredFields: ['discovery_url'],
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
            'scope' => implode(' ', $this->configuration->getScopes() ?: ['openid', 'email', 'profile']),
            'state' => $state,
            'nonce' => $nonce,
        ];

        if ($codeChallenge !== null) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = $codeChallengeMethod;
        }

        $authorizationEndpoint = $this->discoveryDocument?->getAuthorizationEndpoint()
            ?? $this->configuration->getAuthorizationEndpoint();

        if ($authorizationEndpoint === null) {
            throw new \RuntimeException('Authorization endpoint is not configured. Provide a discovery URL or set the endpoint explicitly.');
        }

        return $authorizationEndpoint . '?' . http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }

    public function getTokenEndpoint(): string
    {
        $endpoint = $this->discoveryDocument?->getTokenEndpoint()
            ?? $this->configuration->getTokenEndpoint();

        if ($endpoint === null) {
            throw new \RuntimeException('Token endpoint is not configured. Provide a discovery URL or set the endpoint explicitly.');
        }

        return $endpoint;
    }

    public function getUserInfoEndpoint(): ?string
    {
        return $this->discoveryDocument?->getUserinfoEndpoint()
            ?? $this->configuration->getUserinfoEndpoint();
    }

    public function getJwksUri(): ?string
    {
        return $this->discoveryDocument?->getJwksUri()
            ?? $this->configuration->getJwksUri();
    }

    public function getIssuer(): ?string
    {
        return $this->discoveryDocument?->getIssuer()
            ?? $this->configuration->getIssuer();
    }

    public function getEndSessionEndpoint(): ?string
    {
        return $this->discoveryDocument?->getEndSessionEndpoint()
            ?? $this->configuration->getEndSessionEndpoint();
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
