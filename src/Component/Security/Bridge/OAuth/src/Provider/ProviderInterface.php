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

interface ProviderInterface
{
    public static function definition(): ProviderDefinition;

    public function getAuthorizationUrl(
        string $state,
        string $nonce,
        ?string $codeChallenge = null,
        string $codeChallengeMethod = 'S256',
    ): string;

    public function getTokenEndpoint(): string;

    public function getUserInfoEndpoint(): ?string;

    public function getJwksUri(): ?string;

    public function getIssuer(): ?string;

    public function getEndSessionEndpoint(): ?string;

    /**
     * Normalize provider-specific user info response to standard claims.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalizeUserInfo(array $data): array;

    public function supportsOidc(): bool;

    /**
     * Validate provider-specific claims after authentication.
     *
     * @param array<string, mixed> $claims
     *
     * @throws \WPPack\Component\Security\Exception\AuthenticationException
     */
    public function validateClaims(array $claims): void;
}
