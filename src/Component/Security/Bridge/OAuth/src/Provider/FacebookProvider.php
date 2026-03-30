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

final class FacebookProvider implements ProviderInterface
{
    private const AUTHORIZATION_ENDPOINT = 'https://www.facebook.com/v21.0/dialog/oauth';
    private const TOKEN_ENDPOINT = 'https://graph.facebook.com/v21.0/oauth/access_token';
    private const USERINFO_ENDPOINT = 'https://graph.facebook.com/v21.0/me?fields=id,name,email,picture.type(large)';

    public function __construct(
        private readonly OAuthConfiguration $configuration,
    ) {}

    public static function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            type: 'facebook',
            label: 'Facebook',
            dropdownLabel: 'Facebook',
            oidc: false,
            defaultScopes: ['email', 'public_profile'],
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
            'scope' => implode(',', $this->configuration->getScopes() ?: ['email', 'public_profile']),
            'state' => $state,
        ];

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

        if (isset($data['name'])) {
            $normalized['name'] = $data['name'];
        }

        if (isset($data['email'])) {
            $normalized['email'] = $data['email'];
        }

        if (isset($data['picture']['data']['url'])) {
            $normalized['picture'] = $data['picture']['data']['url'];
        }

        return $normalized;
    }

    public function supportsOidc(): bool
    {
        return false;
    }

    public function validateClaims(array $claims): void {}
}
