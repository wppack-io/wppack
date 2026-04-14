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

namespace WpPack\Component\Database\Bridge\AuroraDsql\Translator;

use WpPack\Component\Database\Bridge\Pgsql\Translator\PostgresqlQueryTranslator;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Query translator for Aurora DSQL.
 *
 * Extends PostgreSQL translation with DSQL-specific limitations:
 * - TRUNCATE TABLE is not supported → converted to DELETE FROM
 * - Sequences/SERIAL limitations
 *
 * @see https://docs.aws.amazon.com/aurora-dsql/latest/userguide/working-with-postgresql-compatibility-unsupported-features.html
 */
final class AuroraDsqlQueryTranslator implements QueryTranslatorInterface
{
    private readonly PostgresqlQueryTranslator $pgsql;

    public function __construct()
    {
        $this->pgsql = new PostgresqlQueryTranslator();
    }

    public function translate(string $sql): array
    {
        // TRUNCATE → DELETE FROM (DSQL does not support TRUNCATE)
        if (preg_match('/^\s*TRUNCATE\s+(?:TABLE\s+)?/i', trim($sql))) {
            $tableName = preg_replace('/^\s*TRUNCATE\s+(?:TABLE\s+)?[`"]?(\w+)[`"]?\s*;?\s*$/i', '$1', trim($sql));

            return ['DELETE FROM "' . str_replace('"', '""', $tableName) . '"'];
        }

        return $this->pgsql->translate($sql);
    }
}
