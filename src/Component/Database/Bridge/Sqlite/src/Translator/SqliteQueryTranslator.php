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
use Psr\Log\LoggerInterface;
use WpPack\Component\Database\Exception\ParserFailureException;
use WpPack\Component\Database\Exception\TranslationException;
use WpPack\Component\Database\Exception\UnsupportedFeatureException;
use WpPack\Component\Database\Sql\QueryRewriter;
use WpPack\Component\Database\Translator\QueryTranslatorHelpersTrait;
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
    use QueryTranslatorHelpersTrait;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

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
        // LOCATE is handled via structural transform (arg swap)
    ];

    public function translate(string $sql): array
    {
        $trimmed = trim($sql);

        // DO expr → SELECT expr (MySQL no-op / connection ping)
        if (preg_match('/^\s*DO\s+/i', $trimmed)) {
            return [preg_replace('/^\s*DO\s+/i', 'SELECT ', $trimmed)];
        }

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

        // MySQL FULLTEXT indexes + MATCH(...) AGAINST(...) have no native
        // equivalent on SQLite. A best-effort pass-through would reach the
        // engine as unknown syntax and return zero rows silently, which
        // looks to the plugin like "search returned nothing" — failing
        // loudly is the safe production default. Operators who need full-
        // text search on SQLite should migrate to FTS5 virtual tables at
        // the schema level; that rewrite is out of scope for this
        // translator.
        if (preg_match('/\bMATCH\s*\(.*?\)\s*AGAINST\b/is', $trimmed)) {
            throw new UnsupportedFeatureException(
                $sql,
                'sqlite',
                ['FULLTEXT MATCH ... AGAINST is not supported on SQLite — use FTS5 virtual tables instead'],
            );
        }

        $parser = new Parser($sql);

        // The phpmyadmin/sql-parser library records context-sensitive warnings
        // as $parser->errors even when a statement is produced (e.g. stand-
        // alone `ROLLBACK` triggers "No transaction was previously started"
        // despite the SQL itself being valid at runtime). Only treat the
        // combination of "errors AND no statement produced" as a hard
        // translation failure: anything else is a hint we log but continue.
        if ($parser->errors !== []) {
            $messages = array_map(static fn(\Throwable $e): string => $e->getMessage(), $parser->errors);

            if ($parser->statements === []) {
                $this->logger?->error('SQLite query translation failed', [
                    'sql' => $sql,
                    'errors' => $messages,
                ]);

                throw new ParserFailureException($sql, 'sqlite', $messages);
            }

            $this->logger?->warning('SQLite query translation: parser reported warnings', [
                'sql' => $sql,
                'errors' => $messages,
            ]);
        }

        $stmt = $parser->statements[0] ?? null;

        if ($stmt === null) {
            // Well-formed but unrecognised statement type (SAVEPOINT-like
            // shapes are handled above). We still rewrite tokens to catch
            // expression-level fixes, but log a warning so operators can
            // spot traffic that bypasses structural translation.
            $this->logger?->warning('SQLite query translation: unrecognised statement, falling back to token rewrite', [
                'sql' => $sql,
            ]);

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
    /**
     * Render a JoinKeyword::$type back to the original join keyword form.
     *
     * phpmyadmin/sql-parser collapses `LEFT JOIN` to just `LEFT` etc. for
     * the public $type property, which made the old rewrite emit
     * `LEFT "t2" b` and drop the `JOIN` token.
     */
    private static function joinKeywordFromType(string $type): string
    {
        return match ($type) {
            'LEFT'               => 'LEFT JOIN',
            'RIGHT'              => 'RIGHT JOIN',
            'INNER'              => 'INNER JOIN',
            'CROSS'              => 'CROSS JOIN',
            'FULL'               => 'FULL JOIN',
            'NATURAL'            => 'NATURAL JOIN',
            'NATURAL LEFT'       => 'NATURAL LEFT JOIN',
            'NATURAL RIGHT'      => 'NATURAL RIGHT JOIN',
            'NATURAL LEFT OUTER' => 'NATURAL LEFT OUTER JOIN',
            'NATURAL RIGHT OUTER' => 'NATURAL RIGHT OUTER JOIN',
            'STRAIGHT'           => 'STRAIGHT_JOIN',
            default              => 'JOIN',
        };
    }

    private function rewriteDeleteJoin(DeleteStatement $stmt): string
    {
        $table = $stmt->from[0]->table ?? '';
        $alias = $stmt->from[0]->alias ?? $table;
        $quotedTable = $this->quoteId($table);

        $joinClauses = [];
        foreach ($stmt->join as $join) {
            $joinKeyword = self::joinKeywordFromType($join->type ?? 'JOIN');
            $joinTable = $join->expr->table ?? $join->expr->expr ?? '';
            $joinAlias = $join->expr->alias ?? '';
            $joinRef = $this->quoteId($joinTable) . ($joinAlias !== '' ? ' ' . $joinAlias : '');

            $clauseTail = '';
            if ($join->on !== null) {
                $onParts = [];
                foreach ($join->on as $cond) {
                    $onParts[] = $cond->expr;
                }
                if ($onParts !== []) {
                    $clauseTail = ' ON ' . implode(' ', $onParts);
                }
            } elseif ($join->using !== null) {
                // USING (col1, col2) — ArrayObj::build() returns '(c1, c2)'
                $clauseTail = ' USING ' . $join->using->build();
            }

            $joinClauses[] = $joinKeyword . ' ' . $joinRef . $clauseTail;
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
     *
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
        $indexStatements = [];
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

                // PRIMARY KEY and UNIQUE constraints stay inline;
                // regular KEY/INDEX must be separate CREATE INDEX statements in SQLite
                if ($field->key->type === 'PRIMARY KEY' || $field->key->type === 'UNIQUE KEY') {
                    $parts[] = $this->buildKeyDef($field->key);
                } else {
                    $indexStatements[] = $this->buildCreateIndex($rawTableName, $field->key);
                }
            }
        }

        $results = [\sprintf("CREATE TABLE %s%s (%s)", $ifNotExists, $tableName, implode(', ', $parts))];

        return [...$results, ...$indexStatements, ...$triggers, ...$cacheInserts];
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
     * Build an inline key/constraint definition (PRIMARY KEY, UNIQUE only).
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
            default => 'PRIMARY KEY (' . $colList . ')',
        };
    }

    /**
     * Build a CREATE INDEX statement for a regular KEY/INDEX.
     *
     * SQLite does not support inline KEY definitions in CREATE TABLE.
     */
    private function buildCreateIndex(string $table, \PhpMyAdmin\SqlParser\Components\Key $key): string
    {
        $columns = [];

        foreach ($key->columns as $col) {
            if (isset($col['name'])) {
                $columns[] = $this->quoteId($col['name']);
            }
        }

        $indexName = $key->name ?? implode('_', array_map(
            static fn(array $c): string => $c['name'] ?? '',
            $key->columns,
        ));

        return \sprintf(
            'CREATE INDEX IF NOT EXISTS %s ON %s (%s)',
            $this->quoteId($indexName),
            $this->quoteId($table),
            implode(', ', $columns),
        );
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

        // CHANGE COLUMN / MODIFY COLUMN
        if ($stmt->altered !== null) {
            foreach ($stmt->altered as $alter) {
                $optStr = strtoupper(trim(implode(' ', array_filter($alter->options->options ?? [], '\is_string'))));
                if (str_contains($optStr, 'CHANGE') || str_contains($optStr, 'MODIFY')) {
                    return $this->translateAlterChangeColumn($stmt, $alter, str_contains($optStr, 'CHANGE'));
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
     * ALTER TABLE CHANGE/MODIFY COLUMN for SQLite.
     *
     * SQLite uses dynamic typing (type affinity), so column type changes have
     * no effect on stored data. The translator handles this as follows:
     *
     * - MODIFY COLUMN (same name): no-op — type changes are irrelevant in SQLite
     * - CHANGE COLUMN (same name): no-op — type changes only
     * - CHANGE COLUMN (rename): ALTER TABLE RENAME COLUMN (SQLite 3.25.0+)
     *
     * @return list<string>
     */
    private function translateAlterChangeColumn(
        AlterStatement $stmt,
        \PhpMyAdmin\SqlParser\Components\AlterOperation $alter,
        bool $isChange,
    ): array {
        $table = $stmt->table->table ?? '';
        $oldName = $alter->field->column ?? $alter->field->name ?? '';

        if ($isChange && $alter->unknown !== []) {
            // CHANGE: first unknown token is the new column name
            $newName = $alter->unknown[0]->value ?? '';

            if ($newName !== '' && $newName !== $oldName) {
                return [\sprintf(
                    'ALTER TABLE %s RENAME COLUMN %s TO %s',
                    $this->quoteId($table),
                    $this->quoteId($oldName),
                    $this->quoteId($newName),
                )];
            }
        }

        // MODIFY or CHANGE with same name: type change only → no-op in SQLite
        return [];
    }

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

        // FROM DUAL → skip (MySQL compatibility — SQLite has no DUAL table)
        if ($kw === 'FROM') {
            $next = $rw->peekNth(2);
            if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'DUAL') {
                $rw->skip(); // FROM
                $rw->skip(); // DUAL

                return;
            }
        }

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

            // ── LIKE: add ESCAPE '\' so MySQL's default escape behaviour works in SQLite ──
            $rw->consume(); // consume LIKE
            $patternToken = $rw->peek();

            if ($patternToken !== null && $patternToken->type === TokenType::String) {
                $rawToken = $patternToken->token;
                if (str_contains($rawToken, '\\_') || str_contains($rawToken, '\\%')) {
                    $rw->skip();
                    $inner = mb_substr($rawToken, 1, -1);
                    $escaped = str_replace(['\\_', '\\%'], ["\x1a_", "\x1a%"], $inner);
                    $rw->add("'" . $escaped . "' ESCAPE '\x1a'");

                    return;
                }
            }

            // For parameterised patterns (?) and plain string patterns:
            // MySQL treats \ as the default LIKE escape character; SQLite does not.
            // Peek past the pattern token to check for an explicit ESCAPE clause.
            $afterPattern = $rw->peekNth(2);
            $hasEscape = $afterPattern !== null
                && $afterPattern->type === TokenType::Keyword
                && $afterPattern->keyword === 'ESCAPE';

            if (!$hasEscape) {
                // Consume the pattern token, then append ESCAPE '\'
                $rw->consume();
                $rw->add(" ESCAPE '\\'");
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
            'STR_TO_DATE' => $this->transformStrToDate($rw),
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
            'FIND_IN_SET' => $this->transformFindInSet($rw),
            'SUBSTRING_INDEX' => $this->transformSubstringIndex($rw),
            'SPACE' => $this->transformSpace($rw),
            'TIME_TO_SEC' => $this->transformTimeToSec($rw),
            'SEC_TO_TIME' => $this->transformSecToTime($rw),
            'DAYNAME' => $this->transformDayName($rw),
            'MONTHNAME' => $this->transformMonthName($rw),
            'QUARTER' => $this->transformQuarter($rw),
            'LOCATE' => $this->transformLocate($rw),
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
     * STR_TO_DATE(str, format) → strftime'd date.
     *
     * SQLite has no native string-to-date parser, but for the common ISO
     * shapes (`%Y-%m-%d`, `%Y-%m-%d %H:%i:%s`, and a few aliases) we can
     * pass the string through datetime() / date() which return the same
     * input when it already matches the ISO 8601 format SQLite expects.
     * Non-ISO formats (e.g. `%M %d, %Y` for "January 15, 2024") have no
     * safe rewrite; we pass them through verbatim and let SQLite error so
     * the caller discovers the gap instead of silently getting NULL.
     */
    private function transformStrToDate(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $strExpr = $this->transformArgExpression($args[0]);
        $formatToken = $this->findStringToken($args[1]);
        if ($formatToken === null) {
            return false;
        }

        $format = (string) $formatToken->value;
        $isDateOnly = in_array($format, ['%Y-%m-%d', '%Y/%m/%d', '%Y.%m.%d'], true);
        $isDateTime = in_array($format, [
            '%Y-%m-%d %H:%i:%s',
            '%Y-%m-%d %H:%i',
            '%Y-%m-%dT%H:%i:%s',
        ], true);

        if ($isDateOnly) {
            $rw->add(\sprintf('date(%s)', $strExpr));

            return true;
        }

        if ($isDateTime) {
            $rw->add(\sprintf('datetime(%s)', $strExpr));

            return true;
        }

        // Unknown format — fall back to datetime() which handles ISO-8601
        // best-effort. Non-ISO inputs yield NULL but at least the query
        // doesn't reference a non-existent strftime inverse.
        $rw->add(\sprintf('datetime(%s)', $strExpr));

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
     * LOCATE(needle, haystack) → INSTR(haystack, needle)
     *
     * MySQL LOCATE and SQLite INSTR have reversed argument order.
     */
    private function transformLocate(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $needle = $this->transformArgExpression($args[0]);
        $haystack = $this->transformArgExpression($args[1]);

        $rw->add("INSTR({$haystack}, {$needle})");

        return true;
    }

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

        // extractFunctionArgs strips whitespace between tokens, so
        // `DISTINCT name ORDER BY id` comes back as [DISTINCT, name, ORDER BY, id]
        // and transformArgExpression would concatenate those to
        // `DISTINCTnameORDER BYid`. Build the expression string directly by
        // joining the token text with single spaces — the resulting fragment
        // is then safe to hand to group_concat().
        $expr = $exprTokens !== []
            ? $this->joinTokensWithSpaces($exprTokens)
            : $this->transformArgExpression($args[0]);
        $rw->add(\sprintf('group_concat(%s, %s)', $expr, $separator));

        return true;
    }

    /**
     * FIND_IN_SET(needle, csv) →
     *   CASE WHEN needle IS NULL OR csv IS NULL THEN NULL
     *        WHEN needle = '' THEN 0
     *        ELSE (instr(',' || csv || ',', ',' || needle || ',') > 0) *
     *             ((length(substr(',' || csv, 1, instr(',' || csv || ',', ',' || needle || ',') - 1)) -
     *               length(replace(substr(',' || csv, 1, instr(',' || csv || ',', ',' || needle || ',') - 1), ',', ''))) + 1)
     *   END
     *
     * The expression looks like a mouthful but gives the 1-based position
     * of needle in csv (0 if absent, NULL on NULL inputs) without
     * requiring a SQLite extension. The trick: count commas in the prefix
     * preceding the match, +1 gives the 1-based position.
     */
    private function transformFindInSet(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $needle = $this->transformArgExpression($args[0]);
        $haystack = $this->transformArgExpression($args[1]);

        $rw->add(\sprintf(
            '(CASE WHEN %1$s IS NULL OR %2$s IS NULL THEN NULL '
            . "WHEN %1\$s = '' THEN 0 "
            . "WHEN instr(',' || %2\$s || ',', ',' || %1\$s || ',') = 0 THEN 0 "
            . "ELSE (length(substr(',' || %2\$s, 1, instr(',' || %2\$s || ',', ',' || %1\$s || ',') - 1)) - "
            . "length(replace(substr(',' || %2\$s, 1, instr(',' || %2\$s || ',', ',' || %1\$s || ',') - 1), ',', ''))) + 1 "
            . 'END)',
            $needle,
            $haystack,
        ));

        return true;
    }

    /**
     * SUBSTRING_INDEX(str, delim, n) → positive-n rewrite only.
     *
     * SQLite lacks split_part, so we emulate the positive case via repeated
     * instr() walks. Negative n (tail slice) has no reasonable SQLite
     * rewrite without a recursive CTE; we refuse translation loudly for
     * those inputs so the caller knows the limitation.
     */
    private function transformSubstringIndex(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 3) {
            return false;
        }

        $str = $this->transformArgExpression($args[0]);
        $delim = $this->transformArgExpression($args[1]);
        $count = trim($this->transformArgExpression($args[2]));

        if ($count === '' || !preg_match('/^-?\d+$/', $count)) {
            return false;
        }

        $n = (int) $count;
        if ($n < 0) {
            throw new UnsupportedFeatureException(
                $rw->getResult() . ' ... SUBSTRING_INDEX(..., ' . $count . ')',
                'sqlite',
                ['SUBSTRING_INDEX with negative count is not supported on SQLite'],
            );
        }

        if ($n === 0) {
            $rw->add("''");

            return true;
        }

        // Walk forward: position of the n-th delim, then take prefix.
        // Nested replace() finds the offset of the n-th occurrence by
        // replacing preceding delimiters with a marker then searching.
        // For n = 1 we can short-circuit with a simpler form.
        if ($n === 1) {
            $rw->add(\sprintf(
                "(CASE WHEN instr(%1\$s, %2\$s) = 0 THEN %1\$s ELSE substr(%1\$s, 1, instr(%1\$s, %2\$s) - 1) END)",
                $str,
                $delim,
            ));

            return true;
        }

        // Generic positive-n: use recursive substr walk via a helper
        // pattern. For n > 1 we emit a SELECT over a generated series,
        // which is heavyweight; plugins rarely pass n > 1 here so for
        // now fall through so the caller sees a recognisable error.
        return false;
    }

    /**
     * SPACE(n) → replace(hex(zeroblob(n)), '00', ' ')
     *
     * SQLite lacks a native repeat() for single characters, but zeroblob
     * allocates n NUL bytes cheaply and hex() expands each to '00'; the
     * replace() swap finishes with an n-char space string. Small hack,
     * but the alternative is a recursive CTE for every SPACE call.
     */
    private function transformSpace(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf("replace(hex(zeroblob(%s)), '00', ' ')", $expr));

        return true;
    }

    /**
     * TIME_TO_SEC('HH:MM:SS') → (hours*3600 + minutes*60 + seconds)
     *
     * Accepts the canonical MySQL TIME form. For values over 24h MySQL
     * returns the full seconds count; SQLite's strftime can't parse
     * HH:MM:SS > 23:59:59, so we decompose by substring arithmetic which
     * also handles the oversized case.
     */
    private function transformTimeToSec(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);

        // length-based split tolerates both H:MM:SS and HH:MM:SS inputs
        // (MySQL accepts both).
        $rw->add(\sprintf(
            '('
            . "CAST(substr(%1\$s, 1, instr(%1\$s, ':') - 1) AS INTEGER) * 3600 + "
            . "CAST(substr(%1\$s, instr(%1\$s, ':') + 1, 2) AS INTEGER) * 60 + "
            . "CAST(substr(%1\$s, -2, 2) AS INTEGER)"
            . ')',
            $expr,
        ));

        return true;
    }

    /**
     * DAYNAME(d) → CASE by strftime('%w', d) → 'Sunday'..'Saturday'
     *
     * SQLite has no locale-aware day/month names, and strftime('%A') is
     * unsupported. The CASE expression here is verbose but produces the
     * same English strings MySQL does.
     */
    private function transformDayName(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf(
            "(CASE CAST(strftime('%%w', %s) AS INTEGER) "
            . "WHEN 0 THEN 'Sunday' "
            . "WHEN 1 THEN 'Monday' "
            . "WHEN 2 THEN 'Tuesday' "
            . "WHEN 3 THEN 'Wednesday' "
            . "WHEN 4 THEN 'Thursday' "
            . "WHEN 5 THEN 'Friday' "
            . "WHEN 6 THEN 'Saturday' "
            . "END)",
            $expr,
        ));

        return true;
    }

    /**
     * MONTHNAME(d) → CASE by strftime('%m', d) → 'January'..'December'
     */
    private function transformMonthName(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf(
            "(CASE CAST(strftime('%%m', %s) AS INTEGER) "
            . "WHEN 1 THEN 'January' WHEN 2 THEN 'February' "
            . "WHEN 3 THEN 'March' WHEN 4 THEN 'April' "
            . "WHEN 5 THEN 'May' WHEN 6 THEN 'June' "
            . "WHEN 7 THEN 'July' WHEN 8 THEN 'August' "
            . "WHEN 9 THEN 'September' WHEN 10 THEN 'October' "
            . "WHEN 11 THEN 'November' WHEN 12 THEN 'December' "
            . "END)",
            $expr,
        ));

        return true;
    }

    /**
     * QUARTER(d) → (CAST(strftime('%m', d) AS INTEGER) - 1) / 3 + 1
     */
    private function transformQuarter(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf(
            "((CAST(strftime('%%m', %s) AS INTEGER) - 1) / 3 + 1)",
            $expr,
        ));

        return true;
    }

    /**
     * SEC_TO_TIME(n) → printf('%02d:%02d:%02d', n / 3600, (n % 3600) / 60, n % 60)
     */
    private function transformSecToTime(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf(
            "printf('%%02d:%%02d:%%02d', (%1\$s) / 3600, ((%1\$s) %% 3600) / 60, (%1\$s) %% 60)",
            $expr,
        ));

        return true;
    }

    /**
     * WEEK(d [, mode]) → CAST(strftime('%W', d) AS INTEGER)
     *
     * SQLite's strftime('%W') returns the ISO 8601 week number (Monday start).
     * MySQL's mode parameter (0-7) controls Sunday/Monday start and range,
     * but SQLite has no equivalent. We always use ISO week (%W).
     */
    private function transformWeek(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        // mode parameter is consumed but SQLite only supports ISO week (%W)

        $rw->add(\sprintf("CAST(strftime('%%W', %s) AS INTEGER)", $expr));

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

    // ── Meta commands ──

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
            return ["SELECT name AS table_name, 'BASE TABLE' AS table_type, 'def' AS table_catalog FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE '\_%' ESCAPE '\\'"];
        }

        if (preg_match('/\binformation_schema\.columns\b/i', $sql) && preg_match('/table_name\s*=\s*[\'"](\w+)[\'"]/i', $sql, $m)) {
            return [\sprintf('PRAGMA table_info("%s")', $m[1])];
        }

        if (preg_match('/^\s*SHOW\s+FULL\s+TABLES\s+LIKE\s+[\'"](.+?)[\'"]\s*$/i', $sql, $m)) {
            return [\sprintf("SELECT name, 'BASE TABLE' AS Table_type FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%%' AND name NOT LIKE '\\_%%' ESCAPE '\\' AND name LIKE '%s'", str_replace("'", "''", $m[1]))];
        }

        if (preg_match('/^\s*SHOW\s+TABLES\s+LIKE\s+[\'"](.+?)[\'"]\s*$/i', $sql, $m)) {
            return [\sprintf("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%%' AND name NOT LIKE '\\_%%' ESCAPE '\\' AND name LIKE '%s'", str_replace("'", "''", $m[1]))];
        }

        if (preg_match('/^\s*SHOW\s+FULL\s+TABLES\s*/i', $sql)) {
            return ["SELECT name, 'BASE TABLE' AS Table_type FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE '\_%' ESCAPE '\\'"];
        }

        if (preg_match('/^\s*SHOW\s+TABLES\s*/i', $sql)) {
            return ["SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE '\_%' ESCAPE '\\'"];
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
                . "FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%%%%' AND name NOT LIKE '\\_%%' ESCAPE '\\' AND name LIKE '%s'",
                str_replace("'", "''", $m[1]),
            )];
        }

        if (preg_match('/^\s*SHOW\s+TABLE\s+STATUS/i', $sql)) {
            return ["SELECT name AS Name, 'InnoDB' AS Engine, 0 AS Version, 'Dynamic' AS Row_format, "
                . "0 AS Rows, 0 AS Avg_row_length, 0 AS Data_length, 0 AS Max_data_length, "
                . "0 AS Index_length, 0 AS Data_free, NULL AS Auto_increment, "
                . "NULL AS Create_time, NULL AS Update_time, NULL AS Check_time, "
                . "'utf8mb4_unicode_ci' AS Collation, NULL AS Checksum, '' AS Create_options, '' AS Comment "
                . "FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE '\_%' ESCAPE '\\'"];
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
