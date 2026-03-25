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

namespace WpPack\Component\Security\Bridge\OAuth\Token;

final readonly class OAuthTokenSet
{
    public function __construct(
        private string $accessToken,
        private string $tokenType,
        private ?string $idToken = null,
        private ?string $refreshToken = null,
        private ?int $expiresIn = null,
        private ?string $scope = null,
        private ?int $issuedAt = null,
    ) {}

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getTokenType(): string
    {
        return $this->tokenType;
    }

    public function getIdToken(): ?string
    {
        return $this->idToken;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function getExpiresIn(): ?int
    {
        return $this->expiresIn;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function getIssuedAt(): ?int
    {
        return $this->issuedAt;
    }

    public function isExpired(): bool
    {
        if ($this->expiresIn === null || $this->issuedAt === null) {
            return false;
        }

        return time() >= ($this->issuedAt + $this->expiresIn);
    }

    public function hasRefreshToken(): bool
    {
        return $this->refreshToken !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            tokenType: $data['token_type'] ?? 'Bearer',
            idToken: $data['id_token'] ?? null,
            refreshToken: $data['refresh_token'] ?? null,
            expiresIn: isset($data['expires_in']) ? (int) $data['expires_in'] : null,
            scope: $data['scope'] ?? null,
            issuedAt: time(),
        );
    }
}
