<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Token;

final readonly class DiscoveryDocument
{
    public function __construct(
        private string $issuer,
        private string $authorizationEndpoint,
        private string $tokenEndpoint,
        private ?string $userinfoEndpoint = null,
        private ?string $jwksUri = null,
        private ?string $endSessionEndpoint = null,
        private ?string $revocationEndpoint = null,
    ) {}

    public function getIssuer(): string
    {
        return $this->issuer;
    }

    public function getAuthorizationEndpoint(): string
    {
        return $this->authorizationEndpoint;
    }

    public function getTokenEndpoint(): string
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

    public function getEndSessionEndpoint(): ?string
    {
        return $this->endSessionEndpoint;
    }

    public function getRevocationEndpoint(): ?string
    {
        return $this->revocationEndpoint;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            issuer: $data['issuer'],
            authorizationEndpoint: $data['authorization_endpoint'],
            tokenEndpoint: $data['token_endpoint'],
            userinfoEndpoint: $data['userinfo_endpoint'] ?? null,
            jwksUri: $data['jwks_uri'] ?? null,
            endSessionEndpoint: $data['end_session_endpoint'] ?? null,
            revocationEndpoint: $data['revocation_endpoint'] ?? null,
        );
    }
}
