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

namespace WpPack\Component\Database\Bridge\Pgsql\Translator;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\SetStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Translates MySQL SQL to PostgreSQL SQL using AST-based parsing.
 *
 * Used for PostgreSQL and Aurora DSQL targets.
 * Handles identifiers, data types, functions, INSERT syntax, and SHOW statements.
 */
final class PostgresqlQueryTranslator implements QueryTranslatorInterface
{
    private const IGNORED_PATTERNS = [
        '/^\s*SET\s+NAMES\s+/i',
        '/^\s*LOCK\s+TABLES?\s+/i',
        '/^\s*UNLOCK\s+TABLES?\s*/i',
        '/^\s*OPTIMIZE\s+TABLE\s+/i',
        '/^\s*CHECK\s+TABLE\s+/i',
        '/^\s*REPAIR\s+TABLE\s+/i',
    ];

    /** @var array<string, string> */
    private const SHOW_TRANSLATIONS = [
        '/^\s*SHOW\s+TABLES\s*/i' => "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'",
        '/^\s*SHOW\s+FULL\s+TABLES\s*/i' => "SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = 'public'",
    ];

    /** @var array<string, string> */
    private const FUNCTION_MAP = [
        'RAND' => 'random',
        'IFNULL' => 'COALESCE',
        'SUBSTRING' => 'SUBSTRING',
        'SUBSTR' => 'SUBSTRING',
        'LENGTH' => 'LENGTH',
        'CHAR_LENGTH' => 'CHAR_LENGTH',
    ];

    public function translate(string $sql): array
    {
        $trimmed = trim($sql);

        foreach (self::IGNORED_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return [];
            }
        }

        foreach (self::SHOW_TRANSLATIONS as $pattern => $replacement) {
            if (preg_match($pattern, $trimmed)) {
                return [$replacement];
            }
        }

        if ($translated = $this->translateShowCommand($trimmed)) {
            return $translated;
        }

        // Strip FOR UPDATE (PostgreSQL supports it, but strip for simplicity)
        // Actually PostgreSQL supports FOR UPDATE natively — keep it

        // AST parse for statement-specific handling
        $parser = new Parser($sql);
        $stmt = $parser->statements[0] ?? null;

        if ($stmt !== null) {
            if ($stmt instanceof SetStatement) {
                // SET SESSION — some are valid in PostgreSQL, ignore MySQL-specific ones
                return [];
            }

            if ($stmt instanceof TruncateStatement) {
                // PostgreSQL supports TRUNCATE natively
                $table = '"' . str_replace('"', '""', $stmt->table->table) . '"';

                return ["TRUNCATE TABLE {$table}"];
            }
        }

