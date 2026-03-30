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

final class GitHubProvider implements ProviderInterface
{
    private const DEFAULT_AUTHORIZATION_ENDPOINT = 'https://github.com/login/oauth/authorize';
    private const DEFAULT_TOKEN_ENDPOINT = 'https://github.com/login/oauth/access_token';
    private const DEFAULT_USERINFO_ENDPOINT = 'https://api.github.com/user';

    public function __construct(
        private readonly OAuthConfiguration $configuration,
    ) {}

    public static function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            type: 'github',
            label: 'GitHub',
            dropdownLabel: 'GitHub',
            oidc: false,
            defaultScopes: ['user:email'],
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
            'scope' => implode(' ', $this->configuration->getScopes() ?: ['read:user', 'user:email']),
            'state' => $state,
        ];

        $authorizationEndpoint = $this->configuration->getAuthorizationEndpoint()
            ?? self::DEFAULT_AUTHORIZATION_ENDPOINT;

        return $authorizationEndpoint . '?' . http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }

    public function getTokenEndpoint(): string
    {
        return $this->configuration->getTokenEndpoint()
            ?? self::DEFAULT_TOKEN_ENDPOINT;
    }

    public function getUserInfoEndpoint(): string
    {
        return $this->configuration->getUserinfoEndpoint()
            ?? self::DEFAULT_USERINFO_ENDPOINT;
    }

    public function getJwksUri(): ?string
    {
        return null;
    }

    public function getIssuer(): ?string
    {
        return null;
    }

    public function getEndSessionEndpoint(): ?string
    {
        return null;
    }

    public function normalizeUserInfo(array $data): array
    {
        $normalized = [];

        if (isset($data['id'])) {
            $normalized['sub'] = (string) $data['id'];
        }

        if (isset($data['login'])) {
            $normalized['preferred_username'] = $data['login'];
        }

        if (isset($data['name'])) {
            $normalized['name'] = $data['name'];
        }

        if (isset($data['email'])) {
            $normalized['email'] = $data['email'];
        }

        if (isset($data['avatar_url'])) {
            $normalized['picture'] = $data['avatar_url'];
        }

        return $normalized;
    }

    public function supportsOidc(): bool
    {
        return false;
    }

    public function validateClaims(array $claims): void {}
}
