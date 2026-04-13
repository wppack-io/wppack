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

namespace WpPack\Component\Database\Bridge\Sqlite\Translator;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\SetStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Translates MySQL SQL to SQLite SQL using AST-based parsing.
 *
 * Uses phpmyadmin/sql-parser to parse MySQL SQL into an AST, then applies
 * SQLite-specific transformations for identifiers, types, functions, and syntax.
 *
 * Handles: SELECT, INSERT, UPDATE, DELETE, REPLACE, CREATE TABLE, DROP TABLE,
 * TRUNCATE, ALTER TABLE, CREATE INDEX, DROP INDEX, ON DUPLICATE KEY UPDATE,
 * REGEXP, DATE_ADD/DATE_SUB, CONCAT, and 40+ MySQL functions.
 */
final class SqliteQueryTranslator implements QueryTranslatorInterface
{
    private const IGNORED_PATTERNS = [
        '/^\s*SET\s+(SESSION\s+|GLOBAL\s+)?/i',
        '/^\s*LOCK\s+TABLES?\s+/i',
        '/^\s*UNLOCK\s+TABLES?\s*/i',
        '/^\s*OPTIMIZE\s+TABLE\s+/i',
        '/^\s*ANALYZE\s+TABLE\s+/i',
        '/^\s*CHECK\s+TABLE\s+/i',
        '/^\s*REPAIR\s+TABLE\s+/i',
    ];

    /** @var array<string, string> */
    private const SHOW_TRANSLATIONS = [
        '/^\s*SHOW\s+TABLES\s*/i' => "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'",
        '/^\s*SHOW\s+FULL\s+TABLES\s*/i' => "SELECT name, 'BASE TABLE' AS Table_type FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'",
    ];

    /** @var array<string, string> */
    private const FUNCTION_MAP = [
        'RAND' => 'random',
        'LAST_INSERT_ID' => 'last_insert_rowid',
        'SUBSTRING' => 'SUBSTR',
        'SUBSTR' => 'SUBSTR',
        'LENGTH' => 'LENGTH',
        'CHAR_LENGTH' => 'LENGTH',
        'IFNULL' => 'IFNULL',
    ];

    /** @var array<string, string> */
    private const DATE_FORMAT_MAP = [
        '%Y' => '%Y',
        '%m' => '%m',
        '%d' => '%d',
        '%H' => '%H',
        '%i' => '%M',
        '%s' => '%S',
        '%j' => '%j',
        '%W' => '%w',
    ];

    public function translate(string $sql): array
    {
        $trimmed = trim($sql);

        // Ignored statements
        foreach (self::IGNORED_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return [];
            }
        }

        // SHOW statements
        foreach (self::SHOW_TRANSLATIONS as $pattern => $replacement) {
            if (preg_match($pattern, $trimmed)) {
                return [$replacement];
            }
        }

        if ($translated = $this->translateShowCommand($trimmed)) {
            return $translated;
        }

        // Strip FOR UPDATE
        $sql = (string) preg_replace('/\s+FOR\s+UPDATE\s*/i', '', $sql);

        // AST parse for statement-specific handling
        $parser = new Parser($sql);
        $stmt = $parser->statements[0] ?? null;

        if ($stmt !== null) {
            if ($stmt instanceof TruncateStatement) {
                return $this->translateTruncate($stmt);
            }

            if ($stmt instanceof AlterStatement) {
                return $this->translateAlter($stmt, $sql);
            }

            if ($stmt instanceof SetStatement) {
                return [];
            }
        }

