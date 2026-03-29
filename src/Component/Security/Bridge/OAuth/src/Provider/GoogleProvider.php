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
use WpPack\Component\Security\Exception\AuthenticationException;

final class GoogleProvider implements ProviderInterface
{
    public const DISCOVERY_URL = 'https://accounts.google.com/.well-known/openid-configuration';

    private const DEFAULT_AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const DEFAULT_TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const DEFAULT_USERINFO_ENDPOINT = 'https://openidconnect.googleapis.com/v1/userinfo';
    private const DEFAULT_JWKS_URI = 'https://www.googleapis.com/oauth2/v3/certs';
    private const DEFAULT_ISSUER = 'https://accounts.google.com';

    private ?DiscoveryDocument $discoveryDocument = null;

    /**
     * @param string|list<string>|null $hostedDomain
     */
    public function __construct(
        private readonly OAuthConfiguration $configuration,
        private readonly string|array|null $hostedDomain = null,
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
            'scope' => implode(' ', $this->configuration->getScopes() ?: ['openid', 'email', 'profile']),
            'state' => $state,
            'nonce' => $nonce,
        ];

        if ($codeChallenge !== null) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = $codeChallengeMethod;
        }

        if ($this->hostedDomain !== null) {
            $params['hd'] = \is_array($this->hostedDomain) ? $this->hostedDomain[0] : $this->hostedDomain;
        }

        $authorizationEndpoint = $this->discoveryDocument?->getAuthorizationEndpoint()
            ?? $this->configuration->getAuthorizationEndpoint()
            ?? self::DEFAULT_AUTHORIZATION_ENDPOINT;

        return $authorizationEndpoint . '?' . http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }

    public function getTokenEndpoint(): string
    {
        return $this->discoveryDocument?->getTokenEndpoint()
            ?? $this->configuration->getTokenEndpoint()
            ?? self::DEFAULT_TOKEN_ENDPOINT;
    }

    public function getUserInfoEndpoint(): string
    {
        return $this->discoveryDocument?->getUserinfoEndpoint()
            ?? $this->configuration->getUserinfoEndpoint()
            ?? self::DEFAULT_USERINFO_ENDPOINT;
    }

    public function getJwksUri(): string
    {
        return $this->discoveryDocument?->getJwksUri()
            ?? $this->configuration->getJwksUri()
            ?? self::DEFAULT_JWKS_URI;
    }

    public function getIssuer(): string
    {
        return $this->discoveryDocument?->getIssuer()
            ?? $this->configuration->getIssuer()
            ?? self::DEFAULT_ISSUER;
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

    /**
     * Validate that the `hd` claim in the ID token matches the configured hosted domain(s).
     *
     * @param array<string, mixed> $claims
     * @throws AuthenticationException if the hosted domain does not match
     */
    public function validateHostedDomain(array $claims): void
    {
        if ($this->hostedDomain === null) {
            return;
        }

        $hd = $claims['hd'] ?? null;

        if ($hd === null) {
            throw new AuthenticationException('The ID token does not contain a "hd" claim.');
        }

        $allowedDomains = \is_array($this->hostedDomain) ? $this->hostedDomain : [$this->hostedDomain];

        if (!\in_array($hd, $allowedDomains, true)) {
            throw new AuthenticationException(\sprintf(
                'The hosted domain "%s" is not allowed.',
                $hd,
            ));
        }
    }

    public function validateClaims(array $claims): void
    {
        $this->validateHostedDomain($claims);
    }

    public function setDiscoveryDocument(DiscoveryDocument $discoveryDocument): void
    {
        $this->discoveryDocument = $discoveryDocument;
    }
}
