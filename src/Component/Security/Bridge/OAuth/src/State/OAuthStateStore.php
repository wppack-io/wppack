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

namespace WPPack\Component\Security\Bridge\OAuth\State;

use WPPack\Component\Transient\TransientManager;

final class OAuthStateStore
{
    private const TRANSIENT_PREFIX = '_wppack_oauth_state_';
    private const DEFAULT_TTL = 600;

    public function __construct(
        private readonly TransientManager $transientManager,
    ) {}

    public function store(string $state, StoredState $storedState): void
    {
        $this->transientManager->set(self::TRANSIENT_PREFIX . hash('sha256', $state), [
            'nonce' => $storedState->getNonce(),
            'code_verifier' => $storedState->getCodeVerifier(),
            'return_to' => $storedState->getReturnTo(),
            'created_at' => $storedState->getCreatedAt(),
        ], self::DEFAULT_TTL);
    }

    public function retrieve(string $state): ?StoredState
    {
        $hashedKey = self::TRANSIENT_PREFIX . hash('sha256', $state);

        $data = $this->transientManager->get($hashedKey);

        // One-time use: delete immediately
        $this->transientManager->delete($hashedKey);

        if (!\is_array($data)) {
            return null;
        }

        $storedState = new StoredState(
            nonce: (string) ($data['nonce'] ?? ''),
            codeVerifier: isset($data['code_verifier']) ? (string) $data['code_verifier'] : null,
            returnTo: isset($data['return_to']) ? (string) $data['return_to'] : null,
            createdAt: (int) ($data['created_at'] ?? 0),
        );

        if ($storedState->isExpired(self::DEFAULT_TTL)) {
            return null;
        }

        return $storedState;
    }
}
