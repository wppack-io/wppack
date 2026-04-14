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
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Statements\SetStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use PhpMyAdmin\SqlParser\Token;
use PhpMyAdmin\SqlParser\TokenType;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Translates MySQL SQL to SQLite SQL using AST-guided token rewriting.
 *
 * Uses phpmyadmin/sql-parser's Parser for AST (structural understanding) and
 * QueryRewriter for token-level manipulation (expression transformation).
 *
 * String literals (TokenType::String) are never transformed — they pass through
 * QueryRewriter::consume() untouched.
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

    /**
     * Zero-argument functions: keyword → full replacement (replaces FUNC()).
     *
     * @var array<string, string>
     */
    private const ZERO_ARG_MAP = [
        'NOW' => "datetime('now')",
        'CURDATE' => "date('now')",
        'CURTIME' => "time('now')",
        'UNIX_TIMESTAMP' => "strftime('%s','now')",
        'UTC_TIMESTAMP' => "datetime('now')",
        'UTC_DATE' => "date('now')",
        'UTC_TIME' => "time('now')",
        'VERSION' => "'10.0.0-wppack'",
        'DATABASE' => "'main'",
        'FOUND_ROWS' => '-1',
    ];

    /**
     * Standalone keyword replacements (no parens).
     *
     * @var array<string, string>
     */
    private const KEYWORD_MAP = [
        'CURRENT_TIMESTAMP' => "datetime('now')",
    ];

    /**
     * Function rename map: just the name is replaced.
     *
     * @var array<string, string>
     */
    private const RENAME_MAP = [
        'RAND' => 'random',
        'LAST_INSERT_ID' => 'last_insert_rowid',
        'SUBSTRING' => 'SUBSTR',
        'CHAR_LENGTH' => 'LENGTH',
        'CHARACTER_LENGTH' => 'LENGTH',
        'MID' => 'SUBSTR',
        'LCASE' => 'lower',
        'UCASE' => 'upper',
        'LOCATE' => 'INSTR',
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

        if (preg_match('/^\s*(SAVEPOINT|RELEASE\s+SAVEPOINT|ROLLBACK\s+TO\s+SAVEPOINT)\b/i', $trimmed)) {
            return [$trimmed];
        }

        $parser = new Parser($sql);
        $stmt = $parser->statements[0] ?? null;

        if ($stmt === null) {
            return [$this->rewriteTokens($parser)];
        }

        return match (true) {
            $stmt instanceof SelectStatement => [$this->translateSelect($stmt, $parser)],
            $stmt instanceof InsertStatement => $this->translateInsert($stmt, $parser),
            $stmt instanceof ReplaceStatement => [$this->translateReplace($parser)],
            $stmt instanceof UpdateStatement => [$this->translateUpdate($stmt, $parser)],
            $stmt instanceof DeleteStatement => [$this->translateDelete($stmt, $parser)],
            $stmt instanceof CreateStatement => $this->translateCreate($stmt, $parser),
            $stmt instanceof AlterStatement => $this->translateAlter($stmt, $parser),
            $stmt instanceof TruncateStatement => $this->translateTruncate($stmt),
            $stmt instanceof SetStatement => [],
            default => [$this->rewriteTokens($parser)],
        };
    }

    // ── DML handlers ──

    private function translateSelect(SelectStatement $stmt, Parser $parser): string
    {
        $rw = $this->createRewriter($parser);

        while ($rw->hasMore()) {
            $token = $rw->peek();
            if ($token === null) {
                break;
            }

            // Composed keyword: FOR UPDATE → strip
            if ($token->type === TokenType::Keyword && $token->keyword === 'FOR UPDATE') {
                $rw->skip();
                continue;
            }

            // SQL_CALC_FOUND_ROWS → skip
            if ($token->type === TokenType::Keyword && $token->keyword === 'SQL_CALC_FOUND_ROWS') {
                $rw->skip();
                continue;
            }

            // LIMIT: rewrite using AST info
            if ($token->type === TokenType::Keyword && $token->keyword === 'LIMIT' && $stmt->limit !== null) {
                $this->rewriteLimit($rw, $stmt->limit->offset, $stmt->limit->rowCount);
                continue;
            }

            $this->translateExpression($rw);
        }

        return $rw->getResult();
    }

    /**
     * @return list<string>
     */
    private function translateInsert(InsertStatement $stmt, Parser $parser): array
    {
        $rw = $this->createRewriter($parser);
        $hasIgnore = $stmt->options !== null && $stmt->options->has('IGNORE');
        $hasOnDuplicate = $stmt->onDuplicateSet !== null && $stmt->onDuplicateSet !== [];
        $inOnConflictUpdate = false;

        while ($rw->hasMore()) {
            $token = $rw->peek();
            if ($token === null) {
                break;
            }

            // INSERT → INSERT (handle IGNORE later)
            if ($token->type === TokenType::Keyword && $token->keyword === 'INSERT') {
                $rw->consume();

                if ($hasIgnore) {
                    // Skip IGNORE keyword
                    $next = $rw->peek();
                    if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'IGNORE') {
                        $rw->skip();
                    }
                    $rw->dropLast();
                    $rw->add('INSERT OR IGNORE');
                }

                continue;
            }

            // ON DUPLICATE KEY UPDATE → ON CONFLICT DO UPDATE SET
            if ($token->type === TokenType::Keyword && $token->keyword === 'ON' && $hasOnDuplicate) {
                $next = $rw->peekNth(2);
                if ($next !== null && $next->keyword === 'DUPLICATE') {
                    // Skip ON, DUPLICATE, KEY, UPDATE
                    $rw->skip();
                    $rw->skip();
                    $rw->skip();
                    $rw->skip();
                    $rw->add('ON CONFLICT DO UPDATE SET');
                    $inOnConflictUpdate = true;
                    continue;
                }
            }

            // VALUES(col) in ON CONFLICT context → excluded.col
            if ($inOnConflictUpdate && $token->type === TokenType::Keyword
                && $token->keyword === 'VALUES'
                && $rw->peekNth(2)?->token === '(') {
                $rw->skip(); // skip VALUES
                $rw->skip(); // skip (
                $inner = $rw->peek();
                $colName = $inner !== null ? ($inner->type === TokenType::Symbol && ($inner->flags & Token::FLAG_SYMBOL_BACKTICK) !== 0
                    ? '"' . str_replace('"', '""', (string) $inner->value) . '"'
                    : $inner->token) : '';
                $rw->skip(); // skip column name
                $rw->skip(); // skip )
                $rw->add('excluded.' . $colName);
                continue;
            }

            // LIMIT rewrite
            if ($token->type === TokenType::Keyword && $token->keyword === 'LIMIT') {
                // Peek for InsertStatement — INSERT doesn't have limit, but just in case
                $this->rewriteLimitFromTokens($rw);
                continue;
            }

            $this->translateExpression($rw);
        }

        return [$rw->getResult()];
    }

    private function translateReplace(Parser $parser): string
    {
        $rw = $this->createRewriter($parser);

        while ($rw->hasMore()) {
            $token = $rw->peek();
            if ($token === null) {
                break;
            }

            // REPLACE → INSERT OR REPLACE (DML, not function)
            if ($token->type === TokenType::Keyword && $token->keyword === 'REPLACE'
                && $rw->peekNth(2)?->token !== '(') {
                $rw->skip();
                $rw->add('INSERT OR REPLACE');
                continue;
            }

            $this->translateExpression($rw);
        }

        return $rw->getResult();
    }

    private function translateUpdate(UpdateStatement $stmt, Parser $parser): string
    {
        // SQLite does not support UPDATE ... LIMIT — wrap with rowid subquery
        if ($stmt->limit !== null) {
            return $this->rewriteWithRowidSubquery($stmt, $parser, 'UPDATE');
        }

        return $this->rewriteTokens($parser);
    }

    private function translateDelete(DeleteStatement $stmt, Parser $parser): string
    {
        // SQLite does not support DELETE ... LIMIT — wrap with rowid subquery
        if ($stmt->limit !== null) {
            return $this->rewriteWithRowidSubquery($stmt, $parser, 'DELETE');
        }

        return $this->rewriteTokens($parser);
    }

    /**
     * Rewrite UPDATE/DELETE with LIMIT using rowid subquery.
     *
     * MySQL:   UPDATE t SET col = val WHERE cond LIMIT N
     * SQLite:  UPDATE t SET col = val WHERE rowid IN (SELECT rowid FROM t WHERE cond LIMIT N)
     *
     * MySQL:   DELETE FROM t WHERE cond ORDER BY col LIMIT N
     * SQLite:  DELETE FROM t WHERE rowid IN (SELECT rowid FROM t WHERE cond ORDER BY col LIMIT N)
     */
    private function rewriteWithRowidSubquery(UpdateStatement|DeleteStatement $stmt, Parser $parser, string $verb): string
    {
        // Extract table name from AST
        $tableName = match (true) {
            $stmt instanceof UpdateStatement => $stmt->tables[0]->table ?? null,
            $stmt instanceof DeleteStatement => $stmt->from[0]->table ?? null,
        };

        if ($tableName === null) {
            return $this->rewriteTokens($parser);
        }

        $quotedTable = $this->quoteId($tableName);
        $limit = $stmt->limit->rowCount;

        // Build WHERE conditions from AST
        $whereParts = [];
        if ($stmt->where !== null) {
            foreach ($stmt->where as $cond) {
                $whereParts[] = $cond->expr;
            }
        }
        $whereClause = $whereParts !== [] ? implode(' ', $whereParts) : '1=1';

        // Build ORDER BY from AST
        $orderParts = [];
        if ($stmt->order !== null) {
            foreach ($stmt->order as $order) {
                $orderParts[] = $order->expr->expr . ' ' . $order->type->value;
            }
        }
        $orderClause = $orderParts !== [] ? ' ORDER BY ' . implode(', ', $orderParts) : '';

        // Build the rowid subquery
        $subquery = \sprintf(
            'rowid IN (SELECT rowid FROM %s WHERE %s%s LIMIT %s)',
            $quotedTable,
            $whereClause,
            $orderClause,
            $limit,
        );

        // Rewrite the statement, replacing WHERE/ORDER/LIMIT with rowid subquery
        $rw = $this->createRewriter($parser);

        while ($rw->hasMore()) {
            $token = $rw->peek();
            if ($token === null) {
                break;
            }

            // Stop at WHERE, ORDER BY, or LIMIT — replace with our subquery
            if ($token->type === TokenType::Keyword
                && \in_array($token->keyword, ['WHERE', 'ORDER BY', 'LIMIT'], true)) {
                // Discard remaining tokens
                do {
                    $rw->skip();
                } while ($rw->peek() !== null);

                $rw->add(' WHERE ' . $subquery);

                return $rw->getResult();
            }

            $this->translateExpression($rw);
        }

        // No WHERE/ORDER/LIMIT found — append subquery
        $rw->add(' WHERE ' . $subquery);

        return $rw->getResult();
    }

    // ── DDL handlers ──

    /**
     * Translate CREATE TABLE using AST CreateDefinition[] directly.
     *
     * Builds SQLite DDL from the parsed field definitions, type mapping,
     * and constraint rewriting. Does not use token rewriting for CREATE TABLE —
     * SQL is constructed entirely from AST components.
     *
     * For CREATE INDEX / CREATE VIEW / other CREATE statements, falls back to
     * token rewriting.
     */
    /**
     * @return list<string>
     */
    private function translateCreate(CreateStatement $stmt, Parser $parser): array
    {
        // CREATE INDEX, CREATE VIEW, etc. — token rewrite fallback
        if (!$this->isCreateTable($stmt)) {
            return [$this->rewriteTokens($parser)];
        }

        return $this->buildCreateTable($stmt);
    }

    private function isCreateTable(CreateStatement $stmt): bool
    {
        return \is_array($stmt->fields) && $stmt->fields !== [];
    }

    /**
     * Build CREATE TABLE SQL directly from AST fields.
     * Returns multiple statements if ON UPDATE CURRENT_TIMESTAMP triggers are needed.
     *
     * @return list<string>
     */
    private function buildCreateTable(CreateStatement $stmt): array
    {
        $rawTableName = $stmt->name->table ?? '';
        $tableName = $this->quoteId($rawTableName);
        $ifNotExists = ($stmt->options?->has('IF NOT EXISTS')) ? 'IF NOT EXISTS ' : '';

        // Scan AST to find PRIMARY KEY column and AUTO_INCREMENT column
        $pkColumnName = null;
        $autoIncrementCol = null;

        foreach ($stmt->fields as $field) {
            if ($field->key !== null && $field->key->type === 'PRIMARY KEY' && isset($field->key->columns[0]['name'])) {
                $pkColumnName = $field->key->columns[0]['name'];
            }
            if ($field->type !== null && $field->options?->has('PRIMARY KEY')) {
                $pkColumnName = $field->name;
            }
            if ($field->type !== null && $field->options?->has('AUTO_INCREMENT')) {
                $autoIncrementCol = $field->name;
            }
        }

        $mergePk = $autoIncrementCol !== null && $autoIncrementCol === $pkColumnName;
        $parts = [];
        $triggers = [];

        foreach ($stmt->fields as $field) {
            if ($field->type !== null) {
                $parts[] = $this->buildColumnDef($field, $mergePk ? $pkColumnName : null);

                // Detect ON UPDATE CURRENT_TIMESTAMP → generate trigger
                $optionsBuild = strtoupper($field->options?->build() ?? '');
                if (str_contains($optionsBuild, 'ON UPDATE CURRENT_TIMESTAMP')) {
                    $triggers[] = $this->buildOnUpdateTrigger($rawTableName, $field->name ?? '');
                }
            } elseif ($field->key !== null) {
                if ($mergePk && $field->key->type === 'PRIMARY KEY') {
                    continue;
                }

                $parts[] = $this->buildKeyDef($field->key);
            }
        }

        $results = [\sprintf("CREATE TABLE %s%s (%s)", $ifNotExists, $tableName, implode(', ', $parts))];

        return [...$results, ...$triggers];
    }

    /**
     * Build a CREATE TRIGGER for ON UPDATE CURRENT_TIMESTAMP emulation.
     */
    private function buildOnUpdateTrigger(string $table, string $column): string
    {
        $triggerName = $this->quoteId("__{$table}_{$column}_on_update__");
        $quotedTable = $this->quoteId($table);
        $quotedColumn = $this->quoteId($column);

        return \sprintf(
            'CREATE TRIGGER %s AFTER UPDATE ON %s FOR EACH ROW BEGIN UPDATE %s SET %s = datetime(\'now\') WHERE rowid = NEW.rowid; END',
            $triggerName,
            $quotedTable,
            $quotedTable,
            $quotedColumn,
        );
    }

    /**
     * Build a column definition from AST CreateDefinition.
     *
     * @param string|null $mergedPkColumn Column name that should get PRIMARY KEY AUTOINCREMENT
     */
    private function buildColumnDef(\PhpMyAdmin\SqlParser\Components\CreateDefinition $field, ?string $mergedPkColumn): string
    {
        $name = $this->quoteId($field->name ?? '');
        $type = $this->mapSqliteType($field->type !== null ? $field->type->name : '');

        $clauses = [$name, $type];

        // NOT NULL
        if ($field->options?->has('NOT NULL')) {
            $clauses[] = 'NOT NULL';
        }

        // PRIMARY KEY AUTOINCREMENT handling:
        // - mergedPkColumn set: PK was separate constraint, merge into column
        // - Inline PRIMARY KEY + AUTO_INCREMENT: combine both
        $hasPk = ($mergedPkColumn !== null && $field->name === $mergedPkColumn)
            || $field->options?->has('PRIMARY KEY');
        $hasAi = $field->options?->has('AUTO_INCREMENT') ?? false;

        if ($hasPk && $hasAi) {
            $clauses[] = 'PRIMARY KEY AUTOINCREMENT';
        } elseif ($hasPk) {
            $clauses[] = 'PRIMARY KEY';
        } elseif ($hasAi) {
            $clauses[] = 'AUTOINCREMENT';
        }

        // DEFAULT
        $defaultExpr = $field->options?->get('DEFAULT', true);
        if ($defaultExpr instanceof \PhpMyAdmin\SqlParser\Components\Expression && $defaultExpr->expr !== null && $defaultExpr->expr !== '') {
            $clauses[] = 'DEFAULT ' . $defaultExpr->expr;
        }

        return implode(' ', $clauses);
    }

    /**
     * Build a key/constraint definition from AST Key.
     */
    private function buildKeyDef(\PhpMyAdmin\SqlParser\Components\Key $key): string
    {
        $columns = [];

        foreach ($key->columns as $col) {
            if (isset($col['name'])) {
                $columns[] = $this->quoteId($col['name']);
            }
        }

        $colList = implode(', ', $columns);

        return match ($key->type) {
            'PRIMARY KEY' => 'PRIMARY KEY (' . $colList . ')',
            'UNIQUE KEY' => 'UNIQUE (' . $colList . ')',
            default => 'KEY ' . ($key->name !== null ? $this->quoteId($key->name) . ' ' : '') . '(' . $colList . ')',
        };
    }

    private function mapSqliteType(string $mysqlType): string
    {
        return match (strtoupper($mysqlType)) {
            'BIGINT', 'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BOOLEAN' => 'INTEGER',
            'VARCHAR', 'CHAR', 'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT', 'ENUM', 'SET' => 'TEXT',
            'DATETIME', 'TIMESTAMP', 'DATE', 'TIME' => 'TEXT',
            'JSON' => 'TEXT',
            'FLOAT', 'DOUBLE', 'DECIMAL', 'NUMERIC', 'REAL' => 'REAL',
            'BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB', 'VARBINARY', 'BINARY' => 'BLOB',
            default => 'TEXT',
        };
    }

    private function quoteId(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * @return list<string>
     */
    private function translateAlter(AlterStatement $stmt, Parser $parser): array
    {
        $rw = $this->createRewriter($parser);
        $rw->consumeAll();
        $sql = $rw->getResult();

        // ADD COLUMN (but not ADD INDEX, ADD KEY, etc.)
        if (preg_match('/\bADD\s+(?!INDEX\b|KEY\b|UNIQUE\b|PRIMARY\b|CONSTRAINT\b)/i', $sql)) {
            return [$this->transformDdlTypes($sql)];
        }

        // RENAME
        if (preg_match('/\bRENAME\b/i', $sql)) {
            return [$sql];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function translateTruncate(TruncateStatement $stmt): array
    {
        if ($stmt->table === null) {
            return [];
        }

        $name = $stmt->table->table;

        return ['DELETE FROM "' . str_replace('"', '""', $name) . '"'];
    }

    // ── Expression translation ──

    /**
     * Translate the next token from the rewriter, handling function and operator transforms.
     *
     * This is the core expression translator called by all DML handlers.
     * String literals pass through untouched (consume as-is).
     */
    private function translateExpression(QueryRewriter $rw): void
    {
        $token = $rw->peek();
        if ($token === null) {
            return;
        }

        // String literals → pass through unchanged (core safety guarantee)
        if ($token->type === TokenType::String) {
            $rw->consume();

            return;
        }

        if ($token->type !== TokenType::Keyword || $token->keyword === null) {
            // Non-keyword tokens (operators, numbers, identifiers, symbols) → consume
            $rw->consume();

            return;
        }

        $kw = $token->keyword;

        // ── Composed keywords ──
        if ($kw === 'FOR UPDATE') {
            $rw->skip();

            return;
        }

        // ── Zero-arg functions: NOW() → datetime('now') ──
        if (isset(self::ZERO_ARG_MAP[$kw])
            && $rw->peekNth(2)?->token === '('
            && $rw->peekNth(3)?->token === ')') {
            $rw->skip(); // function name
            $rw->skip(); // (
            $rw->skip(); // )
            $rw->add(self::ZERO_ARG_MAP[$kw]);

            return;
        }

        // ── Standalone keyword replacements ──
        if (isset(self::KEYWORD_MAP[$kw])
            && $rw->peekNth(2)?->token !== '(') {
            $rw->skip();
            $rw->add(self::KEYWORD_MAP[$kw]);

            return;
        }

        // ── Function renames ──
        if (isset(self::RENAME_MAP[$kw])
            && $rw->peekNth(2)?->token === '(') {
            $rw->skip();
            $rw->add(self::RENAME_MAP[$kw]);

            return;
        }

        // ── Structural transforms ──
        if ($rw->peekNth(2)?->token === '(') {
            if ($this->tryStructuralTransform($rw, $kw)) {
                return;
            }
        }

        // ── LIMIT ──
        if ($kw === 'LIMIT') {
            $this->rewriteLimitFromTokens($rw);

            return;
        }

        // ── CAST(x AS SIGNED) → CAST(x AS INTEGER) ──
        if ($kw === 'SIGNED') {
            $rw->skip();
            $rw->add('INTEGER');

            return;
        }

        // Default: consume
        $rw->consume();
    }

    // ── Structural transforms ──

    private function tryStructuralTransform(QueryRewriter $rw, string $kw): bool
    {
        return match ($kw) {
            'DATE_ADD' => $this->transformDateAddSub($rw, '+'),
            'DATE_SUB' => $this->transformDateAddSub($rw, '-'),
            'DATE_FORMAT' => $this->transformDateFormat($rw),
            'FROM_UNIXTIME' => $this->transformFromUnixtime($rw),
            'LEFT' => $this->transformLeftFunc($rw),
            'RIGHT' => $this->transformRightFunc($rw),
            'IF' => $this->transformIfFunc($rw),
            'CONCAT' => $this->transformConcat($rw),
            'CONCAT_WS' => $this->transformConcatWs($rw),
            'DATEDIFF' => $this->transformDatediff($rw),
            'MONTH' => $this->transformDateExtract($rw, '%m'),
            'YEAR' => $this->transformDateExtract($rw, '%Y'),
            'DAY', 'DAYOFMONTH' => $this->transformDateExtract($rw, '%d'),
            'HOUR' => $this->transformDateExtract($rw, '%H'),
            'MINUTE' => $this->transformDateExtract($rw, '%M'),
            'SECOND' => $this->transformDateExtract($rw, '%S'),
            'DAYOFWEEK' => $this->transformDayOfWeek($rw),
            'DAYOFYEAR' => $this->transformDateExtract($rw, '%j'),
            'WEEKDAY' => $this->transformWeekday($rw),
            'GREATEST' => $this->transformGreatestLeast($rw, 'MAX'),
            'LEAST' => $this->transformGreatestLeast($rw, 'MIN'),
            default => false,
        };
    }

    /**
     * DATE_ADD(d, INTERVAL n unit) → datetime(d, '+n unit')
     * DATE_SUB(d, INTERVAL n unit) → datetime(d, '-n unit')
     */
    private function transformDateAddSub(QueryRewriter $rw, string $sign): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        // Parse INTERVAL n unit from second argument
        $intervalParts = $this->parseIntervalArg($args[1]);
        if ($intervalParts === null) {
            return false;
        }

        [$number, $unit] = $intervalParts;
        $dateExpr = $this->transformArgExpression($args[0]);

        $rw->add(\sprintf("datetime(%s, '%s%s %s')", $dateExpr, $sign, $number, strtolower($unit)));

        return true;
    }

    /**
     * DATE_FORMAT(d, 'format') → strftime('converted_format', d)
     */
    private function transformDateFormat(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $dateExpr = $this->transformArgExpression($args[0]);
        $formatToken = $this->findStringToken($args[1]);
        if ($formatToken === null) {
            return false;
        }

        $format = str_replace(
            ['%Y', '%m', '%d', '%H', '%i', '%s', '%j', '%W'],
            ['%Y', '%m', '%d', '%H', '%M', '%S', '%j', '%w'],
            (string) $formatToken->value,
        );

        $rw->add(\sprintf("strftime('%s', %s)", $format, $dateExpr));

        return true;
    }

    /**
     * FROM_UNIXTIME(t) → datetime(t, 'unixepoch')
     */
    private function transformFromUnixtime(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf("datetime(%s, 'unixepoch')", $expr));

        return true;
    }

    /**
     * LEFT(s, n) → SUBSTR(s, 1, n)
     */
    private function transformLeftFunc(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $strExpr = $this->transformArgExpression($args[0]);
        $lenExpr = $this->transformArgExpression($args[1]);
        $rw->add(\sprintf('SUBSTR(%s, 1, %s)', $strExpr, $lenExpr));

        return true;
    }

    /**
     * IF(cond, t, f) → CASE WHEN cond THEN t ELSE f END
     */
    private function transformIfFunc(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 3) {
            return false;
        }

        $cond = $this->transformArgExpression($args[0]);
        $trueVal = $this->transformArgExpression($args[1]);
        $falseVal = $this->transformArgExpression($args[2]);
        $rw->add(\sprintf('CASE WHEN %s THEN %s ELSE %s END', $cond, $trueVal, $falseVal));

        return true;
    }

    /**
     * CONCAT(a, b, c) → a || b || c
     */
    private function transformConcat(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || $args === []) {
            return false;
        }

        $parts = [];
        foreach ($args as $arg) {
            $parts[] = $this->transformArgExpression($arg);
        }

        $rw->add(implode(' || ', $parts));

        return true;
    }

    /**
     * CONCAT_WS(sep, a, b) → a || sep || b
     */
    private function transformConcatWs(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $sep = $this->transformArgExpression($args[0]);
        $parts = [];
        for ($i = 1, $c = \count($args); $i < $c; $i++) {
            $parts[] = $this->transformArgExpression($args[$i]);
        }

        $rw->add(implode(' || ' . $sep . ' || ', $parts));

        return true;
    }

    /**
     * RIGHT(s, n) → SUBSTR(s, -n)
     */
    private function transformRightFunc(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $strExpr = $this->transformArgExpression($args[0]);
        $lenExpr = $this->transformArgExpression($args[1]);
        $rw->add(\sprintf('SUBSTR(%s, -%s)', $strExpr, $lenExpr));

        return true;
    }

    /**
     * DATEDIFF(d1, d2) → CAST(julianday(d1) - julianday(d2) AS INTEGER)
     */
    private function transformDatediff(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $d1 = $this->transformArgExpression($args[0]);
        $d2 = $this->transformArgExpression($args[1]);
        $rw->add(\sprintf('CAST(julianday(%s) - julianday(%s) AS INTEGER)', $d1, $d2));

        return true;
    }

    /**
     * MONTH(d) → CAST(strftime('%m', d) AS INTEGER)
     * YEAR(d)  → CAST(strftime('%Y', d) AS INTEGER)
     * etc.
     */
    private function transformDateExtract(QueryRewriter $rw, string $format): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf("CAST(strftime('%s', %s) AS INTEGER)", $format, $expr));

        return true;
    }

    /**
     * DAYOFWEEK(d) → CAST(strftime('%w', d) AS INTEGER) + 1
     * MySQL DAYOFWEEK: 1=Sunday, 7=Saturday; SQLite %w: 0=Sunday, 6=Saturday
     */
    private function transformDayOfWeek(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf("(CAST(strftime('%%w', %s) AS INTEGER) + 1)", $expr));

        return true;
    }

    /**
     * WEEKDAY(d) → (CAST(strftime('%w', d) AS INTEGER) + 6) % 7
     * MySQL WEEKDAY: 0=Monday, 6=Sunday; SQLite %w: 0=Sunday, 6=Saturday
     */
    private function transformWeekday(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf("((CAST(strftime('%%w', %s) AS INTEGER) + 6) %% 7)", $expr));

        return true;
    }

    /**
     * GREATEST(a, b) → MAX(a, b)
     * LEAST(a, b) → MIN(a, b)
     */
    private function transformGreatestLeast(QueryRewriter $rw, string $func): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || $args === []) {
            return false;
        }

        $parts = [];
        foreach ($args as $arg) {
            $parts[] = $this->transformArgExpression($arg);
        }

        $rw->add(\sprintf('%s(%s)', $func, implode(', ', $parts)));

        return true;
    }

    // ── LIMIT rewriting ──

    /**
     * Rewrite LIMIT using AST offset/rowCount values.
     *
     * @param int|string $offset
     * @param int|string $rowCount
     */
    private function rewriteLimit(QueryRewriter $rw, int|string $offset, int|string $rowCount): void
    {
        // Skip all LIMIT-related tokens
        $rw->skip(); // LIMIT keyword

        // Skip number
        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Number) {
            $rw->skip();
        }

        // Check for comma (offset, count syntax)
        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Operator && $next->token === ',') {
            $rw->skip(); // comma
            $next = $rw->peek();
            if ($next !== null && $next->type === TokenType::Number) {
                $rw->skip(); // second number
            }
        }

        // Check for OFFSET keyword (LIMIT N OFFSET M syntax)
        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'OFFSET') {
            $rw->skip(); // OFFSET
            $next = $rw->peek();
            if ($next !== null && $next->type === TokenType::Number) {
                $rw->skip(); // offset number
            }
        }

        $offsetVal = (int) $offset;
        if ($offsetVal === 0) {
            $rw->add('LIMIT ' . $rowCount);
        } else {
            $rw->add('LIMIT ' . $rowCount . ' OFFSET ' . $offset);
        }
    }

    /**
     * Rewrite LIMIT by reading tokens directly (when AST limit info is unavailable).
     */
    private function rewriteLimitFromTokens(QueryRewriter $rw): void
    {
        $rw->skip(); // LIMIT keyword

        $first = $rw->peek();
        if ($first === null || $first->type !== TokenType::Number) {
            $rw->add('LIMIT');

            return;
        }

        $firstNum = $first->token;
        $rw->skip(); // first number

        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Operator && $next->token === ',') {
            $rw->skip(); // comma
            $second = $rw->peek();
            if ($second !== null && $second->type === TokenType::Number) {
                $secondNum = $second->token;
                $rw->skip(); // second number

                if ($firstNum === '0') {
                    $rw->add('LIMIT ' . $secondNum);
                } else {
                    $rw->add('LIMIT ' . $secondNum . ' OFFSET ' . $firstNum);
                }

                return;
            }
        }

        $rw->add('LIMIT ' . $firstNum);
    }

    // ── DDL helpers ──

    /**
     * Apply DDL type transformations via regex (for ALTER TABLE ADD COLUMN).
     */
    private function transformDdlTypes(string $sql): string
    {
        $typeMap = [
            '/\b(?:TINY|SMALL|MEDIUM|BIG)?INT(?:EGER)?\s*\(\s*\d+\s*\)\s*(?:UNSIGNED\s*)?/i' => 'INTEGER ',
            '/\bINT\b\s*(?:UNSIGNED\s*)?/i' => 'INTEGER ',
            '/\bBIGINT\b\s*(?:UNSIGNED\s*)?/i' => 'INTEGER ',
            '/\bTINYINT\b\s*(?:UNSIGNED\s*)?/i' => 'INTEGER ',
            '/\bSMALLINT\b\s*(?:UNSIGNED\s*)?/i' => 'INTEGER ',
            '/\bMEDIUMINT\b\s*(?:UNSIGNED\s*)?/i' => 'INTEGER ',
            '/\bVARCHAR\s*\(\s*\d+\s*\)/i' => 'TEXT',
            '/\bCHAR\s*\(\s*\d+\s*\)/i' => 'TEXT',
            '/\b(?:TINY|MEDIUM|LONG)?TEXT\b/i' => 'TEXT',
            '/\bDATETIME\b/i' => 'TEXT',
            '/\bTIMESTAMP\b/i' => 'TEXT',
            '/\bJSON\b/i' => 'TEXT',
            '/\bENUM\s*\([^)]+\)/i' => 'TEXT',
            '/\bFLOAT\b(?:\s*\([^)]+\))?/i' => 'REAL',
            '/\bDOUBLE\b(?:\s*\([^)]+\))?/i' => 'REAL',
            '/\bDECIMAL\s*\([^)]+\)/i' => 'REAL',
            '/\b(?:TINY|MEDIUM|LONG)?BLOB\b/i' => 'BLOB',
            '/\bVARBINARY\s*\(\s*\d+\s*\)/i' => 'BLOB',
            '/\bBINARY\s*\(\s*\d+\s*\)/i' => 'BLOB',
        ];

        foreach ($typeMap as $pattern => $replacement) {
            $sql = (string) preg_replace($pattern, $replacement, $sql);
        }

        $sql = (string) preg_replace('/\bUNSIGNED\b/i', '', $sql);
        $sql = (string) preg_replace('/\bAUTO_INCREMENT\b/i', 'AUTOINCREMENT', $sql);

        return $sql;
    }

    // ── Argument extraction helpers ──

    /**
     * Extract function arguments from the rewriter.
     *
     * Skips the function name and opening paren, collects argument tokens split
     * by top-level commas, and skips the closing paren.
     *
     * @return list<list<Token>>|null Array of argument token lists, or null on failure
     */
    private function extractFunctionArgs(QueryRewriter $rw): ?array
    {
        $rw->skip(); // function name
        $openParen = $rw->peek();
        if ($openParen === null || $openParen->token !== '(') {
            return null;
        }
        $rw->skip(); // (

        $args = [[]];
        $depth = 1;
        $argIndex = 0;

        while ($rw->hasMore()) {
            $token = $rw->peek();
            if ($token === null) {
                break;
            }

            if ($token->type === TokenType::Operator && $token->token === '(') {
                $depth++;
            } elseif ($token->type === TokenType::Operator && $token->token === ')') {
                $depth--;
                if ($depth === 0) {
                    $rw->skip(); // closing )
                    break;
                }
            } elseif ($token->type === TokenType::Operator && $token->token === ',' && $depth === 1) {
                $rw->skip(); // comma
                $argIndex++;
                $args[$argIndex] = [];
                continue;
            }

            $args[$argIndex][] = $rw->skip();
        }

        return $args;
    }

    /**
     * Transform an argument token list into a SQL expression string.
     *
     * Applies all expression-level transforms (function renames, etc.) to the
     * argument tokens by running them through a fresh QueryRewriter.
     *
     * @param list<Token> $tokens
     */
    private function transformArgExpression(array $tokens): string
    {
        // Filter out whitespace-only args
        $semantic = array_filter($tokens, fn(Token $t) => !$this->isSemanticVoid($t));
        if ($semantic === []) {
            return '';
        }

        // Build a mini rewriter for the argument tokens
        $rw = new QueryRewriter($tokens, \count($tokens));

        while ($rw->hasMore()) {
            $this->translateExpression($rw);
        }

        return trim($rw->getResult());
    }

    /**
     * Find the first string literal token in a token list.
     *
     * @param list<Token> $tokens
     */
    private function findStringToken(array $tokens): ?Token
    {
        foreach ($tokens as $token) {
            if ($token->type === TokenType::String) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Parse INTERVAL n unit from argument tokens.
     *
     * @param list<Token> $tokens
     * @return array{string, string}|null [number, unit]
     */
    private function parseIntervalArg(array $tokens): ?array
    {
        $number = null;
        $unit = null;

        foreach ($tokens as $token) {
            if ($this->isSemanticVoid($token)) {
                continue;
            }
            if ($token->type === TokenType::Keyword && $token->keyword === 'INTERVAL') {
                continue;
            }
            if ($token->type === TokenType::Number && $number === null) {
                $number = $token->token;
                continue;
            }
            if ($token->type === TokenType::Keyword && $number !== null) {
                $unit = $token->token;
                break;
            }
        }

        if ($number === null || $unit === null) {
            return null;
        }

        return [$number, $unit];
    }

    private function isSemanticVoid(Token $token): bool
    {
        return $token->type === TokenType::Whitespace
            || $token->type === TokenType::Comment
            || $token->type === TokenType::Delimiter;
    }

    // ── Generic token rewrite ──

    /**
     * Rewrite all tokens with expression-level transforms.
     * Used as fallback for UPDATE, DELETE, and unrecognized statements.
     */
    private function rewriteTokens(Parser $parser): string
    {
        $rw = $this->createRewriter($parser);

        while ($rw->hasMore()) {
            $token = $rw->peek();
            if ($token === null) {
                break;
            }

            // LIMIT handling
            if ($token->type === TokenType::Keyword && $token->keyword === 'LIMIT') {
                $this->rewriteLimitFromTokens($rw);
                continue;
            }

            $this->translateExpression($rw);
        }

        return $rw->getResult();
    }

    private function createRewriter(Parser $parser): QueryRewriter
    {
        return new QueryRewriter($parser->list->tokens, $parser->list->count);
    }

    // ── Meta commands ──

    /**
     * @return list<string>|null
     */
    private function translateMetaCommand(string $sql): ?array
    {
        if (preg_match('/^\s*START\s+TRANSACTION\b/i', $sql)) {
            return ['BEGIN'];
        }

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
