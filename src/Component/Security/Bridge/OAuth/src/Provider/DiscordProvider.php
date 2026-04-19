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

final class DiscordProvider implements ProviderInterface
{
    private const AUTHORIZATION_ENDPOINT = 'https://discord.com/api/oauth2/authorize';
    private const TOKEN_ENDPOINT = 'https://discord.com/api/oauth2/token';
    private const USERINFO_ENDPOINT = 'https://discord.com/api/users/@me';

    public function __construct(
        private readonly OAuthConfiguration $configuration,
    ) {}

    public static function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            type: 'discord',
            label: 'Discord',
            dropdownLabel: 'Discord',
            oidc: false,
            defaultScopes: ['identify', 'email'],
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
            'scope' => implode(' ', $this->configuration->getScopes() ?: ['identify', 'email']),
            'state' => $state,
        ];

        if ($codeChallenge !== null) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = $codeChallengeMethod;
        }

        $endpoint = $this->configuration->getAuthorizationEndpoint() ?? self::AUTHORIZATION_ENDPOINT;

        return $endpoint . '?' . http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }

    public function getTokenEndpoint(): string
    {
        return $this->configuration->getTokenEndpoint() ?? self::TOKEN_ENDPOINT;
    }

    public function getUserInfoEndpoint(): string
    {
        return $this->configuration->getUserinfoEndpoint() ?? self::USERINFO_ENDPOINT;
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

        if (isset($data['username'])) {
            $normalized['preferred_username'] = $data['username'];
        }

        if (isset($data['global_name'])) {
            $normalized['name'] = $data['global_name'];
        }

        if (isset($data['email'])) {
            $normalized['email'] = $data['email'];
        }

        if (isset($data['id'], $data['avatar'])) {
            $normalized['picture'] = \sprintf(
                'https://cdn.discordapp.com/avatars/%s/%s.png',
                $data['id'],
                $data['avatar'],
            );
        }

        return $normalized;
    }

    public function supportsOidc(): bool
    {
        return false;
    }

    public function validateClaims(array $claims): void {}
}
