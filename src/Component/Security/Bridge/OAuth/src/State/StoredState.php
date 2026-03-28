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

namespace WpPack\Component\Security\Bridge\OAuth\State;

final readonly class StoredState
{
    public function __construct(
        private string $nonce,
        private ?string $codeVerifier = null,
        private ?string $returnTo = null,
        private int $createdAt = 0,
        private ?string $providerName = null,
    ) {}

    public function getNonce(): string
    {
        return $this->nonce;
    }

    public function getCodeVerifier(): ?string
    {
        return $this->codeVerifier;
    }

    public function getReturnTo(): ?string
    {
        return $this->returnTo;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function isExpired(int $ttl = 600): bool
    {
        return time() >= ($this->createdAt + $ttl);
    }

    public static function create(
        string $nonce,
        ?string $codeVerifier = null,
        ?string $returnTo = null,
        ?string $providerName = null,
    ): self {
        return new self(
            nonce: $nonce,
            codeVerifier: $codeVerifier,
            returnTo: $returnTo,
            createdAt: time(),
            providerName: $providerName,
        );
    }
}
