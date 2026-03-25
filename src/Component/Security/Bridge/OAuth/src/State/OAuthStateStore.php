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

final class OAuthStateStore
{
    private const TRANSIENT_PREFIX = '_wppack_oauth_state_';
    private const DEFAULT_TTL = 600;

    public function store(string $state, StoredState $storedState): void
    {
        set_transient(self::TRANSIENT_PREFIX . $state, [
            'nonce' => $storedState->getNonce(),
            'code_verifier' => $storedState->getCodeVerifier(),
            'return_to' => $storedState->getReturnTo(),
            'created_at' => $storedState->getCreatedAt(),
        ], self::DEFAULT_TTL);
    }

    public function retrieve(string $state): ?StoredState
    {
        $data = get_transient(self::TRANSIENT_PREFIX . $state);

        // One-time use: delete immediately
        delete_transient(self::TRANSIENT_PREFIX . $state);

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
