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

namespace WpPack\Plugin\PasskeyLoginPlugin\Migration;

use WpPack\Component\Database\Attribute\Table;
use WpPack\Component\Database\DatabaseManager;
use WpPack\Component\Database\TableInterface;

#[Table('wppack_passkey_credentials')]
final class PasskeyCredentialTable implements TableInterface
{
    public function schema(DatabaseManager $db): string
    {
        $tableName = $db->prefix() . 'wppack_passkey_credentials';
        $charsetCollate = $db->charsetCollate();

        return "CREATE TABLE {$tableName} (
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
            PRIMARY KEY (id),
            UNIQUE KEY credential_id (credential_id(191)),
            KEY user_id (user_id)
        ) {$charsetCollate};";
    }
}
