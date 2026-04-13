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

namespace WpPack\Component\Database\SchemaReader;

use WpPack\Component\Database\DatabaseEngine;
use WpPack\Component\Database\DatabaseManager;

/**
 * Schema reader for MariaDB databases.
 *
 * Extends MysqlSchemaReader with MariaDB detection via VERSION() query.
 * DdlNormalizer handles MariaDB-specific type conversions in CREATE TABLE DDL.
 */
class MariadbSchemaReader extends MysqlSchemaReader
{
    public function supports(DatabaseManager $db): bool
    {
        if ($db->engine !== DatabaseEngine::MySQL) {
            return false;
        }

        $version = $db->fetchOne('SELECT VERSION()');

        return \is_string($version) && stripos($version, 'MariaDB') !== false;
    }
}
