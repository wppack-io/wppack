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

namespace WPPack\Plugin\PasskeyLoginPlugin\Tests\Migration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\DatabaseManager;
use WPPack\Plugin\PasskeyLoginPlugin\Migration\PasskeyCredentialTable;

#[CoversClass(PasskeyCredentialTable::class)]
final class PasskeyCredentialTableTest extends TestCase
{
    #[Test]
    public function schemaIncludesEveryRequiredColumn(): void
    {
        $db = new DatabaseManager();
        $prefix = $db->basePrefix();

        $sql = (new PasskeyCredentialTable())->schema($db);

        self::assertStringContainsString("CREATE TABLE {$prefix}wppack_passkey_credentials", $sql);
        self::assertStringContainsString('id bigint(20) NOT NULL AUTO_INCREMENT', $sql);
        self::assertStringContainsString('user_id bigint(20) NOT NULL', $sql);
        self::assertStringContainsString('credential_id varchar(1024) NOT NULL', $sql);
        self::assertStringContainsString('public_key text NOT NULL', $sql);
        self::assertStringContainsString('counter bigint(20) NOT NULL DEFAULT 0', $sql);
        self::assertStringContainsString('transports varchar(255)', $sql);
        self::assertStringContainsString('device_name varchar(255)', $sql);
        self::assertStringContainsString('aaguid char(36)', $sql);
        self::assertStringContainsString('backup_eligible tinyint(1)', $sql);
        self::assertStringContainsString('created_at datetime', $sql);
        self::assertStringContainsString('last_used_at datetime DEFAULT NULL', $sql);
    }

    #[Test]
    public function schemaEnforcesPrimaryKeyAndUniqueCredentialIndex(): void
    {
        $sql = (new PasskeyCredentialTable())->schema(new DatabaseManager());

        self::assertStringContainsString('PRIMARY KEY (id)', $sql);
        self::assertStringContainsString('UNIQUE KEY credential_id (credential_id(191))', $sql);
        self::assertStringContainsString('KEY user_id (user_id)', $sql);
    }

    #[Test]
    public function schemaUsesBasePrefixForNetworkWideSharing(): void
    {
        $db = new DatabaseManager();
        $sql = (new PasskeyCredentialTable())->schema($db);

        // Base prefix (not a per-site blog prefix) is used — verified by the
        // absence of any numeric site suffix between the prefix and the table.
        self::assertMatchesRegularExpression(
            '/CREATE TABLE ' . preg_quote($db->basePrefix(), '/') . 'wppack_passkey_credentials/',
            $sql,
        );
    }
}
