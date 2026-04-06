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

use WpPack\Component\Database\DatabaseManager;

final class DatabaseCredentialRepository implements CredentialRepositoryInterface
{
    private const TABLE = 'wppack_passkey_credentials';

    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function findByUserId(int $userId): array
    {
        $table = $this->db->prefix() . self::TABLE;

        $rows = $this->db->fetchAllAssociative(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
            [$userId],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function findByCredentialId(string $credentialId): ?PasskeyCredential
    {
        $table = $this->db->prefix() . self::TABLE;

        $row = $this->db->fetchAssociative(
            "SELECT * FROM {$table} WHERE credential_id = %s LIMIT 1",
            [$credentialId],
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function save(PasskeyCredential $credential): void
    {
        $this->db->insert(self::TABLE, [
            'user_id' => $credential->userId,
            'credential_id' => $credential->credentialId,
            'public_key' => $credential->publicKey,
            'counter' => $credential->counter,
            'transports' => json_encode($credential->transports),
            'device_name' => $credential->deviceName,
            'aaguid' => $credential->aaguid,
            'backup_eligible' => $credential->backupEligible ? 1 : 0,
            'created_at' => $credential->createdAt->format('Y-m-d H:i:s'),
            'last_used_at' => null,
        ]);
    }

    public function updateCounter(int $id, int $newCounter): void
    {
        $this->db->update(self::TABLE, ['counter' => $newCounter], ['id' => $id]);
    }

    public function updateLastUsed(int $id): void
    {
        $this->db->update(
            self::TABLE,
            ['last_used_at' => current_time('mysql')],
            ['id' => $id],
        );
    }

    public function updateDeviceName(int $id, string $name): void
    {
        $this->db->update(self::TABLE, ['device_name' => $name], ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete(self::TABLE, ['id' => $id]);
    }

    public function findAll(): array
    {
        $table = $this->db->prefix() . self::TABLE;

        $rows = $this->db->fetchAllAssociative(
            "SELECT * FROM {$table} ORDER BY created_at DESC",
        );

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PasskeyCredential
    {
        return new PasskeyCredential(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            credentialId: $row['credential_id'],
            publicKey: $row['public_key'],
            counter: (int) $row['counter'],
            transports: json_decode($row['transports'] ?? '[]', true) ?: [],
            deviceName: $row['device_name'] ?? '',
            aaguid: $row['aaguid'] ?? '',
            backupEligible: (bool) ($row['backup_eligible'] ?? false),
            createdAt: new \DateTimeImmutable($row['created_at']),
            lastUsedAt: isset($row['last_used_at']) ? new \DateTimeImmutable($row['last_used_at']) : null,
        );
    }
}
