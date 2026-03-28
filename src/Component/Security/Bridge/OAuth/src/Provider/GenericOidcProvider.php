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

final class GenericOidcProvider implements ProviderInterface
{
    private ?DiscoveryDocument $discoveryDocument = null;

    public function __construct(
        private readonly OAuthConfiguration $configuration,
        ?DiscoveryDocument $discoveryDocument = null,
    ) {
        $this->discoveryDocument = $discoveryDocument;
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
            'scope' => implode(' ', $this->configuration->getScopes()),
            'state' => $state,
            'nonce' => $nonce,
        ];

        if ($codeChallenge !== null) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = $codeChallengeMethod;
        }

        $authorizationEndpoint = $this->discoveryDocument?->getAuthorizationEndpoint()
            ?? $this->configuration->getAuthorizationEndpoint();

        return $authorizationEndpoint . '?' . http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }

    public function getTokenEndpoint(): string
    {
        return $this->discoveryDocument?->getTokenEndpoint()
            ?? $this->configuration->getTokenEndpoint();
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

    public function setDiscoveryDocument(DiscoveryDocument $discoveryDocument): void
    {
        $this->discoveryDocument = $discoveryDocument;
    }
}
