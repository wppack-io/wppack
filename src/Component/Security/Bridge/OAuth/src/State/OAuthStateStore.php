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

use WpPack\Component\Transient\TransientManager;

final class OAuthStateStore
{
    private const TRANSIENT_PREFIX = '_wppack_oauth_state_';
    private const DEFAULT_TTL = 600;

    public function __construct(
        private readonly TransientManager $transientManager,
    ) {}

    public function store(string $state, StoredState $storedState): void
    {
        $this->transientManager->set(self::TRANSIENT_PREFIX . $state, [
            'nonce' => $storedState->getNonce(),
            'code_verifier' => $storedState->getCodeVerifier(),
            'return_to' => $storedState->getReturnTo(),
            'created_at' => $storedState->getCreatedAt(),
            'provider' => $storedState->getProviderName(),
        ], self::DEFAULT_TTL);
    }

    /**
     * Read the stored state without consuming it.
     *
     * Unlike retrieve(), peek() does not delete the transient. This is
     * useful for routing decisions (e.g., checking the provider name)
     * before the actual authentication flow consumes the state.
     */
    public function peek(string $state): ?StoredState
    {
        $data = $this->transientManager->get(self::TRANSIENT_PREFIX . $state);

        if (!\is_array($data)) {
            return null;
        }

        $storedState = new StoredState(
            nonce: (string) ($data['nonce'] ?? ''),
            codeVerifier: isset($data['code_verifier']) ? (string) $data['code_verifier'] : null,
            returnTo: isset($data['return_to']) ? (string) $data['return_to'] : null,
            createdAt: (int) ($data['created_at'] ?? 0),
            providerName: isset($data['provider']) ? (string) $data['provider'] : null,
        );

        if ($storedState->isExpired(self::DEFAULT_TTL)) {
            return null;
        }

        return $storedState;
    }

    public function retrieve(string $state): ?StoredState
    {
        $data = $this->transientManager->get(self::TRANSIENT_PREFIX . $state);

        // One-time use: delete immediately
        $this->transientManager->delete(self::TRANSIENT_PREFIX . $state);

        if (!\is_array($data)) {
            return null;
        }

        $storedState = new StoredState(
            nonce: (string) ($data['nonce'] ?? ''),
            codeVerifier: isset($data['code_verifier']) ? (string) $data['code_verifier'] : null,
            returnTo: isset($data['return_to']) ? (string) $data['return_to'] : null,
            createdAt: (int) ($data['created_at'] ?? 0),
            providerName: isset($data['provider']) ? (string) $data['provider'] : null,
        );

        if ($storedState->isExpired(self::DEFAULT_TTL)) {
            return null;
        }

        return $storedState;
    }
}
