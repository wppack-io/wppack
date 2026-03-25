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

interface ProviderInterface
{
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
}
