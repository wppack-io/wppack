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

/**
 * Login with Amazon OAuth 2.0 provider.
 *
 * @see https://developer.amazon.com/docs/login-with-amazon/web-docs.html
 */
final class AmazonProvider implements ProviderInterface
{
    private const AUTHORIZATION_ENDPOINT = 'https://www.amazon.com/ap/oa';
    private const TOKEN_ENDPOINT = 'https://api.amazon.com/auth/o2/token';
    private const USERINFO_ENDPOINT = 'https://api.amazon.com/user/profile';

    public function __construct(
        private readonly OAuthConfiguration $configuration,
    ) {}

    public static function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            type: 'amazon',
            label: 'Amazon',
            dropdownLabel: 'Amazon',
            oidc: false,
            defaultScopes: ['profile'],
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
            'scope' => implode(' ', $this->configuration->getScopes() ?: ['profile']),
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

        if (isset($data['user_id'])) {
            $normalized['sub'] = (string) $data['user_id'];
        }

        if (isset($data['name'])) {
            $normalized['name'] = $data['name'];
        }

        if (isset($data['email'])) {
            $normalized['email'] = $data['email'];
        }

        return $normalized;
    }

    public function supportsOidc(): bool
    {
        return false;
    }

    public function validateClaims(array $claims): void {}
}