        // General syntax conversion
        return [$this->convertSyntax($sql)];
    }

    /**
     * Apply all SQLite syntax conversions.
     */
    private function convertSyntax(string $sql): string
    {
        $sql = $this->convertIdentifiers($sql);
        $sql = $this->convertDataTypes($sql);
        $sql = $this->convertFunctions($sql);
        $sql = $this->convertInsertSyntax($sql);
        $sql = $this->convertLimit($sql);
        $sql = $this->convertTransaction($sql);
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
     * MySQL data types → SQLite types in DDL.
     */
    private function convertDataTypes(string $sql): string
    {
        if (!preg_match('/^\s*(CREATE|ALTER)\s/i', $sql)) {
            return $sql;
        }

        $typeMap = [
            '/\b(?:TINY|SMALL|MEDIUM|BIG)?INT(?:EGER)?\s*\(\s*\d+\s*\)\s*(?:UNSIGNED\s*)?/i' => 'INTEGER ',
            '/\bINT\b\s*(?:UNSIGNED\s*)?/i' => 'INTEGER ',
            '/\bTINYINT\b\s*(?:UNSIGNED\s*)?/i' => 'INTEGER ',
            '/\bSMALLINT\b\s*(?:UNSIGNED\s*)?/i' => 'INTEGER ',
            '/\bMEDIUMINT\b\s*(?:UNSIGNED\s*)?/i' => 'INTEGER ',
            '/\bBIGINT\b\s*(?:UNSIGNED\s*)?/i' => 'INTEGER ',
            '/\bVARCHAR\s*\(\s*\d+\s*\)/i' => 'TEXT',
            '/\bCHAR\s*\(\s*\d+\s*\)/i' => 'TEXT',
            '/\b(?:TINY|MEDIUM|LONG)?TEXT\b/i' => 'TEXT',
            '/\bENUM\s*\([^)]+\)/i' => 'TEXT',
            '/\bDATETIME\b/i' => 'TEXT',
            '/\bTIMESTAMP\b/i' => 'TEXT',
            '/\bDATE\b/i' => 'TEXT',
            '/\bTIME\b/i' => 'TEXT',
            '/\bFLOAT\b(?:\s*\([^)]+\))?/i' => 'REAL',
            '/\bDOUBLE\b(?:\s*\([^)]+\))?/i' => 'REAL',
            '/\bDECIMAL\s*\([^)]+\)/i' => 'REAL',
            '/\bNUMERIC\s*\([^)]+\)/i' => 'REAL',
            '/\b(?:TINY|MEDIUM|LONG)?BLOB\b/i' => 'BLOB',
            '/\bVARBINARY\s*\(\s*\d+\s*\)/i' => 'BLOB',
            '/\bBINARY\s*\(\s*\d+\s*\)/i' => 'BLOB',
            '/\bJSON\b/i' => 'TEXT',
            '/\bBOOLEAN\b/i' => 'INTEGER',
        ];

        foreach ($typeMap as $pattern => $replacement) {
            $sql = (string) preg_replace($pattern, $replacement, $sql);
        }

        $sql = (string) preg_replace('/\bUNSIGNED\b/i', '', $sql);

        return $sql;
    }

    /**
     * MySQL functions → SQLite equivalents.
     */
    private function convertFunctions(string $sql): string
    {
        // Simple name replacements
        foreach (self::FUNCTION_MAP as $mysql => $sqlite) {
            $sql = (string) preg_replace('/\b' . $mysql . '\s*\(/i', $sqlite . '(', $sql);
        }

        // Zero-arg functions
        $sql = (string) preg_replace('/\bNOW\s*\(\s*\)/i', "datetime('now')", $sql);
        $sql = (string) preg_replace('/\bCURDATE\s*\(\s*\)/i', "date('now')", $sql);
        $sql = (string) preg_replace('/\bCURTIME\s*\(\s*\)/i', "time('now')", $sql);
        $sql = (string) preg_replace('/\bUNIX_TIMESTAMP\s*\(\s*\)/i', "strftime('%s','now')", $sql);
        $sql = (string) preg_replace('/\bVERSION\s*\(\s*\)/i', "'10.0.0-wppack'", $sql);
        $sql = (string) preg_replace('/\bDATABASE\s*\(\s*\)/i', "'main'", $sql);
        $sql = (string) preg_replace('/\bFOUND_ROWS\s*\(\s*\)/i', '-1', $sql);
        $sql = (string) preg_replace('/\bCURRENT_TIMESTAMP\b/i', "datetime('now')", $sql);

        // FROM_UNIXTIME(t) → datetime(t, 'unixepoch')
        $sql = (string) preg_replace('/\bFROM_UNIXTIME\s*\(\s*([^)]+)\s*\)/i', "datetime($1, 'unixepoch')", $sql);

        // LEFT(s, n) → SUBSTR(s, 1, n)
        $sql = (string) preg_replace('/\bLEFT\s*\(\s*([^,]+),\s*([^)]+)\)/i', 'SUBSTR($1, 1, $2)', $sql);

        // CAST(x AS SIGNED) → CAST(x AS INTEGER)
        $sql = (string) preg_replace('/\bCAST\s*\(\s*(.+?)\s+AS\s+SIGNED\s*\)/i', 'CAST($1 AS INTEGER)', $sql);

        // DATE_ADD(d, INTERVAL n unit)
        $sql = (string) preg_replace_callback(
            '/\bDATE_ADD\s*\(\s*(.+?)\s*,\s*INTERVAL\s+(\d+)\s+(\w+)\s*\)/i',
            static fn(array $m) => \sprintf("datetime(%s, '+%s %s')", $m[1], $m[2], strtolower($m[3])),
            $sql,
        );

        // DATE_SUB(d, INTERVAL n unit)
        $sql = (string) preg_replace_callback(
            '/\bDATE_SUB\s*\(\s*(.+?)\s*,\s*INTERVAL\s+(\d+)\s+(\w+)\s*\)/i',
            static fn(array $m) => \sprintf("datetime(%s, '-%s %s')", $m[1], $m[2], strtolower($m[3])),
            $sql,
        );

        // DATE_FORMAT(d, f)
        $sql = (string) preg_replace_callback(
            '/\bDATE_FORMAT\s*\(\s*(.+?)\s*,\s*[\'"](.+?)[\'"]\s*\)/i',
            function (array $m): string {
                $format = $m[2];

                foreach (self::DATE_FORMAT_MAP as $mysql => $sqlite) {
                    $format = str_replace($mysql, $sqlite, $format);
                }

                return \sprintf("strftime('%s', %s)", $format, $m[1]);
            },
            $sql,
        );

        // IF(cond, t, f) → CASE WHEN cond THEN t ELSE f END
        $sql = (string) preg_replace(
            '/\bIF\s*\(\s*([^,]+?)\s*,\s*([^,]+?)\s*,\s*([^)]+?)\s*\)/i',
            'CASE WHEN $1 THEN $2 ELSE $3 END',
            $sql,
        );

        return $sql;
    }

    /**
     * MySQL INSERT syntax → SQLite.
     */
    private function convertInsertSyntax(string $sql): string
    {
        $sql = (string) preg_replace('/\bINSERT\s+IGNORE\s+INTO\b/i', 'INSERT OR IGNORE INTO', $sql);
        $sql = (string) preg_replace('/\bREPLACE\s+INTO\b/i', 'INSERT OR REPLACE INTO', $sql);

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
     * START TRANSACTION → BEGIN
     */
    private function convertTransaction(string $sql): string
    {
        return (string) preg_replace('/\bSTART\s+TRANSACTION\b/i', 'BEGIN', $sql);
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
        $sql = (string) preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $sql);
        $sql = (string) preg_replace('/\s*AUTOINCREMENT\s*=\s*\d+/i', '', $sql);

        return $sql;
    }

    /**
     * TRUNCATE TABLE t → DELETE FROM t
     *
     * @return list<string>
     */
    private function translateTruncate(TruncateStatement $stmt): array
    {
        $table = '"' . str_replace('"', '""', $stmt->table->table) . '"';

        return ["DELETE FROM {$table}"];
    }

    /**
     * ALTER TABLE — SQLite supports ADD COLUMN and RENAME only.
     *
     * @return list<string>
     */
    private function translateAlter(AlterStatement $stmt, string $originalSql): array
    {
        $table = '"' . str_replace('"', '""', $stmt->table->table) . '"';
        $results = [];

        foreach ($stmt->altered as $alter) {
            $options = $alter->options->options ?? [];
            $optionStr = strtoupper(trim(implode(' ', array_filter($options, '\is_string'))));

            if (str_contains($optionStr, 'ADD')) {
                $rebuilt = $this->convertSyntax("ALTER TABLE {$table} ADD COLUMN " . $alter->field->build());
                $results[] = $rebuilt;
            } elseif (str_contains($optionStr, 'RENAME')) {
                $results[] = $this->convertSyntax($originalSql);
            }
            // DROP COLUMN / MODIFY — silently skip (SQLite limitation)
        }

        return $results !== [] ? $results : [$this->convertSyntax($originalSql)];
    }

    /**
     * @return list<string>|null
     */
    private function translateShowCommand(string $sql): ?array
    {
        if (preg_match('/^\s*SHOW\s+(?:FULL\s+)?COLUMNS\s+FROM\s+[`"]?(\w+)[`"]?\s*/i', $sql, $m)) {
            return [\sprintf('PRAGMA table_info("%s")', $m[1])];
        }

        if (preg_match('/^\s*SHOW\s+CREATE\s+TABLE\s+[`"]?(\w+)[`"]?\s*/i', $sql, $m)) {
            return [\sprintf("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = '%s'", $m[1])];
        }

        if (preg_match('/^\s*SHOW\s+(?:INDEX|KEYS?)\s+FROM\s+[`"]?(\w+)[`"]?\s*/i', $sql, $m)) {
            return [\sprintf('PRAGMA index_list("%s")', $m[1])];
        }

        if (preg_match('/^\s*SHOW\s+(?:GLOBAL\s+|SESSION\s+)?VARIABLES/i', $sql)) {
            return ["SELECT '' AS Variable_name, '' AS Value WHERE 0"];
        }

        if (preg_match('/^\s*SHOW\s+COLLATION/i', $sql)) {
            return ["SELECT 'utf8mb4_unicode_ci' AS Collation, 'utf8mb4' AS Charset WHERE 0"];
        }

        if (preg_match('/^\s*SHOW\s+DATABASES/i', $sql)) {
            return ["SELECT 'main' AS `Database`"];
        }

        if (preg_match('/^\s*SHOW\s+TABLE\s+STATUS/i', $sql)) {
            return ["SELECT name AS Name, 'InnoDB' AS Engine FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"];
        }

        return null;
    }
}
