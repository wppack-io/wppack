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

use PhpMyAdmin\SqlParser\Components\Condition;
use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Statements\SetStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Translates MySQL SQL to SQLite SQL using AST-based walking.
 *
 * Parses MySQL SQL into an AST via phpmyadmin/sql-parser, then walks each
 * statement component (expressions, conditions, table references, limits)
 * to apply SQLite-specific transformations. Subqueries within expressions
 * are properly bounded — no whole-SQL regex that could mis-match.
 */
final class SqliteQueryTranslator implements QueryTranslatorInterface
{
    /** @var list<string> */
    private const IGNORED_PATTERNS = [
        '/^\s*SET\s+(SESSION\s+|GLOBAL\s+)?/i',
        '/^\s*LOCK\s+TABLES?\s+/i',
        '/^\s*UNLOCK\s+TABLES?\s*/i',
        '/^\s*OPTIMIZE\s+TABLE\s+/i',
        '/^\s*ANALYZE\s+TABLE\s+/i',
        '/^\s*CHECK\s+TABLE\s+/i',
        '/^\s*REPAIR\s+TABLE\s+/i',
        '/^\s*CREATE\s+DATABASE\b/i',
        '/^\s*DROP\s+DATABASE\b/i',
    ];

    public function translate(string $sql): array
    {
        $trimmed = trim($sql);

        // Early return for ignored statements
        foreach (self::IGNORED_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return [];
            }
        }

        // SHOW / DESCRIBE — handled before AST
        if ($result = $this->translateMetaCommand($trimmed)) {
            return $result;
        }

        // SAVEPOINT — SQLite native support
        if (preg_match('/^\s*(SAVEPOINT|RELEASE\s+SAVEPOINT|ROLLBACK\s+TO\s+SAVEPOINT)\b/i', $trimmed)) {
            return [$this->quoteIdentifiers($sql)];
        }

        // AST parse
        $parser = new Parser($sql);
        $stmt = $parser->statements[0] ?? null;

        if ($stmt === null) {
            return [$this->quoteIdentifiers($this->transformExpression($sql))];
        }

