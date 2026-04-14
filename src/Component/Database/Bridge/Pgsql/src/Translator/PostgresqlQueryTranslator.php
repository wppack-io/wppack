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

use PhpMyAdmin\SqlParser\Components\Condition;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Statements\SetStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Translates MySQL SQL to PostgreSQL SQL using AST-based walking.
 */
final class PostgresqlQueryTranslator implements QueryTranslatorInterface
{
    /** @var list<string> */
    private const IGNORED_PATTERNS = [
        '/^\s*SET\s+NAMES\s+/i',
        '/^\s*LOCK\s+TABLES?\s+/i',
        '/^\s*UNLOCK\s+TABLES?\s*/i',
        '/^\s*OPTIMIZE\s+TABLE\s+/i',
        '/^\s*CHECK\s+TABLE\s+/i',
        '/^\s*REPAIR\s+TABLE\s+/i',
        '/^\s*CREATE\s+DATABASE\b/i',
        '/^\s*DROP\s+DATABASE\b/i',
    ];

    public function translate(string $sql): array
    {
        $trimmed = trim($sql);

        foreach (self::IGNORED_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return [];
            }
        }

        if ($result = $this->translateMetaCommand($trimmed)) {
            return $result;
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
            $stmt instanceof UpdateStatement => [$this->translateUpdate($stmt)],
            $stmt instanceof DeleteStatement => [$this->translateDelete($stmt)],
            $stmt instanceof CreateStatement => [$this->translateCreate($stmt)],
            $stmt instanceof TruncateStatement => [$this->translateTruncate($stmt)],
            $stmt instanceof SetStatement => [],
            default => [$this->quoteIdentifiers($this->transformExpression($sql))],
        };
    }

    // ── Statement-level AST walk ──

    private function translateSelect(SelectStatement $stmt): string
    {
        if ($stmt->expr !== null) {
            foreach ($stmt->expr as $expr) {
                $expr->expr = $this->transformExpression($expr->expr);
            }
        }

        $this->transformConditions($stmt->where ?? []);
        $this->transformConditions($stmt->having ?? []);

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

        return $this->quoteIdentifiers($sql);
    }

    private function translateInsert(InsertStatement $stmt): string
    {
        $sql = $stmt->build();

        $sql = (string) preg_replace('/\bINSERT\s+IGNORE\s+INTO\b/i', 'INSERT INTO', $sql);

        if (preg_match('/\bINSERT\s+IGNORE\b/i', $stmt->build())) {
            $sql = rtrim($sql, " \t\n\r;") . ' ON CONFLICT DO NOTHING';
        }

        // Original had IGNORE
        if (preg_match('/IGNORE/i', $stmt->build())) {
            $sql = (string) preg_replace('/\bINSERT\s+INTO\b/i', 'INSERT INTO', $sql);
            if (!str_contains($sql, 'ON CONFLICT')) {
                $sql = rtrim($sql, " \t\n\r;") . ' ON CONFLICT DO NOTHING';
            }
        }

        $sql = $this->convertOnDuplicateKey($sql);

        return $this->quoteIdentifiers($this->transformExpression($sql));
    }

    private function translateUpdate(UpdateStatement $stmt): string
    {
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

    private function translateTruncate(TruncateStatement $stmt): string
    {
        $table = '"' . str_replace('"', '""', $stmt->table->table) . '"';

        return "TRUNCATE TABLE {$table}";
    }

    // ── Expression transformation ──

    private function transformExpression(string $expr): string
    {
        // Function renames
        $expr = (string) preg_replace('/\bRAND\s*\(/i', 'random(', $expr);
        $expr = (string) preg_replace('/\bIFNULL\s*\(/i', 'COALESCE(', $expr);

        // Zero-arg
        $expr = (string) preg_replace('/\bCURDATE\s*\(\s*\)/i', 'CURRENT_DATE', $expr);
        $expr = (string) preg_replace('/\bCURTIME\s*\(\s*\)/i', 'CURRENT_TIME', $expr);
        $expr = (string) preg_replace('/\bUNIX_TIMESTAMP\s*\(\s*\)/i', 'EXTRACT(EPOCH FROM NOW())::INTEGER', $expr);
        $expr = (string) preg_replace('/\bLAST_INSERT_ID\s*\(\s*\)/i', 'lastval()', $expr);
        $expr = (string) preg_replace('/\bDATABASE\s*\(\s*\)/i', 'CURRENT_DATABASE()', $expr);
        $expr = (string) preg_replace('/\bFOUND_ROWS\s*\(\s*\)/i', '-1', $expr);

        // FROM_UNIXTIME(t) → TO_TIMESTAMP(t)
        $expr = (string) preg_replace('/\bFROM_UNIXTIME\s*\(\s*([^)]+)\s*\)/i', 'TO_TIMESTAMP($1)', $expr);

        // LEFT(s, n) → SUBSTRING(s FROM 1 FOR n)
        $expr = (string) preg_replace('/\bLEFT\s*\(\s*([^,]+),\s*([^)]+)\)/i', 'SUBSTRING($1 FROM 1 FOR $2)', $expr);

        // CAST(x AS SIGNED) → CAST(x AS INTEGER)
        $expr = (string) preg_replace('/\bCAST\s*\(\s*(.+?)\s+AS\s+SIGNED\s*\)/i', 'CAST($1 AS INTEGER)', $expr);

        // DATE_ADD(d, INTERVAL n unit) → d + INTERVAL 'n unit'
        $expr = (string) preg_replace_callback(
            '/\bDATE_ADD\s*\(\s*(.+?)\s*,\s*INTERVAL\s+(\d+)\s+(\w+)\s*\)/i',
            static fn(array $m) => \sprintf('%s + INTERVAL \'%s %s\'', $m[1], $m[2], strtolower($m[3])),
            $expr,
        );

        // DATE_SUB(d, INTERVAL n unit) → d - INTERVAL 'n unit'
        $expr = (string) preg_replace_callback(
            '/\bDATE_SUB\s*\(\s*(.+?)\s*,\s*INTERVAL\s+(\d+)\s+(\w+)\s*\)/i',
            static fn(array $m) => \sprintf('%s - INTERVAL \'%s %s\'', $m[1], $m[2], strtolower($m[3])),
            $expr,
        );

        // DATE_FORMAT(d, f) → TO_CHAR(d, converted_f)
        $expr = (string) preg_replace_callback(
            '/\bDATE_FORMAT\s*\(\s*(.+?)\s*,\s*[\'"](.+?)[\'"]\s*\)/i',
            static function (array $m): string {
                $format = str_replace(
                    ['%Y', '%m', '%d', '%H', '%i', '%s'],
                    ['YYYY', 'MM', 'DD', 'HH24', 'MI', 'SS'],
                    $m[2],
                );

                return \sprintf("TO_CHAR(%s, '%s')", $m[1], $format);
            },
            $expr,
        );

        // IF(cond, t, f) → CASE WHEN cond THEN t ELSE f END
        $expr = (string) preg_replace(
            '/\bIF\s*\(\s*([^,]+?)\s*,\s*([^,]+?)\s*,\s*([^)]+?)\s*\)/i',
            'CASE WHEN $1 THEN $2 ELSE $3 END',
            $expr,
        );

        // REGEXP → ~*
        $expr = (string) preg_replace('/\s+REGEXP\s+/i', ' ~* ', $expr);

        return $expr;
    }

    /**
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

    // ── DDL ──

    private function transformDdl(string $sql): string
    {
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
        $sql = (string) preg_replace('/\s*ENGINE\s*=\s*\w+/i', '', $sql);
        $sql = (string) preg_replace('/\s*DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
        $sql = (string) preg_replace('/\s*COLLATE\s*=\s*\w+/i', '', $sql);
        $sql = (string) preg_replace('/\s*CHARACTER\s+SET\s+\w+/i', '', $sql);
        $sql = (string) preg_replace('/\bAUTO_INCREMENT\b/i', 'SERIAL', $sql);
        $sql = (string) preg_replace('/\s*SERIAL\s*=\s*\d+/i', '', $sql);

        return $sql;
    }

    // ── Helpers ──

    private function quoteIdentifiers(string $sql): string
    {
        return (string) preg_replace_callback('/`([^`]*(?:``[^`]*)*)`/', static function (array $m): string {
            $inner = str_replace('``', '`', $m[1]);
            $inner = str_replace('"', '""', $inner);

            return '"' . $inner . '"';
        }, $sql);
    }

    private function convertOnDuplicateKey(string $sql): string
    {
        if (preg_match('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\s+(.+)$/is', $sql, $m)) {
            $updateClause = (string) preg_replace('/\bVALUES\s*\(\s*([^)]+)\s*\)/i', 'excluded.$1', $m[1]);
            $sql = (string) preg_replace('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\s+.+$/is', 'ON CONFLICT DO UPDATE SET ' . $updateClause, $sql);
        }

        return $sql;
    }

    /**
     * @return list<string>|null
     */
    private function translateMetaCommand(string $sql): ?array
    {
        if (preg_match('/^\s*START\s+TRANSACTION\b/i', $sql)) {
            return ['BEGIN'];
        }

        if (preg_match('/^\s*SHOW\s+TABLES\s*/i', $sql)) {
            return ["SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"];
        }

        if (preg_match('/^\s*SHOW\s+FULL\s+TABLES\s*/i', $sql)) {
            return ["SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = 'public'"];
        }

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

        if (preg_match('/^\s*DESCRIBE\s+[`"]?(\w+)[`"]?\s*/i', $sql, $m)) {
            return [\sprintf(
                "SELECT column_name AS \"Field\", data_type AS \"Type\" FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '%s'",
                $m[1],
            )];
        }

        return null;
    }
}
