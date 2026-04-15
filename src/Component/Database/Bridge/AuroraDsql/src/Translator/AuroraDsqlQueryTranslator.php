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
        // TRUNCATE → DELETE FROM + sequence reset (DSQL does not support TRUNCATE)
        // MySQL TRUNCATE resets AUTO_INCREMENT. We replicate this by resetting
        // the SERIAL sequence after DELETE FROM.
        if (preg_match('/^\s*TRUNCATE\s+(?:TABLE\s+)?[`"]?(\w+)[`"]?\s*;?\s*$/i', trim($sql), $m)) {
            $table = str_replace('"', '""', $m[1]);
            $quotedTable = '"' . $table . '"';

            return [
                'DELETE FROM ' . $quotedTable,
                // Reset SERIAL sequences to 1 (MySQL TRUNCATE resets AUTO_INCREMENT)
                \sprintf(
                    "SELECT setval(pg_get_serial_sequence('%s', column_name), 1, false) "
                    . "FROM information_schema.columns "
                    . "WHERE table_schema = 'public' AND table_name = '%s' "
                    . "AND column_default LIKE 'nextval%%'",
                    str_replace("'", "''", $table),
                    str_replace("'", "''", $table),
                ),
            ];
        }

        return $this->pgsql->translate($sql);
    }
}