        return match (true) {
            $stmt instanceof SelectStatement => [$this->translateSelect($stmt)],
            $stmt instanceof InsertStatement => [$this->translateInsert($stmt)],
            $stmt instanceof ReplaceStatement => [$this->translateReplace($stmt)],
            $stmt instanceof UpdateStatement => [$this->translateUpdate($stmt)],
            $stmt instanceof DeleteStatement => [$this->translateDelete($stmt)],
            $stmt instanceof CreateStatement => [$this->translateCreate($stmt)],
            $stmt instanceof TruncateStatement => $this->translateTruncate($stmt),
            $stmt instanceof AlterStatement => $this->translateAlter($stmt, $sql),
            $stmt instanceof SetStatement => [],
            default => [$this->quoteIdentifiers($this->transformExpression($sql))],
        };
    }

    // ── Statement-level AST walk ──

    private function translateSelect(SelectStatement $stmt): string
    {
        // Transform expressions
        if ($stmt->expr !== null) {
            foreach ($stmt->expr as $expr) {
                $expr->expr = $this->transformExpression($expr->expr);
            }
        }

        // Transform WHERE conditions
        $this->transformConditions($stmt->where ?? []);

        // Transform HAVING conditions
        $this->transformConditions($stmt->having ?? []);

        // Transform JOIN conditions
        if ($stmt->join !== null) {
            foreach ($stmt->join as $join) {
                if ($join->on !== null) {
                    $this->transformConditions($join->on);
                }
            }
        }

        $sql = $stmt->build();

        // LIMIT offset,count → LIMIT count OFFSET offset (post-build)
        $sql = (string) preg_replace('/\bLIMIT\s+(\d+)\s*,\s*(\d+)/i', 'LIMIT $2 OFFSET $1', $sql);

        // Strip OFFSET 0 (unnecessary)
        $sql = (string) preg_replace('/\s+OFFSET\s+0\b/i', '', $sql);

        // Strip FOR UPDATE
        $sql = (string) preg_replace('/\s+FOR\s+UPDATE\b/i', '', $sql);

        return $this->quoteIdentifiers($sql);
    }

    private function translateInsert(InsertStatement $stmt): string
    {
        $sql = $stmt->build();

        // INSERT IGNORE INTO → INSERT OR IGNORE INTO
        $sql = (string) preg_replace('/\bINSERT\s+IGNORE\s+INTO\b/i', 'INSERT OR IGNORE INTO', $sql);

        // ON DUPLICATE KEY UPDATE → ON CONFLICT DO UPDATE SET
        $sql = $this->convertOnDuplicateKey($sql);

        return $this->quoteIdentifiers($this->transformExpression($sql));
    }

    private function translateReplace(ReplaceStatement $stmt): string
    {
        $sql = $stmt->build();
        $sql = (string) preg_replace('/\bREPLACE\s+INTO\b/i', 'INSERT OR REPLACE INTO', $sql);

        return $this->quoteIdentifiers($this->transformExpression($sql));
    }

    private function translateUpdate(UpdateStatement $stmt): string
    {
        // Transform SET expressions
        if ($stmt->set !== null) {
            foreach ($stmt->set as $set) {
                $set->value = $this->transformExpression($set->value);
            }
        }

        $this->transformConditions($stmt->where ?? []);

        return $this->quoteIdentifiers($stmt->build());
    }

    private function translateDelete(DeleteStatement $stmt): string
    {
        $this->transformConditions($stmt->where ?? []);

        return $this->quoteIdentifiers($stmt->build());
    }

    private function translateCreate(CreateStatement $stmt): string
    {
        $sql = $stmt->build();

        return $this->quoteIdentifiers($this->transformDdl($sql));
    }

    /**
     * @return list<string>
     */
    private function translateTruncate(TruncateStatement $stmt): array
    {
        $table = $this->sqliteQuote($stmt->table->table);

        return ["DELETE FROM {$table}"];
    }

    /**
     * @return list<string>
     */
    private function translateAlter(AlterStatement $stmt, string $originalSql): array
    {
        $table = $this->sqliteQuote($stmt->table->table);
        $results = [];

        foreach ($stmt->altered as $alter) {
            $options = $alter->options->options ?? [];
            $optionStr = strtoupper(trim(implode(' ', array_filter($options, '\is_string'))));

            if (str_contains($optionStr, 'ADD') && $alter->field !== null) {
                $fieldSql = $this->transformDdl($alter->field->build());
                $results[] = $this->quoteIdentifiers("ALTER TABLE {$table} ADD COLUMN {$fieldSql}");
            } elseif (str_contains($optionStr, 'RENAME')) {
                $results[] = $this->quoteIdentifiers($this->transformDdl($originalSql));
            }
            // DROP COLUMN / MODIFY / ADD INDEX — silently skip (SQLite limitation)
        }

        return $results !== [] ? $results : [];
    }

    // ── Expression transformation ──

    /**
     * Transform MySQL functions and syntax within an expression string.
     */
    private function transformExpression(string $expr): string
    {
        // Zero-arg functions
        $expr = (string) preg_replace('/\bNOW\s*\(\s*\)/i', "datetime('now')", $expr);
        $expr = (string) preg_replace('/\bCURDATE\s*\(\s*\)/i', "date('now')", $expr);
        $expr = (string) preg_replace('/\bCURTIME\s*\(\s*\)/i', "time('now')", $expr);
        $expr = (string) preg_replace('/\bUNIX_TIMESTAMP\s*\(\s*\)/i', "strftime('%s','now')", $expr);
        $expr = (string) preg_replace('/\bCURRENT_TIMESTAMP\b/i', "datetime('now')", $expr);
        $expr = (string) preg_replace('/\bVERSION\s*\(\s*\)/i', "'10.0.0-wppack'", $expr);
        $expr = (string) preg_replace('/\bDATABASE\s*\(\s*\)/i', "'main'", $expr);
        $expr = (string) preg_replace('/\bFOUND_ROWS\s*\(\s*\)/i', '-1', $expr);

        // Function renames
        $expr = (string) preg_replace('/\bRAND\s*\(/i', 'random(', $expr);
        $expr = (string) preg_replace('/\bLAST_INSERT_ID\s*\(/i', 'last_insert_rowid(', $expr);
        $expr = (string) preg_replace('/\bSUBSTRING\s*\(/i', 'SUBSTR(', $expr);
        $expr = (string) preg_replace('/\bCHAR_LENGTH\s*\(/i', 'LENGTH(', $expr);

        // FROM_UNIXTIME(t) → datetime(t, 'unixepoch')
        $expr = (string) preg_replace('/\bFROM_UNIXTIME\s*\(\s*([^)]+)\s*\)/i', "datetime($1, 'unixepoch')", $expr);

        // LEFT(s, n) → SUBSTR(s, 1, n)
        $expr = (string) preg_replace('/\bLEFT\s*\(\s*([^,]+),\s*([^)]+)\)/i', 'SUBSTR($1, 1, $2)', $expr);

        // CAST(x AS SIGNED) → CAST(x AS INTEGER)
        $expr = (string) preg_replace('/\bCAST\s*\(\s*(.+?)\s+AS\s+SIGNED\s*\)/i', 'CAST($1 AS INTEGER)', $expr);

        // DATE_ADD(d, INTERVAL n unit) → datetime(d, '+n unit')
        $expr = (string) preg_replace_callback(
            '/\bDATE_ADD\s*\(\s*(.+?)\s*,\s*INTERVAL\s+(\d+)\s+(\w+)\s*\)/i',
            static fn(array $m) => \sprintf("datetime(%s, '+%s %s')", $m[1], $m[2], strtolower($m[3])),
            $expr,
        );

        // DATE_SUB(d, INTERVAL n unit) → datetime(d, '-n unit')
        $expr = (string) preg_replace_callback(
            '/\bDATE_SUB\s*\(\s*(.+?)\s*,\s*INTERVAL\s+(\d+)\s+(\w+)\s*\)/i',
            static fn(array $m) => \sprintf("datetime(%s, '-%s %s')", $m[1], $m[2], strtolower($m[3])),
            $expr,
        );

        // DATE_FORMAT(d, f) → strftime(converted_f, d)
        $expr = (string) preg_replace_callback(
            '/\bDATE_FORMAT\s*\(\s*(.+?)\s*,\s*[\'"](.+?)[\'"]\s*\)/i',
            static function (array $m): string {
                $format = str_replace(
                    ['%Y', '%m', '%d', '%H', '%i', '%s', '%j', '%W'],
                    ['%Y', '%m', '%d', '%H', '%M', '%S', '%j', '%w'],
                    $m[2],
                );

                return \sprintf("strftime('%s', %s)", $format, $m[1]);
            },
            $expr,
        );

        // IF(cond, t, f) → CASE WHEN cond THEN t ELSE f END
        $expr = (string) preg_replace(
            '/\bIF\s*\(\s*([^,]+?)\s*,\s*([^,]+?)\s*,\s*([^)]+?)\s*\)/i',
            'CASE WHEN $1 THEN $2 ELSE $3 END',
            $expr,
        );

        return $expr;
    }

    /**
     * Transform conditions (WHERE, HAVING, JOIN ON).
     *
     * @param list<Condition> $conditions
     */
    private function transformConditions(array $conditions): void
    {
        foreach ($conditions as $cond) {
            if (!$cond->isOperator) {
                $cond->expr = $this->transformExpression($cond->expr);
            }
        }
    }

    // ── DDL transformation ──

    private function transformDdl(string $sql): string
    {
        // Data types
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

        // Strip MySQL-specific clauses
        $sql = (string) preg_replace('/\bUNSIGNED\b/i', '', $sql);
        $sql = (string) preg_replace('/\s*ENGINE\s*=\s*\w+/i', '', $sql);
        $sql = (string) preg_replace('/\s*DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
        $sql = (string) preg_replace('/\s*COLLATE\s*=\s*\w+/i', '', $sql);
        $sql = (string) preg_replace('/\s*CHARACTER\s+SET\s+\w+/i', '', $sql);
        $sql = (string) preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $sql);
        $sql = (string) preg_replace('/\s*AUTOINCREMENT\s*=\s*\d+/i', '', $sql);

        // SQLite requires AUTOINCREMENT on the same line as INTEGER PRIMARY KEY.
        // WordPress pattern: `ID` bigint(20) AUTO_INCREMENT ... PRIMARY KEY (`ID`)
        // After type conversion: `ID` INTEGER AUTOINCREMENT ... PRIMARY KEY (`ID`)
        // Must merge into: `ID` INTEGER PRIMARY KEY AUTOINCREMENT
        if (str_contains($sql, 'AUTOINCREMENT') && preg_match('/PRIMARY\s+KEY\s*\(\s*[`"]?(\w+)[`"]?\s*\)/i', $sql, $pkMatch)) {
            $pkCol = $pkMatch[1];

            // Add PRIMARY KEY before AUTOINCREMENT on the column definition
            $sql = (string) preg_replace(
                '/(["`]?' . preg_quote($pkCol, '/') . '["`]?\s+INTEGER\s.*?)\bAUTOINCREMENT\b/i',
                '$1PRIMARY KEY AUTOINCREMENT',
                $sql,
            );

            // Remove the separate PRIMARY KEY line
            $sql = (string) preg_replace('/,?\s*PRIMARY\s+KEY\s*\([^)]+\)/i', '', $sql);
        }

        return $sql;
    }

    // ── Identifier quoting ──

    /**
     * Convert backtick-quoted identifiers to double-quoted (SQLite style).
     */
    private function quoteIdentifiers(string $sql): string
    {
        return (string) preg_replace_callback('/`([^`]*(?:``[^`]*)*)`/', static function (array $m): string {
            $inner = str_replace('``', '`', $m[1]);
            $inner = str_replace('"', '""', $inner);

            return '"' . $inner . '"';
        }, $sql);
    }

    private function sqliteQuote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    // ── Transaction ──

    private function convertOnDuplicateKey(string $sql): string
    {
        if (preg_match('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\s+(.+)$/is', $sql, $m)) {
            $updateClause = (string) preg_replace('/\bVALUES\s*\(\s*([^)]+)\s*\)/i', 'excluded.$1', $m[1]);
            $sql = (string) preg_replace('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\s+.+$/is', 'ON CONFLICT DO UPDATE SET ' . $updateClause, $sql);
        }

        return $sql;
    }

    // ── Transaction / START TRANSACTION ──

    /**
     * @return list<string>|null
     */
    private function translateMetaCommand(string $sql): ?array
    {
        // START TRANSACTION → BEGIN
        if (preg_match('/^\s*START\s+TRANSACTION\b/i', $sql)) {
            return ['BEGIN'];
        }

        // SHOW statements
        if (preg_match('/^\s*SHOW\s+TABLES\s*/i', $sql)) {
            return ["SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"];
        }

        if (preg_match('/^\s*SHOW\s+FULL\s+TABLES\s*/i', $sql)) {
            return ["SELECT name, 'BASE TABLE' AS Table_type FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"];
        }

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

        if (preg_match('/^\s*DESCRIBE\s+[`"]?(\w+)[`"]?\s*/i', $sql, $m)) {
            return [\sprintf('PRAGMA table_info("%s")', $m[1])];
        }

        return null;
    }
}
