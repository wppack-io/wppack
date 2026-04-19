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

final class EntraIdProvider implements ProviderInterface
{
    private const BASE_URL = 'https://login.microsoftonline.com';

    private ?DiscoveryDocument $discoveryDocument = null;

    public function __construct(
        private readonly OAuthConfiguration $configuration,
        private readonly string $tenantId,
        ?DiscoveryDocument $discoveryDocument = null,
    ) {
        if (!preg_match('/^[a-f0-9\-]{36}$|^(common|organizations|consumers)$/i', $this->tenantId)) {
            throw new \InvalidArgumentException('Invalid Azure tenant ID format.');
        }

        $this->discoveryDocument = $discoveryDocument;
    }

    public static function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            type: 'entra-id',
            label: 'Entra ID',
            dropdownLabel: 'Microsoft Entra ID',
            oidc: true,
            requiredFields: ['tenant_id'],
        );
    }

    public function getDiscoveryUrl(): string
    {
        return \sprintf('%s/%s/v2.0/.well-known/openid-configuration', self::BASE_URL, $this->tenantId);
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
            'prompt' => 'select_account',
        ];

        if ($codeChallenge !== null) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = $codeChallengeMethod;
        }

        $authorizationEndpoint = $this->discoveryDocument?->getAuthorizationEndpoint()
            ?? $this->configuration->getAuthorizationEndpoint()
            ?? \sprintf('%s/%s/oauth2/v2.0/authorize', self::BASE_URL, $this->tenantId);

        return $authorizationEndpoint . '?' . http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }

    public function getTokenEndpoint(): string
    {
        return $this->discoveryDocument?->getTokenEndpoint()
            ?? $this->configuration->getTokenEndpoint()
            ?? \sprintf('%s/%s/oauth2/v2.0/token', self::BASE_URL, $this->tenantId);
    }

    public function getUserInfoEndpoint(): ?string
    {
        return $this->discoveryDocument?->getUserinfoEndpoint()
            ?? $this->configuration->getUserinfoEndpoint();
    }

    public function getJwksUri(): string
    {
        return $this->discoveryDocument?->getJwksUri()
            ?? $this->configuration->getJwksUri()
            ?? \sprintf('%s/%s/discovery/v2.0/keys', self::BASE_URL, $this->tenantId);
    }

    public function getIssuer(): string
    {
        return $this->discoveryDocument?->getIssuer()
            ?? $this->configuration->getIssuer()
            ?? \sprintf('%s/%s/v2.0', self::BASE_URL, $this->tenantId);
    }

    public function getEndSessionEndpoint(): string
    {
        return $this->discoveryDocument?->getEndSessionEndpoint()
            ?? $this->configuration->getEndSessionEndpoint()
            ?? \sprintf('%s/%s/oauth2/v2.0/logout', self::BASE_URL, $this->tenantId);
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