        return [$this->convertSyntax($sql)];
    }

    private function convertSyntax(string $sql): string
    {
        $sql = $this->convertIdentifiers($sql);
        $sql = $this->convertDataTypes($sql);
        $sql = $this->convertFunctions($sql);
        $sql = $this->convertInsertSyntax($sql);
        $sql = $this->convertLimit($sql);
        $sql = $this->stripMysqlClauses($sql);

        return $sql;
    }

    /**
     * Backtick-quoted identifiers → double-quoted.
     */
    private function convertIdentifiers(string $sql): string
    {
        return (string) preg_replace_callback('/`([^`]*(?:``[^`]*)*)`/', static function (array $m): string {
            $inner = str_replace('``', '`', $m[1]);
            $inner = str_replace('"', '""', $inner);

            return '"' . $inner . '"';
        }, $sql);
    }

    /**
     * MySQL data types → PostgreSQL types in DDL.
     */
    private function convertDataTypes(string $sql): string
    {
        if (!preg_match('/^\s*(CREATE|ALTER)\s/i', $sql)) {
            return $sql;
        }

        $typeMap = [
            '/\bTINYINT\s*\(\s*1\s*\)/i' => 'BOOLEAN',
            '/\b(?:TINY|SMALL|MEDIUM)?INT\s*\(\s*\d+\s*\)\s*(?:UNSIGNED\s*)?/i' => 'INTEGER',
            '/\bBIGINT\s*\(\s*\d+\s*\)\s*(?:UNSIGNED\s*)?/i' => 'BIGINT',
            '/\bINT\b\s*(?:UNSIGNED\s*)?/i' => 'INTEGER',
            '/\bBIGINT\b\s*(?:UNSIGNED\s*)?/i' => 'BIGINT',
            '/\bTINYINT\b\s*(?:UNSIGNED\s*)?/i' => 'SMALLINT',
            '/\bMEDIUMINT\b\s*(?:UNSIGNED\s*)?/i' => 'INTEGER',
            '/\bDOUBLE\b(?:\s*\([^)]+\))?/i' => 'DOUBLE PRECISION',
            '/\bFLOAT\b(?:\s*\([^)]+\))?/i' => 'REAL',
            '/\bDATETIME\b/i' => 'TIMESTAMP',
            '/\b(?:TINY|MEDIUM|LONG)?TEXT\b/i' => 'TEXT',
            '/\b(?:TINY|MEDIUM|LONG)?BLOB\b/i' => 'BYTEA',
            '/\bVARBINARY\s*\(\s*\d+\s*\)/i' => 'BYTEA',
            '/\bBINARY\s*\(\s*\d+\s*\)/i' => 'BYTEA',
            '/\bENUM\s*\([^)]+\)/i' => 'TEXT',
            '/\bJSON\b/i' => 'JSONB',
        ];

        foreach ($typeMap as $pattern => $replacement) {
            $sql = (string) preg_replace($pattern, $replacement, $sql);
        }

        $sql = (string) preg_replace('/\bUNSIGNED\b/i', '', $sql);

        return $sql;
    }

    /**
     * MySQL functions → PostgreSQL equivalents.
     */
    private function convertFunctions(string $sql): string
    {
        foreach (self::FUNCTION_MAP as $mysql => $pgsql) {
            $sql = (string) preg_replace('/\b' . $mysql . '\s*\(/i', $pgsql . '(', $sql);
        }

        // NOW() — compatible in PostgreSQL
        // CURDATE() → CURRENT_DATE
        $sql = (string) preg_replace('/\bCURDATE\s*\(\s*\)/i', 'CURRENT_DATE', $sql);
        // CURTIME() → CURRENT_TIME
        $sql = (string) preg_replace('/\bCURTIME\s*\(\s*\)/i', 'CURRENT_TIME', $sql);
        // UNIX_TIMESTAMP() → EXTRACT(EPOCH FROM NOW())
        $sql = (string) preg_replace('/\bUNIX_TIMESTAMP\s*\(\s*\)/i', 'EXTRACT(EPOCH FROM NOW())::INTEGER', $sql);
        // FROM_UNIXTIME(t) → TO_TIMESTAMP(t)
        $sql = (string) preg_replace('/\bFROM_UNIXTIME\s*\(\s*([^)]+)\s*\)/i', 'TO_TIMESTAMP($1)', $sql);
        // LAST_INSERT_ID() → lastval()
        $sql = (string) preg_replace('/\bLAST_INSERT_ID\s*\(\s*\)/i', 'lastval()', $sql);
        // VERSION() — compatible in PostgreSQL
        // DATABASE() → CURRENT_DATABASE()
        $sql = (string) preg_replace('/\bDATABASE\s*\(\s*\)/i', 'CURRENT_DATABASE()', $sql);
        // FOUND_ROWS() — not directly emulatable
        $sql = (string) preg_replace('/\bFOUND_ROWS\s*\(\s*\)/i', '-1', $sql);

        // LEFT(s, n) → SUBSTRING(s FROM 1 FOR n)
        $sql = (string) preg_replace('/\bLEFT\s*\(\s*([^,]+),\s*([^)]+)\)/i', 'SUBSTRING($1 FROM 1 FOR $2)', $sql);

        // CAST(x AS SIGNED) → CAST(x AS INTEGER)
        $sql = (string) preg_replace('/\bCAST\s*\(\s*(.+?)\s+AS\s+SIGNED\s*\)/i', 'CAST($1 AS INTEGER)', $sql);

        // DATE_ADD(d, INTERVAL n unit) → d + INTERVAL 'n unit'
        $sql = (string) preg_replace_callback(
            '/\bDATE_ADD\s*\(\s*(.+?)\s*,\s*INTERVAL\s+(\d+)\s+(\w+)\s*\)/i',
            static fn(array $m) => \sprintf('%s + INTERVAL \'%s %s\'', $m[1], $m[2], strtolower($m[3])),
            $sql,
        );

        // DATE_SUB(d, INTERVAL n unit) → d - INTERVAL 'n unit'
        $sql = (string) preg_replace_callback(
            '/\bDATE_SUB\s*\(\s*(.+?)\s*,\s*INTERVAL\s+(\d+)\s+(\w+)\s*\)/i',
            static fn(array $m) => \sprintf('%s - INTERVAL \'%s %s\'', $m[1], $m[2], strtolower($m[3])),
            $sql,
        );

        // DATE_FORMAT(d, f) → TO_CHAR(d, converted_f)
        $sql = (string) preg_replace_callback(
            '/\bDATE_FORMAT\s*\(\s*(.+?)\s*,\s*[\'"](.+?)[\'"]\s*\)/i',
            static function (array $m): string {
                $format = str_replace(
                    ['%Y', '%m', '%d', '%H', '%i', '%s'],
                    ['YYYY', 'MM', 'DD', 'HH24', 'MI', 'SS'],
                    $m[2],
                );

                return \sprintf("TO_CHAR(%s, '%s')", $m[1], $format);
            },
            $sql,
        );

        // IF(cond, t, f) → CASE WHEN cond THEN t ELSE f END
        $sql = (string) preg_replace(
            '/\bIF\s*\(\s*([^,]+?)\s*,\s*([^,]+?)\s*,\s*([^)]+?)\s*\)/i',
            'CASE WHEN $1 THEN $2 ELSE $3 END',
            $sql,
        );

        // REGEXP → ~*
        $sql = (string) preg_replace('/\s+REGEXP\s+/i', ' ~* ', $sql);

        return $sql;
    }

    /**
     * MySQL INSERT syntax → PostgreSQL.
     */
    private function convertInsertSyntax(string $sql): string
    {
        // INSERT IGNORE INTO → INSERT INTO ... ON CONFLICT DO NOTHING
        if (preg_match('/\bINSERT\s+IGNORE\s+INTO\b/i', $sql)) {
            $sql = (string) preg_replace('/\bINSERT\s+IGNORE\s+INTO\b/i', 'INSERT INTO', $sql);
            $sql = rtrim($sql, " \t\n\r;") . ' ON CONFLICT DO NOTHING';
        }

        // REPLACE INTO → not directly supported, use UPSERT pattern
        // Leave as-is for now (would need table schema knowledge)

        // ON DUPLICATE KEY UPDATE → ON CONFLICT DO UPDATE SET
        if (preg_match('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\s+(.+)$/is', $sql, $m)) {
            $updateClause = $m[1];
            $updateClause = (string) preg_replace('/\bVALUES\s*\(\s*([^)]+)\s*\)/i', 'excluded.$1', $updateClause);
            $sql = (string) preg_replace('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\s+.+$/is', 'ON CONFLICT DO UPDATE SET ' . $updateClause, $sql);
        }

        return $sql;
    }

    /**
     * LIMIT offset, count → LIMIT count OFFSET offset
     */
    private function convertLimit(string $sql): string
    {
        return (string) preg_replace('/\bLIMIT\s+(\d+)\s*,\s*(\d+)/i', 'LIMIT $2 OFFSET $1', $sql);
    }

    /**
     * Strip MySQL-specific clauses.
     */
    private function stripMysqlClauses(string $sql): string
    {
        $sql = (string) preg_replace('/\s*ENGINE\s*=\s*\w+/i', '', $sql);
        $sql = (string) preg_replace('/\s*DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
        $sql = (string) preg_replace('/\s*COLLATE\s*=\s*\w+/i', '', $sql);
        $sql = (string) preg_replace('/\s*CHARACTER\s+SET\s+\w+/i', '', $sql);
        $sql = (string) preg_replace('/\bAUTO_INCREMENT\b/i', 'SERIAL', $sql);
        $sql = (string) preg_replace('/\s*SERIAL\s*=\s*\d+/i', '', $sql);

        return $sql;
    }

    /**
     * @return list<string>|null
     */
    private function translateShowCommand(string $sql): ?array
    {
        if (preg_match('/^\s*SHOW\s+(?:FULL\s+)?COLUMNS\s+FROM\s+[`"]?(\w+)[`"]?\s*/i', $sql, $m)) {
            return [\sprintf(
                "SELECT column_name AS \"Field\", data_type AS \"Type\", is_nullable AS \"Null\", column_default AS \"Default\" "
                . "FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '%s' ORDER BY ordinal_position",
                $m[1],
            )];
        }

        if (preg_match('/^\s*SHOW\s+(?:GLOBAL\s+|SESSION\s+)?VARIABLES/i', $sql)) {
            return ["SELECT name AS Variable_name, setting AS Value FROM pg_settings LIMIT 0"];
        }

        if (preg_match('/^\s*SHOW\s+COLLATION/i', $sql)) {
            return ["SELECT collname AS \"Collation\" FROM pg_collation LIMIT 0"];
        }

        if (preg_match('/^\s*SHOW\s+DATABASES/i', $sql)) {
            return ['SELECT datname AS "Database" FROM pg_database WHERE datistemplate = false'];
        }

        if (preg_match('/^\s*SHOW\s+TABLE\s+STATUS/i', $sql)) {
            return ["SELECT table_name AS \"Name\" FROM information_schema.tables WHERE table_schema = 'public'"];
        }

        return null;
    }
}
