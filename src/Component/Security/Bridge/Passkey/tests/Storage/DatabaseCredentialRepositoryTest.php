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

namespace WPPack\Component\Security\Bridge\Passkey\Tests\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\DatabaseManager;
use WPPack\Component\Security\Bridge\Passkey\Storage\DatabaseCredentialRepository;
use WPPack\Component\Security\Bridge\Passkey\Storage\PasskeyCredential;

#[CoversClass(DatabaseCredentialRepository::class)]
final class DatabaseCredentialRepositoryTest extends TestCase
{
    private DatabaseManager $db;

    private string $tableName;

    protected function setUp(): void
    {
        $this->db = new DatabaseManager();
        $this->tableName = $this->db->basePrefix() . 'wppack_passkey_credentials';

        $this->db->wpdb()->query(
            "CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                credential_id varchar(1024) NOT NULL,
                public_key text NOT NULL,
                counter bigint(20) NOT NULL DEFAULT 0,
                transports varchar(255) DEFAULT '[]',
                device_name varchar(255) DEFAULT '',
                aaguid char(36) DEFAULT '',
                backup_eligible tinyint(1) DEFAULT 0,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_used_at datetime DEFAULT NULL,
                PRIMARY KEY (id)
            )",
        );
        $this->db->wpdb()->query("DELETE FROM {$this->tableName}");
    }

    protected function tearDown(): void
    {
        $this->db->wpdb()->query("DROP TABLE IF EXISTS {$this->tableName}");
    }

    private function credential(int $userId, string $credId, string $deviceName = 'YubiKey'): PasskeyCredential
    {
        return new PasskeyCredential(
            id: 0,
            userId: $userId,
            credentialId: $credId,
            publicKey: 'pk-' . $credId,
            counter: 0,
            transports: ['usb', 'nfc'],
            deviceName: $deviceName,
            aaguid: '00000000-0000-0000-0000-000000000000',
            backupEligible: false,
            createdAt: new \DateTimeImmutable('2024-06-01T12:00:00+00:00'),
            lastUsedAt: null,
        );
    }

    #[Test]
    public function findByCredentialIdReturnsNullWhenAbsent(): void
    {
        $repo = new DatabaseCredentialRepository($this->db);

        self::assertNull($repo->findByCredentialId('no-such-credential'));
    }

    #[Test]
    public function saveAndFindByCredentialIdRoundTrip(): void
    {
        $repo = new DatabaseCredentialRepository($this->db);

        $repo->save($this->credential(42, 'cred-alice'));

        $found = $repo->findByCredentialId('cred-alice');

        self::assertInstanceOf(PasskeyCredential::class, $found);
        self::assertGreaterThan(0, $found->id);
        self::assertSame(42, $found->userId);
        self::assertSame('cred-alice', $found->credentialId);
        self::assertSame('pk-cred-alice', $found->publicKey);
        self::assertSame(['usb', 'nfc'], $found->transports);
        self::assertSame('YubiKey', $found->deviceName);
        self::assertFalse($found->backupEligible);
    }

    #[Test]
    public function findByUserIdReturnsAllCredentialsForUser(): void
    {
        $repo = new DatabaseCredentialRepository($this->db);

        $repo->save($this->credential(42, 'cred-a', 'YubiKey'));
        $repo->save($this->credential(42, 'cred-b', 'Passkey'));
        $repo->save($this->credential(99, 'cred-other'));

        $credentials = $repo->findByUserId(42);

        self::assertCount(2, $credentials);

        $ids = array_map(static fn(PasskeyCredential $c): string => $c->credentialId, $credentials);
        self::assertContains('cred-a', $ids);
        self::assertContains('cred-b', $ids);
    }

    #[Test]
    public function findByUserIdReturnsEmptyListForUnknownUser(): void
    {
        self::assertSame([], (new DatabaseCredentialRepository($this->db))->findByUserId(9999));
    }

    #[Test]
    public function updateCounterPersistsNewValue(): void
    {
        $repo = new DatabaseCredentialRepository($this->db);
        $repo->save($this->credential(1, 'x'));

        $stored = $repo->findByCredentialId('x');
        self::assertNotNull($stored);

        $repo->updateCounter($stored->id, 42);

        $fresh = $repo->findByCredentialId('x');
        self::assertNotNull($fresh);
        self::assertSame(42, $fresh->counter);
    }

    #[Test]
    public function updateLastUsedStampsCurrentTime(): void
    {
        $repo = new DatabaseCredentialRepository($this->db);
        $repo->save($this->credential(1, 'x'));

        $stored = $repo->findByCredentialId('x');
        self::assertNotNull($stored);
        self::assertNull($stored->lastUsedAt);

        $repo->updateLastUsed($stored->id);

        $fresh = $repo->findByCredentialId('x');
        self::assertNotNull($fresh);
        self::assertInstanceOf(\DateTimeImmutable::class, $fresh->lastUsedAt);
    }

    #[Test]
    public function updateDeviceNameChangesLabel(): void
    {
        $repo = new DatabaseCredentialRepository($this->db);
        $repo->save($this->credential(1, 'x', 'Old'));

        $stored = $repo->findByCredentialId('x');
        self::assertNotNull($stored);

        $repo->updateDeviceName($stored->id, 'New Label');

        $fresh = $repo->findByCredentialId('x');
        self::assertNotNull($fresh);
        self::assertSame('New Label', $fresh->deviceName);
    }

    #[Test]
    public function deleteRemovesCredentialById(): void
    {
        $repo = new DatabaseCredentialRepository($this->db);
        $repo->save($this->credential(1, 'delete-me'));

        $stored = $repo->findByCredentialId('delete-me');
        self::assertNotNull($stored);

        $repo->delete($stored->id);

        self::assertNull($repo->findByCredentialId('delete-me'));
    }

    #[Test]
    public function findAllReturnsEveryCredentialOrderedByCreatedAtDesc(): void
    {
        $repo = new DatabaseCredentialRepository($this->db);
        $repo->save($this->credential(1, 'a'));
        $repo->save($this->credential(2, 'b'));

        $all = $repo->findAll();

        self::assertCount(2, $all);
        self::assertContainsOnlyInstancesOf(PasskeyCredential::class, $all);
    }
}
