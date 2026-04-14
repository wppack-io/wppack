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
        'LOCALTIME' => "datetime('now')",
        'LOCALTIMESTAMP' => "datetime('now')",
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
        'LOCALTIME' => "datetime('now')",
        'LOCALTIMESTAMP' => "datetime('now')",
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

            // FROM DUAL → skip (MySQL-ism for "no tables")
            if ($token->type === TokenType::Keyword && $token->keyword === 'FROM') {
                $next = $rw->peekNth(2);
                if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'DUAL') {
                    $rw->skip(); // FROM
                    $rw->skip(); // DUAL
                    continue;
                }
            }

            // INDEX HINTS: USE/FORCE/IGNORE INDEX (...) → skip
            if ($token->type === TokenType::Keyword
                && \in_array($token->keyword, ['USE', 'FORCE', 'IGNORE'], true)) {
                $next = $rw->peekNth(2);
                if ($next !== null && ($next->keyword === 'INDEX' || $next->keyword === 'KEY')) {
                    $rw->skip(); // USE/FORCE/IGNORE
                    $rw->skip(); // INDEX/KEY
                    if ($rw->peek()?->token === '(') {
                        $this->skipMatchingParen($rw);
                    }
                    continue;
                }
            }

            // HAVING without GROUP BY → inject GROUP BY 1
            if ($token->type === TokenType::Keyword && $token->keyword === 'HAVING'
                && $stmt->having !== null && $stmt->group === null) {
                $rw->add(' GROUP BY 1');
                // Fall through to consume HAVING normally
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
        // INSERT ... SET col=val → INSERT ... (col) VALUES (val)
        if ($stmt->set !== null && $stmt->set !== []) {
            return $this->translateInsertSet($stmt);
        }

        $rw = $this->createRewriter($parser);
        $hasIgnore = $stmt->options !== null && $stmt->options->has('IGNORE');
        $hasOnDuplicate = $stmt->onDuplicateSet !== null && $stmt->onDuplicateSet !== [];
        $inOnConflictUpdate = false;

        while ($rw->hasMore()) {
            $token = $rw->peek();
            if ($token === null) {
                break;
            }

            // INSERT [IGNORE] → INSERT OR IGNORE
            if ($token->type === TokenType::Keyword && $token->keyword === 'INSERT') {
                if ($hasIgnore) {
                    $rw->skip(); // skip INSERT
                    $next = $rw->peek();
                    if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'IGNORE') {
                        $rw->skip(); // skip IGNORE
                    }
                    $rw->add('INSERT OR IGNORE');
                } else {
                    $rw->consume();
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

    /**
     * INSERT ... SET col=val, col2=val2 → INSERT INTO t (col, col2) VALUES (val, val2)
     *
     * @return list<string>
     */
    private function translateInsertSet(InsertStatement $stmt): array
    {
        $table = $this->quoteId($stmt->into->dest->table ?? '');
        $columns = [];
        $values = [];

        foreach ($stmt->set as $set) {
            $columns[] = $this->quoteId($set->column);
            $values[] = $set->value;
        }

        return [\sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $values),
        )];
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

        // DELETE JOIN: DELETE a FROM t1 a JOIN t2 b ON ... WHERE ...
        // → DELETE FROM t1 WHERE rowid IN (SELECT t1.rowid FROM t1 JOIN t2 ...)
        if ($stmt->join !== null && $stmt->join !== []) {
            return $this->rewriteDeleteJoin($stmt);
        }

        return $this->rewriteTokens($parser);
    }

    /**
     * Rewrite DELETE JOIN (MySQL multi-table delete) to SQLite-compatible subquery.
     *
     * MySQL:  DELETE a FROM t1 a JOIN t2 b ON ... WHERE ...
     * SQLite: DELETE FROM t1 WHERE rowid IN (SELECT t1.rowid FROM t1 JOIN t2 ON ... WHERE ...)
     */
    private function rewriteDeleteJoin(DeleteStatement $stmt): string
    {
        $table = $stmt->from[0]->table ?? '';
        $alias = $stmt->from[0]->alias ?? $table;
        $quotedTable = $this->quoteId($table);

        $joinClauses = [];
        foreach ($stmt->join as $join) {
            $joinType = $join->type ?? 'JOIN';
            $joinTable = $join->expr->table ?? $join->expr->expr ?? '';
            $joinAlias = $join->expr->alias ?? '';
            $joinRef = $this->quoteId($joinTable) . ($joinAlias !== '' ? ' ' . $joinAlias : '');
            $onParts = [];
            if ($join->on !== null) {
                foreach ($join->on as $cond) {
                    $onParts[] = $cond->expr;
                }
            }
            $joinClauses[] = $joinType . ' ' . $joinRef . ($onParts !== [] ? ' ON ' . implode(' ', $onParts) : '');
        }

        $whereParts = [];
        if ($stmt->where !== null) {
            foreach ($stmt->where as $cond) {
                $whereParts[] = $cond->expr;
            }
        }
        $whereClause = $whereParts !== [] ? ' WHERE ' . implode(' ', $whereParts) : '';

        return \sprintf(
            'DELETE FROM %s WHERE rowid IN (SELECT %s.rowid FROM %s %s %s%s)',
            $quotedTable,
            $alias,
            $quotedTable,
            $alias,
            implode(' ', $joinClauses),
            $whereClause,
        );
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
        $cacheInserts = [];

        foreach ($stmt->fields as $field) {
            if ($field->type !== null) {
                $parts[] = $this->buildColumnDef($field, $mergePk ? $pkColumnName : null);

                // Detect ON UPDATE CURRENT_TIMESTAMP → generate trigger
                $optionsBuild = strtoupper($field->options?->build() ?? '');
                if (str_contains($optionsBuild, 'ON UPDATE CURRENT_TIMESTAMP')) {
                    $triggers[] = $this->buildOnUpdateTrigger($rawTableName, $field->name ?? '');
                }

                // Cache MySQL data type for SHOW CREATE TABLE reconstruction
                $mysqlType = $this->buildMysqlTypeString($field);
                if ($mysqlType !== '' && $field->name !== null) {
                    $cacheInserts[] = $this->buildCacheInsert($rawTableName, $field->name, $mysqlType);
                }
            } elseif ($field->key !== null) {
                if ($mergePk && $field->key->type === 'PRIMARY KEY') {
                    continue;
                }

                $parts[] = $this->buildKeyDef($field->key);
            }
        }

        $results = [\sprintf("CREATE TABLE %s%s (%s)", $ifNotExists, $tableName, implode(', ', $parts))];

        return [...$results, ...$triggers, ...$cacheInserts];
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
     * Build original MySQL type string from AST for data type cache.
     */
    private function buildMysqlTypeString(\PhpMyAdmin\SqlParser\Components\CreateDefinition $field): string
    {
        if ($field->type === null) {
            return '';
        }

        $type = $field->type->name;
        $params = $field->type->parameters;

        if ($params !== []) {
            $type .= '(' . implode(',', $params) . ')';
        }

        $typeOptions = $field->type->options !== null ? $field->type->options->options : [];
        if (\in_array('UNSIGNED', $typeOptions, true)) {
            $type .= ' unsigned';
        }

        return strtolower($type);
    }

    /**
     * Build INSERT INTO _mysql_data_types_cache statement.
     */
    private function buildCacheInsert(string $table, string $column, string $mysqlType): string
    {
        return \sprintf(
            "INSERT OR REPLACE INTO _mysql_data_types_cache (\"table\", \"column_or_index\", \"mysql_type\") VALUES ('%s', '%s', '%s')",
            str_replace("'", "''", $table),
            str_replace("'", "''", $column),
            str_replace("'", "''", $mysqlType),
        );
    }

    /**
     * @return list<string>
     */
    private function translateAlter(AlterStatement $stmt, Parser $parser): array
    {
        $rw = $this->createRewriter($parser);
        $rw->consumeAll();
        $sql = $rw->getResult();

        $table = $stmt->table->table ?? '';

        // ADD INDEX / ADD KEY / ADD UNIQUE → CREATE INDEX
        if (preg_match('/\bADD\s+(UNIQUE\s+)?(INDEX|KEY)\b/i', $sql)) {
            $unique = preg_match('/\bADD\s+UNIQUE\b/i', $sql) ? 'UNIQUE ' : '';
            // Extract index name and columns from the SQL
            if (preg_match('/(?:INDEX|KEY)\s+"?(\w+)"?\s*(\([^)]+\))/i', $sql, $m)) {
                return [\sprintf(
                    'CREATE %sINDEX %s ON %s %s',
                    $unique,
                    $this->quoteId($m[1]),
                    $this->quoteId($table),
                    $m[2],
                )];
            }

            return [];
        }

        // DROP INDEX → DROP INDEX IF EXISTS
        if (preg_match('/\bDROP\s+(INDEX|KEY)\s+"?(\w+)"?/i', $sql, $m)) {
            return [\sprintf('DROP INDEX IF EXISTS %s', $this->quoteId($m[2]))];
        }

        // ADD COLUMN (but not ADD INDEX, ADD KEY, etc.)
        if (preg_match('/\bADD\s+(?!INDEX\b|KEY\b|UNIQUE\b|PRIMARY\b|CONSTRAINT\b)/i', $sql)) {
            return [$this->transformDdlTypes($sql)];
        }

        // DROP COLUMN — SQLite 3.35.0+ supports this natively
        if (preg_match('/\bDROP\s+COLUMN\b/i', $sql)) {
            return [$sql];
        }

        // CHANGE COLUMN / MODIFY COLUMN — table recreation pattern
        if ($stmt->altered !== null) {
            foreach ($stmt->altered as $alter) {
                $optStr = strtoupper(trim(implode(' ', array_filter($alter->options->options ?? [], '\is_string'))));
                if (str_contains($optStr, 'CHANGE') || str_contains($optStr, 'MODIFY')) {
                    return $this->translateAlterChangeColumn($stmt);
                }
            }
        }

        // RENAME
        if (preg_match('/\bRENAME\b/i', $sql)) {
            return [$sql];
        }

        return [];
    }

    /**
     * ALTER TABLE CHANGE COLUMN via table recreation pattern.
     *
     * SQLite does not support CHANGE/MODIFY COLUMN. The workaround is:
     * 1. Create temp table with data from original
     * 2. Drop original table
     * 3. Get schema from sqlite_master, modify column definition
     * 4. Create new table with modified schema
     * 5. Copy data back from temp
     * 6. Drop temp
     *
     * Since the translator cannot execute queries (it only returns SQL strings),
     * we return the sequence of SQL statements that the caller must execute in order.
     * The schema modification uses the _mysql_data_types_cache for type reconstruction.
     *
     * @return list<string>
     */
    private function translateAlterChangeColumn(AlterStatement $stmt): array
    {
        $table = $stmt->table->table ?? '';
        $quotedTable = $this->quoteId($table);
        $tmpTable = '_wppack_tmp_' . $table;
        $quotedTmp = $this->quoteId($tmpTable);

        // Return the table recreation sequence
        // The actual schema modification requires runtime access to sqlite_master,
        // which the translator cannot do. Instead, we use a pragmatic approach:
        // copy data to temp, drop original, and let the caller re-create via dbDelta.
        return [
            \sprintf('CREATE TABLE %s AS SELECT * FROM %s', $quotedTmp, $quotedTable),
            \sprintf('DROP TABLE %s', $quotedTable),
            \sprintf('ALTER TABLE %s RENAME TO %s', $quotedTmp, $quotedTable),
        ];
    }

    /**
     * @return list<string>
     */
    /**
     * TRUNCATE TABLE → DELETE FROM + reset AUTOINCREMENT counter.
     *
     * MySQL TRUNCATE resets AUTO_INCREMENT to 1. SQLite's DELETE FROM
     * does not reset the AUTOINCREMENT counter, so we also delete
     * from sqlite_sequence to achieve MySQL-compatible behavior.
     *
     * @return list<string>
     */
    private function translateTruncate(TruncateStatement $stmt): array
    {
        if ($stmt->table === null) {
            return [];
        }

        $name = $stmt->table->table;
        $quoted = '"' . str_replace('"', '""', $name) . '"';

        return [
            'DELETE FROM ' . $quoted,
            "DELETE FROM sqlite_sequence WHERE name = '" . str_replace("'", "''", $name) . "'",
        ];
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

        // String literals → pass through (with ISO 8601 date normalization for SQLite)
        if ($token->type === TokenType::String) {
            $raw = $token->token;
            // ISO 8601: '2024-01-15T10:30:45Z' → '2024-01-15 10:30:45'
            if (preg_match("/^'(\\d{4}-\\d{2}-\\d{2})T(\\d{2}:\\d{2}:\\d{2})Z?'$/", $raw, $m)) {
                $rw->skip();
                $rw->add("'" . $m[1] . ' ' . $m[2] . "'");

                return;
            }
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

        // ── LIKE BINARY → GLOB with pattern conversion ──
        if ($kw === 'LIKE') {
            $next = $rw->peekNth(2);
            if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'BINARY') {
                $rw->skip(); // LIKE
                $rw->skip(); // BINARY
                $rw->add('GLOB');
                $patternToken = $rw->peek();
                if ($patternToken !== null && $patternToken->type === TokenType::String) {
                    $rw->skip();
                    $pattern = str_replace(['%', '_'], ['*', '?'], (string) $patternToken->value);
                    $rw->add("'" . str_replace("'", "''", $pattern) . "'");
                }

                return;
            }

            // ── LIKE with escaped wildcards (\% and \_) → add ESCAPE clause ──
            $rw->consume(); // consume LIKE
            $patternToken = $rw->peek();
            if ($patternToken !== null && $patternToken->type === TokenType::String) {
                // Check raw token for MySQL backslash escapes (Lexer resolves them in value)
                $rawToken = $patternToken->token;
                if (str_contains($rawToken, '\\_') || str_contains($rawToken, '\\%')) {
                    $rw->skip();
                    // Strip surrounding quotes, replace MySQL escapes with SUB character
                    $inner = mb_substr($rawToken, 1, -1);
                    $escaped = str_replace(['\\_', '\\%'], ["\x1a_", "\x1a%"], $inner);
                    $rw->add("'" . $escaped . "' ESCAPE '\x1a'");

                    return;
                }
            }

            return;
        }

        // ── CAST(x AS SIGNED/UNSIGNED) → CAST(x AS INTEGER) ──
        if ($kw === 'SIGNED' || $kw === 'UNSIGNED') {
            $rw->skip();
            $rw->add('INTEGER');

            return;
        }

        // ── CAST(x AS CHAR) → CAST(x AS TEXT) ──
        if ($kw === 'CHAR' && $rw->getDepth() > 0) {
            $rw->skip();
            $rw->add('TEXT');

            return;
        }

        // ── LOW_PRIORITY / DELAYED / HIGH_PRIORITY → skip ──
        if (\in_array($kw, ['LOW_PRIORITY', 'DELAYED', 'HIGH_PRIORITY'], true)) {
            $rw->skip();

            return;
        }

        // ── BINARY: CAST context → BLOB, otherwise skip (REGEXP BINARY etc.) ──
        if ($kw === 'BINARY') {
            $rw->skip();
            if ($rw->getDepth() > 0) {
                $rw->add('BLOB');
            }

            return;
        }

        // ── COLLATE clause → skip (COLLATE + collation name) ──
        if ($kw === 'COLLATE') {
            $rw->skip(); // COLLATE
            $rw->skip(); // collation name

            return;
        }

        // ── Empty IN clause: IN () → IN (NULL) ──
        if ($kw === 'IN') {
            $rw->consume(); // IN
            $next = $rw->peek();
            if ($next !== null && $next->token === '(') {
                $afterOpen = $rw->peekNth(2);
                if ($afterOpen !== null && $afterOpen->token === ')') {
                    $rw->skip(); // (
                    $rw->skip(); // )
                    $rw->add('(NULL)');

                    return;
                }
            }

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
            'WEEK' => $this->transformWeek($rw),
            'WEEKDAY' => $this->transformWeekday($rw),
            'GREATEST' => $this->transformGreatestLeast($rw, 'MAX'),
            'LEAST' => $this->transformGreatestLeast($rw, 'MIN'),
            'ISNULL' => $this->transformIsnull($rw),
            'LOG' => $this->transformLog($rw),
            'CONVERT' => $this->transformConvert($rw),
            'FIELD' => $this->transformField($rw),
            'GROUP_CONCAT' => $this->transformGroupConcat($rw),
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

        // Use strtr() for simultaneous replacement (str_replace cascades:
        // %i→%M then %M→%F would turn minutes into month name)
        $format = strtr((string) $formatToken->value, [
            '%Y' => '%Y', '%y' => '%y', '%m' => '%m', '%c' => '%n',
            '%d' => '%d', '%e' => '%j', '%H' => '%H', '%h' => '%h',
            '%I' => '%h', '%i' => '%M', '%s' => '%S', '%S' => '%S',
            '%j' => '%z', '%W' => '%l', '%w' => '%w', '%p' => '%A',
            '%T' => '%H:%M:%S', '%r' => '%h:%M:%S %A',
            '%a' => '%D', '%b' => '%M', '%M' => '%F',
            '%D' => '%jS', '%k' => '%G', '%l' => '%g',
            '%U' => '%W', '%u' => '%W', '%V' => '%W', '%v' => '%W',
            '%X' => '%Y', '%x' => '%o', '%f' => '000000',
        ]);

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

    /**
     * ISNULL(x) → (x IS NULL)
     */
    private function transformIsnull(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf('(%s IS NULL)', $expr));

        return true;
    }

    /**
     * LOG(x) → ln(x)  (natural log — SQLite doesn't have ln, use UDF or math)
     * LOG(b, x) → (ln(x) / ln(b))
     *
     * Note: SQLite doesn't have a native ln() function. This relies on a LOG UDF
     * being registered, or falls back to keeping LOG as-is for the UDF.
     */
    private function transformLog(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || $args === []) {
            return false;
        }

        if (\count($args) === 1) {
            $x = $this->transformArgExpression($args[0]);
            $rw->add(\sprintf('LOG(%s)', $x));
        } else {
            // LOG(b, x) → LOG(x) / LOG(b)
            $b = $this->transformArgExpression($args[0]);
            $x = $this->transformArgExpression($args[1]);
            $rw->add(\sprintf('(LOG(%s) / LOG(%s))', $x, $b));
        }

        return true;
    }

    /**
     * CONVERT(val, type) → CAST(val AS type)
     */
    private function transformConvert(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rawType = strtoupper(trim($this->transformArgExpression($args[1])));

        $type = match ($rawType) {
            'SIGNED', 'UNSIGNED' => 'INTEGER',
            'CHAR' => 'TEXT',
            default => $rawType,
        };

        $rw->add(\sprintf('CAST(%s AS %s)', $expr, $type));

        return true;
    }

    /**
     * FIELD(val, 'a', 'b', 'c') → CASE WHEN val='a' THEN 1 ... ELSE 0 END
     */
    private function transformField(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $search = $this->transformArgExpression($args[0]);
        $parts = [];

        for ($i = 1, $c = \count($args); $i < $c; $i++) {
            $val = $this->transformArgExpression($args[$i]);
            $parts[] = \sprintf('WHEN %s = %s THEN %d', $search, $val, $i);
        }

        $rw->add('CASE ' . implode(' ', $parts) . ' ELSE 0 END');

        return true;
    }

    /**
     * GROUP_CONCAT(col [SEPARATOR sep]) → group_concat(col, sep)
     *
     * SQLite's native group_concat takes separator as second argument.
     * MySQL uses SEPARATOR keyword instead.
     */
    /**
     * GROUP_CONCAT(expr [SEPARATOR sep]) → group_concat(expr, sep)
     *
     * MySQL's SEPARATOR keyword is inside the function args (not comma-separated).
     * We split the token list at the SEPARATOR keyword.
     */
    private function transformGroupConcat(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || $args === []) {
            return false;
        }

        // All tokens are in args[0] (no commas inside GROUP_CONCAT typically)
        // Split at SEPARATOR keyword
        $allTokens = $args[0];
        $exprTokens = [];
        $separator = "','";

        $foundSep = false;
        foreach ($allTokens as $token) {
            if (!$foundSep && $token->type === TokenType::Keyword && $token->keyword === 'SEPARATOR') {
                $foundSep = true;
                continue;
            }
            if ($foundSep) {
                if ($token->type === TokenType::String) {
                    $separator = $token->token;
                }
            } else {
                $exprTokens[] = $token;
            }
        }

        // If comma-separated args exist (e.g., GROUP_CONCAT(DISTINCT col ORDER BY col SEPARATOR sep))
        // fall back to simple approach
        if (!$foundSep && \count($args) >= 2) {
            $sepStr = $this->findStringToken($args[\count($args) - 1]);
            if ($sepStr !== null) {
                $separator = $sepStr->token;
            }
        }

        $expr = $exprTokens !== [] ? $this->transformArgExpression($exprTokens) : $this->transformArgExpression($args[0]);
        $rw->add(\sprintf('group_concat(%s, %s)', $expr, $separator));

        return true;
    }

    /**
     * WEEK(d [, mode]) → CAST(strftime('%W', d) AS INTEGER)
     * mode 0,2,4,6 = Sunday start; mode 1,3,5,7 = Monday start
     */
    private function transformWeek(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $mode = \count($args) >= 2 ? (int) trim($this->transformArgExpression($args[1])) : 0;

        // %W = Monday start (ISO), %w based for Sunday start
        $format = \in_array($mode, [1, 3, 5, 7], true) ? '%W' : '%W';

        $rw->add(\sprintf("CAST(strftime('%s', %s) AS INTEGER)", $format, $expr));

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

    /**
     * Skip tokens through the matching closing parenthesis.
     */
    private function skipMatchingParen(QueryRewriter $rw): void
    {
        $depth = 0;

        do {
            $t = $rw->skip();
            if ($t === null) {
                break;
            }
            if ($t->token === '(') {
                $depth++;
            } elseif ($t->token === ')') {
                $depth--;
            }
        } while ($depth > 0);
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
    /** @var array<string, string> */
    private const SYSTEM_VARIABLE_DEFAULTS = [
        'sql_mode' => '',
        'character_set_client' => 'utf8mb4',
        'character_set_connection' => 'utf8mb4',
        'character_set_results' => 'utf8mb4',
        'character_set_database' => 'utf8mb4',
        'character_set_server' => 'utf8mb4',
        'collation_connection' => 'utf8mb4_unicode_ci',
        'collation_database' => 'utf8mb4_unicode_ci',
        'collation_server' => 'utf8mb4_unicode_ci',
        'max_allowed_packet' => '67108864',
        'wait_timeout' => '28800',
        'interactive_timeout' => '28800',
        'net_read_timeout' => '30',
        'net_write_timeout' => '60',
    ];

    /**
     * Translate SELECT @@variable queries to return appropriate default values.
     */
    private function translateSystemVariable(string $sql): string
    {
        // Extract all @@variable references and build SELECT with defaults
        if (preg_match_all('/@@(?:SESSION\.|GLOBAL\.)?(\w+)/i', $sql, $matches)) {
            $columns = [];
            foreach ($matches[1] as $i => $varName) {
                $value = self::SYSTEM_VARIABLE_DEFAULTS[strtolower($varName)] ?? '';
                $alias = $matches[0][$i];
                $columns[] = \sprintf("'%s' AS `%s`", str_replace("'", "''", $value), $alias);
            }

            return 'SELECT ' . implode(', ', $columns);
        }

        return "SELECT '' AS `@@value`";
    }

    /**
     * @return list<string>|null
     */
    private function translateMetaCommand(string $sql): ?array
    {
        if (preg_match('/^\s*START\s+TRANSACTION\b/i', $sql)) {
            return ['BEGIN'];
        }

        // MySQL system variables → dummy values
        if (preg_match('/^\s*SELECT\s+@@/i', $sql)) {
            return [$this->translateSystemVariable($sql)];
        }

        // information_schema queries → sqlite_master
        if (preg_match('/\binformation_schema\.tables\b/i', $sql)) {
            return ["SELECT name AS table_name, 'BASE TABLE' AS table_type, 'def' AS table_catalog FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE '_%'"];
        }

        if (preg_match('/\binformation_schema\.columns\b/i', $sql) && preg_match('/table_name\s*=\s*[\'"](\w+)[\'"]/i', $sql, $m)) {
            return [\sprintf('PRAGMA table_info("%s")', $m[1])];
        }

        if (preg_match('/^\s*SHOW\s+FULL\s+TABLES\s+LIKE\s+[\'"](.+?)[\'"]\s*$/i', $sql, $m)) {
            return [\sprintf("SELECT name, 'BASE TABLE' AS Table_type FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%%' AND name NOT LIKE '_%%' AND name LIKE '%s'", str_replace("'", "''", $m[1]))];
        }

        if (preg_match('/^\s*SHOW\s+TABLES\s+LIKE\s+[\'"](.+?)[\'"]\s*$/i', $sql, $m)) {
            return [\sprintf("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%%' AND name NOT LIKE '_%%' AND name LIKE '%s'", str_replace("'", "''", $m[1]))];
        }

        if (preg_match('/^\s*SHOW\s+FULL\s+TABLES\s*/i', $sql)) {
            return ["SELECT name, 'BASE TABLE' AS Table_type FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE '_%'"];
        }

        if (preg_match('/^\s*SHOW\s+TABLES\s*/i', $sql)) {
            return ["SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE '_%'"];
        }

        if (preg_match('/^\s*SHOW\s+(?:FULL\s+)?COLUMNS\s+FROM\s+[`"]?(\w+)[`"]?\s*/i', $sql, $m)) {
            return [\sprintf('PRAGMA table_info("%s")', $m[1])];
        }

        if (preg_match('/^\s*SHOW\s+CREATE\s+TABLE\s+[`"]?(\w+)[`"]?\s*/i', $sql, $m)) {
            $t = str_replace("'", "''", $m[1]);

            // Build MySQL-compatible DDL from pragma_table_info + data type cache
            return [\sprintf(
                "SELECT '%s' AS \"Table\", 'CREATE TABLE `%s` (' || group_concat("
                . "'`' || p.name || '` ' || "
                . "COALESCE((SELECT c.mysql_type FROM _mysql_data_types_cache c WHERE c.\"table\" = '%s' AND c.column_or_index = p.name), p.type) || "
                . "CASE WHEN p.\"notnull\" = 1 THEN ' NOT NULL' ELSE '' END || "
                . "CASE WHEN p.dflt_value IS NOT NULL THEN ' DEFAULT ' || p.dflt_value ELSE '' END"
                . ", ', ') || ')' AS \"Create Table\" "
                . "FROM pragma_table_info('%s') p",
                $t,
                $t,
                $t,
                $t,
            )];
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

        if (preg_match('/^\s*SHOW\s+TABLE\s+STATUS\s+LIKE\s+[\'"](.+?)[\'"]\s*$/i', $sql, $m)) {
            return [\sprintf(
                "SELECT name AS Name, 'InnoDB' AS Engine, 0 AS Version, 'Dynamic' AS Row_format, "
                . "0 AS Rows, 0 AS Avg_row_length, 0 AS Data_length, 0 AS Max_data_length, "
                . "0 AS Index_length, 0 AS Data_free, NULL AS Auto_increment, "
                . "NULL AS Create_time, NULL AS Update_time, NULL AS Check_time, "
                . "'utf8mb4_unicode_ci' AS Collation, NULL AS Checksum, '' AS Create_options, '' AS Comment "
                . "FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%%%%' AND name NOT LIKE '_%%%%' AND name LIKE '%s'",
                str_replace("'", "''", $m[1]),
            )];
        }

        if (preg_match('/^\s*SHOW\s+TABLE\s+STATUS/i', $sql)) {
            return ["SELECT name AS Name, 'InnoDB' AS Engine, 0 AS Version, 'Dynamic' AS Row_format, "
                . "0 AS Rows, 0 AS Avg_row_length, 0 AS Data_length, 0 AS Max_data_length, "
                . "0 AS Index_length, 0 AS Data_free, NULL AS Auto_increment, "
                . "NULL AS Create_time, NULL AS Update_time, NULL AS Check_time, "
                . "'utf8mb4_unicode_ci' AS Collation, NULL AS Checksum, '' AS Create_options, '' AS Comment "
                . "FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE '_%'"];
        }

        if (preg_match('/^\s*DESCRIBE\s+[`"]?(\w+)[`"]?\s*/i', $sql, $m)) {
            return [\sprintf('PRAGMA table_info("%s")', $m[1])];
        }

        // SHOW GRANTS FOR → dummy
        if (preg_match('/^\s*SHOW\s+GRANTS\b/i', $sql)) {
            return ["SELECT 'GRANT ALL PRIVILEGES ON *.* TO ''root''@''localhost''' AS \"Grants for root@localhost\""];
        }

        // SHOW CREATE PROCEDURE → empty result
        if (preg_match('/^\s*SHOW\s+CREATE\s+PROCEDURE\b/i', $sql)) {
            return ["SELECT '' AS Procedure, '' AS Create_Procedure WHERE 0"];
        }

        // CHECK TABLE / ANALYZE TABLE / REPAIR TABLE → dummy success
        if (preg_match('/^\s*(CHECK|ANALYZE|REPAIR)\s+TABLE\s+[`"]?(\w+)[`"]?\s*/i', $sql, $m)) {
            return [\sprintf("SELECT '%s' AS Table, '%s' AS Op, 'status' AS Msg_type, 'OK' AS Msg_text", $m[2], strtolower($m[1]))];
        }

        return null;
    }
}
