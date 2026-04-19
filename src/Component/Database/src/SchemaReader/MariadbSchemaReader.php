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

namespace WPPack\Component\Database\SchemaReader;

use WPPack\Component\Database\DatabaseManager;
use WPPack\Component\Database\Schema\ColumnSchema;
use WPPack\Component\Database\TypeMapper\MariadbTypeMapper;

/**
 * Schema reader for MariaDB databases.
 *
 * Extends MysqlSchemaReader with MariaDB detection and MariaDB-specific
 * column type classification (INET4, INET6, UUID, XMLTYPE, VECTOR).
 * DdlNormalizer handles the actual DDL type conversion.
 */
class MariadbSchemaReader extends MysqlSchemaReader
{
    private readonly MariadbTypeMapper $mariadbTypeMapper;

    public function __construct(MariadbTypeMapper $mariadbTypeMapper = new MariadbTypeMapper())
    {
        parent::__construct();
        $this->mariadbTypeMapper = $mariadbTypeMapper;
    }

    public function supports(DatabaseManager $db): bool
    {
        if ($db->engine !== 'mysql') {
            return false;
        }

        $version = $db->fetchOne('SELECT VERSION()');

        return \is_string($version) && stripos($version, 'MariaDB') !== false;
    }

    /**
     * @return list<ColumnSchema>
     */
    protected function getColumns(DatabaseManager $db, string $tableName): array
    {
        $rows = $db->fetchAllAssociative(
            \sprintf('SHOW COLUMNS FROM %s', $db->quoteIdentifier($tableName)),
        );

        $columns = [];

        foreach ($rows as $row) {
            $type = $row['Type'] ?? '';
            $columns[] = new ColumnSchema(
                name: $row['Field'],
                type: $type,
                nullable: ($row['Null'] ?? 'NO') === 'YES',
                default: $row['Default'] ?? null,
                extra: $row['Extra'] ?? '',
                isBinary: $this->mariadbTypeMapper->isBinary($type),
                isNumeric: $this->mariadbTypeMapper->isNumeric($type),
            );
        }

        return $columns;
    }
}
