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

namespace WpPack\Component\Security\Bridge\Passkey\Storage;

interface CredentialRepositoryInterface
{
    /**
     * @return list<PasskeyCredential>
     */
    public function findByUserId(int $userId): array;

    public function findByCredentialId(string $credentialId): ?PasskeyCredential;

    public function save(PasskeyCredential $credential): void;

    public function updateCounter(int $id, int $newCounter): void;

    public function updateLastUsed(int $id): void;

    public function updateDeviceName(int $id, string $name): void;

    public function delete(int $id): void;

    /**
     * @return list<PasskeyCredential>
     */
    public function findAll(): array;
}
