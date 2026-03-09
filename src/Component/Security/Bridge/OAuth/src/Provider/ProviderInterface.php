<?php

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
