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

namespace WPPack\Component\Security\Bridge\Passkey\Tests\Ceremony;

use WPPack\Component\Security\Bridge\Passkey\Storage\CredentialRepositoryInterface;
use WPPack\Component\Security\Bridge\Passkey\Storage\PasskeyCredential;

/**
 * In-memory credential repository used by passkey tests across the
 * component and plugin layer. Needs to live in its own file so PHPUnit
 * autoloading resolves the class when PasskeyActivationControllerTest
 * (plugin layer) references it without loading CeremonyManagerTest.
 *
 * @internal
 */
class InMemoryCredentialRepository implements CredentialRepositoryInterface
{
    /** @var list<PasskeyCredential> */
    private array $credentials = [];

    public function findByUserId(int $userId): array
    {
        return array_values(array_filter(
            $this->credentials,
            static fn(PasskeyCredential $c): bool => $c->userId === $userId,
        ));
    }

    public function findByCredentialId(string $credentialId): ?PasskeyCredential
    {
        foreach ($this->credentials as $c) {
            if ($c->credentialId === $credentialId) {
                return $c;
            }
        }

        return null;
    }

    public function save(PasskeyCredential $credential): void
    {
        $this->credentials[] = $credential;
    }

    public function updateCounter(int $id, int $newCounter): void {}

    public function updateLastUsed(int $id): void {}

    public function updateDeviceName(int $id, string $name): void {}

    public function delete(int $id): void
    {
        $this->credentials = array_values(array_filter(
            $this->credentials,
            static fn(PasskeyCredential $c): bool => $c->id !== $id,
        ));
    }

    public function findAll(): array
    {
        return $this->credentials;
    }
}
