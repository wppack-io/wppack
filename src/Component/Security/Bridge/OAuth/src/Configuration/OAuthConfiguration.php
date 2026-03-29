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

namespace WpPack\Component\Security\Bridge\OAuth\Configuration;

final readonly class OAuthConfiguration
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        private string $clientId,
        #[\SensitiveParameter]
        private string $clientSecret,
        private string $redirectUri,
        private array $scopes = ['openid', 'email', 'profile'],
        private ?string $authorizationEndpoint = null,
        private ?string $tokenEndpoint = null,
        private ?string $userinfoEndpoint = null,
        private ?string $jwksUri = null,
        private ?string $issuer = null,
        private ?string $discoveryUrl = null,
        private ?string $endSessionEndpoint = null,
        private bool $pkceEnabled = true,
    ) {}

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    /**
     * @return list<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getAuthorizationEndpoint(): ?string
    {
        return $this->authorizationEndpoint;
    }

    public function getTokenEndpoint(): ?string
    {
        return $this->tokenEndpoint;
    }

    public function getUserinfoEndpoint(): ?string
    {
        return $this->userinfoEndpoint;
    }

    public function getJwksUri(): ?string
    {
        return $this->jwksUri;
    }

    public function getIssuer(): ?string
    {
        return $this->issuer;
    }

    public function getDiscoveryUrl(): ?string
    {
        return $this->discoveryUrl;
    }

    public function getEndSessionEndpoint(): ?string
    {
        return $this->endSessionEndpoint;
    }

    public function isPkceEnabled(): bool
    {
        return $this->pkceEnabled;
    }
}
