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

namespace WPPack\Component\Security\Bridge\OAuth\Token;

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
        $httpsEndpoints = [
            'authorization_endpoint',
            'token_endpoint',
            'userinfo_endpoint',
            'jwks_uri',
            'end_session_endpoint',
        ];

        if (!isset($data['issuer'], $data['authorization_endpoint'], $data['token_endpoint'])) {
            throw new \RuntimeException('Discovery document is missing required fields: issuer, authorization_endpoint, token_endpoint.');
        }

        foreach ($httpsEndpoints as $key) {
            if (isset($data[$key]) && !str_starts_with($data[$key], 'https://')) {
                throw new \RuntimeException(\sprintf('Endpoint "%s" must use HTTPS.', $key));
            }
        }

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
