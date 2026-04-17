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
use WpPack\Component\Database\Driver\DriverInterface;
use WpPack\Component\Database\Exception\TranslationException;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Translates MySQL SQL to PostgreSQL SQL using AST-guided token rewriting.
 *
 * Uses phpmyadmin/sql-parser's Parser for AST (structural understanding) and
 * QueryRewriter for token-level manipulation (expression transformation).
 *
 * String literals (TokenType::String) are never transformed.
 */
final class PostgresqlQueryTranslator implements QueryTranslatorInterface
{
    /** @var array<string, list<string>> */
    private array $constraintCache = [];

    public function __construct(
        private readonly ?DriverInterface $driver = null,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Drop a single table (or every table's) cached constraint columns.
     *
     * The translator caches information_schema lookups per-table for the
     * life of the process. Long-running workers (WP-CLI jobs, queue
     * consumers) that schema-mutate outside the translator's ALTER path
     * can call this to guarantee subsequent INSERT ... ON DUPLICATE KEY
     * translations see the current schema.
     */
    public function invalidateConstraintCache(?string $table = null): void
    {
        if ($table === null) {
            $this->constraintCache = [];

            return;
        }

        unset($this->constraintCache[$table]);
    }

    /** @var list<string> */
    private const IGNORED_PATTERNS = [
        '/^\s*SET\s+NAMES\s+/i',
        '/^\s*LOCK\s+TABLES?\s+/i',
        '/^\s*UNLOCK\s+TABLES?\s*/i',
        '/^\s*OPTIMIZE\s+TABLE\s+/i',
        '/^\s*CREATE\s+DATABASE\b/i',
        '/^\s*DROP\s+DATABASE\b/i',
    ];

    /** @var array<string, string> */
    private const ZERO_ARG_MAP = [
        'CURDATE' => 'CURRENT_DATE',
        'CURTIME' => 'CURRENT_TIME',
        'UNIX_TIMESTAMP' => 'EXTRACT(EPOCH FROM NOW())::INTEGER',
        'UTC_TIMESTAMP' => "NOW() AT TIME ZONE 'UTC'",
        'UTC_DATE' => "(NOW() AT TIME ZONE 'UTC')::date",
        'UTC_TIME' => "(NOW() AT TIME ZONE 'UTC')::time",
        'LOCALTIME' => 'NOW()',
        'LOCALTIMESTAMP' => 'NOW()',
        'VERSION' => 'version()',
        'DATABASE' => 'CURRENT_DATABASE()',
        'FOUND_ROWS' => '-1',
    ];

    /** @var array<string, string> */
    private const RENAME_MAP = [
        'RAND' => 'random',
        'IFNULL' => 'COALESCE',
        'LAST_INSERT_ID' => 'lastval',
        'CHAR_LENGTH' => 'LENGTH',
        'CHARACTER_LENGTH' => 'LENGTH',
        'MID' => 'SUBSTRING',
        'LCASE' => 'lower',
        'UCASE' => 'upper',
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

        $parser = new Parser($sql);

        // phpmyadmin/sql-parser records context-sensitive warnings alongside
        // real parse failures; only treat the combination of "errors AND no
        // statement produced" as a hard translation failure so that e.g.
        // stand-alone ROLLBACK/COMMIT, which the library flags with "No
        // transaction was previously started", still flows through.
        if ($parser->errors !== []) {
            $messages = array_map(static fn(\Throwable $e): string => $e->getMessage(), $parser->errors);

            if ($parser->statements === []) {
                $this->logger?->error('PostgreSQL query translation failed', [
                    'sql' => $sql,
                    'errors' => $messages,
                ]);

                throw new TranslationException($sql, 'pgsql', $messages);
            }

            $this->logger?->warning('PostgreSQL query translation: parser reported warnings', [
                'sql' => $sql,
                'errors' => $messages,
            ]);
        }

        $stmt = $parser->statements[0] ?? null;

        if ($stmt === null) {
            $this->logger?->warning('PostgreSQL query translation: unrecognised statement, falling back to token rewrite', [
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
            $stmt instanceof TruncateStatement => [$this->translateTruncate($stmt, $parser)],
            $stmt instanceof AlterStatement => $this->translateAlter($stmt, $parser),
            $stmt instanceof SetStatement => [],
            default => [$this->rewriteTokens($parser)],
        };
    }

    // ── DML handlers ──

    private function translateSelect(SelectStatement $stmt, Parser $parser): string
    {
        $rw = $this->createRewriter($parser);

        // DISTINCT + ORDER BY: PgSQL requires ORDER BY columns in SELECT
        $missingOrderCols = $this->detectMissingOrderByColumns($stmt);

        while ($rw->hasMore()) {
            $token = $rw->peek();
            if ($token === null) {
                break;
            }

            // SQL_CALC_FOUND_ROWS → skip
            if ($token->type === TokenType::Keyword && $token->keyword === 'SQL_CALC_FOUND_ROWS') {
                $rw->skip();
                continue;
            }

            // Inject missing ORDER BY columns before FROM
            if ($missingOrderCols !== [] && $token->type === TokenType::Keyword && $token->keyword === 'FROM') {
                $rw->add(', ' . implode(', ', $missingOrderCols));
                $missingOrderCols = [];
            }

            // FROM DUAL → skip
            if ($token->type === TokenType::Keyword && $token->keyword === 'FROM') {
                $next = $rw->peekNth(2);
                if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'DUAL') {
                    $rw->skip();
                    $rw->skip();
                    continue;
                }
            }

            // INDEX HINTS: USE/FORCE/IGNORE INDEX (...) → skip
            if ($token->type === TokenType::Keyword
                && \in_array($token->keyword, ['USE', 'FORCE', 'IGNORE'], true)) {
                $next = $rw->peekNth(2);
                if ($next !== null && ($next->keyword === 'INDEX' || $next->keyword === 'KEY')) {
                    $rw->skip();
                    $rw->skip();
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
            }

            // LIMIT: rewrite using AST
            if ($token->type === TokenType::Keyword && $token->keyword === 'LIMIT' && $stmt->limit !== null) {
                $this->rewriteLimit($rw, $stmt->limit->offset, $stmt->limit->rowCount);
                continue;
            }

            $this->translateExpression($rw);
        }

        return $this->postProcessPgsql($rw->getResult());
    }

    /**
     * Infer ON CONFLICT target from INSERT ... ON DUPLICATE KEY UPDATE.
     *
     * Heuristic: INSERT columns that are NOT in the ON DUPLICATE KEY UPDATE
     * SET clause are likely the unique constraint columns. This works for
     * WordPress core patterns where ODKU updates all non-key columns.
     */
    private function inferConflictTarget(InsertStatement $stmt): string
    {
        $insertCols = $stmt->into->columns ?? [];
        if ($insertCols === []) {
            return '';
        }

        $updateCols = [];
        foreach ($stmt->onDuplicateSet ?? [] as $set) {
            $updateCols[] = $set->column;
        }

        // Conflict target = INSERT columns not in UPDATE set
        $conflictCols = array_diff($insertCols, $updateCols);

        if ($conflictCols === []) {
            return '';
        }

        return implode(', ', array_map(fn(string $col): string => $this->quoteId($col), $conflictCols));
    }

    /**
     * Query information_schema for PRIMARY KEY or UNIQUE constraint columns.
     *
     * @return list<string>
     */
    private function getConstraintColumns(string $table): array
    {
        if ($this->driver === null) {
            return [];
        }

        if (isset($this->constraintCache[$table])) {
            return $this->constraintCache[$table];
        }

        try {
            // Get ALL columns of the first matching constraint (UNIQUE preferred
            // over PK). Subquery picks the constraint name, outer query gets all
            // its columns — important for composite keys like UNIQUE(col1, col2).
            $result = $this->driver->executeQuery(
                "SELECT kcu.column_name
                 FROM information_schema.table_constraints tc
                 JOIN information_schema.key_column_usage kcu
                   ON tc.constraint_name = kcu.constraint_name
                   AND tc.table_schema = kcu.table_schema
                 WHERE tc.table_schema = 'public'
                   AND tc.table_name = ?
                   AND tc.constraint_name = (
                     SELECT tc2.constraint_name
                     FROM information_schema.table_constraints tc2
                     WHERE tc2.table_schema = 'public'
                       AND tc2.table_name = ?
                       AND tc2.constraint_type IN ('PRIMARY KEY', 'UNIQUE')
                     ORDER BY CASE tc2.constraint_type WHEN 'UNIQUE' THEN 0 ELSE 1 END
                     LIMIT 1
                   )
                 ORDER BY kcu.ordinal_position",
                [$table, $table],
            );

            $cols = array_column($result->fetchAllAssociative(), 'column_name');
        } catch (\Throwable) {
            $cols = [];
        }

        $this->constraintCache[$table] = $cols;

        return $cols;
    }

    private function postProcessPgsql(string $sql): string
    {
        // meta_value + 0 → CAST(meta_value AS BIGINT)
        $sql = (string) preg_replace(
            '/\b(meta_value)\s*\+\s*0\b/',
            'CAST($1 AS BIGINT)',
            $sql,
        );

        // Zero dates: MySQL '0000-00-00 00:00:00' → PostgreSQL '0001-01-01 00:00:00'
        // PostgreSQL doesn't support year 0000. '0001-01-01' is the earliest valid date.
        // Unlike '-infinity', EXTRACT(YEAR FROM ...) returns 1 (not -Infinity), so
        // WordPress's YEAR(post_date) translation works correctly.
        // NOT NULL constraints are satisfied. All real dates are > '0001-01-01'.
        $sql = (string) preg_replace(
            '/[\'"]0000-00-00\s+00:00:00[\'"]/',
            "'0001-01-01 00:00:00'",
            $sql,
        );
        $sql = (string) preg_replace(
            '/[\'"]0000-00-00[\'"]/',
            "'0001-01-01'",
            $sql,
        );

        // AS 'alias' → AS "alias" (PgSQL requires double quotes for identifiers)
        // Plugins like NextGen Gallery generate AS 'single_quoted'
        $sql = (string) preg_replace(
            "/\\bAS\\s+'([^']+)'/",
            'AS "$1"',
            $sql,
        );

        // Remove ORDER BY in COUNT(*) queries (useless, hurts performance)
        if (preg_match('/^\s*SELECT\s+COUNT\s*\(/i', $sql)) {
            $sql = (string) preg_replace('/\s+ORDER\s+BY\s+[^)]+$/i', '', $sql);
        }

        return $sql;
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

            // INSERT IGNORE → INSERT ... ON CONFLICT DO NOTHING
            if ($token->type === TokenType::Keyword && $token->keyword === 'IGNORE' && $hasIgnore) {
                $rw->skip(); // skip IGNORE
                continue;
            }

            // ON DUPLICATE KEY UPDATE → ON CONFLICT (cols) DO UPDATE SET
            if ($token->type === TokenType::Keyword && $token->keyword === 'ON' && $hasOnDuplicate && !$inOnConflictUpdate) {
                $next = $rw->peekNth(2);
                if ($next !== null && $next->keyword === 'DUPLICATE') {
                    $rw->skip(); // ON
                    $rw->skip(); // DUPLICATE
                    $rw->skip(); // KEY
                    $rw->skip(); // UPDATE

                    // Infer conflict target: INSERT columns minus UPDATE columns.
                    // If inference fails, fall back to DO NOTHING (safe degradation).
                    $conflictTarget = $this->inferConflictTarget($stmt);
                    if ($conflictTarget !== '') {
                        $rw->add('ON CONFLICT (' . $conflictTarget . ') DO UPDATE SET');
                        $inOnConflictUpdate = true;
                    } else {
                        $rw->consumeAll();
                        $rw->add(' ON CONFLICT DO NOTHING');

                        break;
                    }
                    continue;
                }
            }

            // VALUES(col) in ON CONFLICT context → excluded.col
            if ($inOnConflictUpdate && $token->type === TokenType::Keyword
                && $token->keyword === 'VALUES'
                && $rw->peekNth(2)?->token === '(') {
                $rw->skip(); // VALUES
                $rw->skip(); // (
                $inner = $rw->peek();
                $colName = $inner !== null ? ($inner->type === TokenType::Symbol && ($inner->flags & Token::FLAG_SYMBOL_BACKTICK) !== 0
                    ? '"' . str_replace('"', '""', (string) $inner->value) . '"'
                    : $inner->token) : '';
                $rw->skip(); // column name
                $rw->skip(); // )
                $rw->add('excluded.' . $colName);
                continue;
            }

            $this->translateExpression($rw);
        }

        $result = $rw->getResult();

        // For INSERT IGNORE: append ON CONFLICT DO NOTHING
        if ($hasIgnore && !$hasOnDuplicate) {
            $result = rtrim($result, " \t\n\r;") . ' ON CONFLICT DO NOTHING';
        }

        $results = [$this->postProcessPgsql($result)];

        // Sync sequence after INSERT with explicit ID (PgSQL SERIAL sequences
        // don't auto-update when IDs are inserted explicitly).
        // WordPress inserts explicit IDs during installation (term_id=1, post_id=1,2,3).
        $setvalSql = $this->buildSetvalIfNeeded($stmt);
        if ($setvalSql !== null) {
            $results[] = $setvalSql;
        }

        return $results;
    }

    /**
     * Build setval() SQL to sync a SERIAL sequence after explicit ID insertion.
     *
     * When INSERT specifies an ID column explicitly (e.g., `INSERT INTO t (id, ...) VALUES (5, ...)`),
     * the PostgreSQL sequence is not updated. This causes the next auto-generated ID
     * to conflict. setval() resets the sequence to MAX(id)+1.
     */
    private function buildSetvalIfNeeded(InsertStatement $stmt): ?string
    {
        if ($stmt->into === null) {
            return null;
        }

        $table = $stmt->into->dest->table ?? null;
        if ($table === null) {
            return null;
        }

        // Check if any column looks like a primary key ID (convention-based)
        $columns = $stmt->into->columns ?? [];
        $idColumn = null;

        foreach ($columns as $col) {
            $name = strtolower((string) $col);
            if ($name === 'id' || str_ends_with($name, '_id') || $name === 'term_id' || $name === 'umeta_id') {
                $idColumn = $name;
                break;
            }
        }

        if ($idColumn === null) {
            return null;
        }

        $quotedTable = $this->quoteId($table);
        $quotedCol = $this->quoteId($idColumn);

        // Sequence naming convention: {table}_{column}_seq
        $seqName = $table . '_' . $idColumn . '_seq';

        return \sprintf(
            "SELECT setval('%s', COALESCE((SELECT MAX(%s) FROM %s), 0) + 1, false)",
            str_replace("'", "''", $seqName),
            $quotedCol,
            $quotedTable,
        );
    }

    /**
     * REPLACE INTO → INSERT ... ON CONFLICT (first_col) DO UPDATE SET remaining columns.
     *
     * MySQL REPLACE deletes the existing row and inserts a new one. PostgreSQL
     * has no direct equivalent. We use the first column as the conflict target
     * Queries information_schema for the actual PRIMARY KEY or UNIQUE constraint
     * columns, then generates ON CONFLICT (...) DO UPDATE SET for all remaining
     * columns. Falls back to DO NOTHING if no driver or constraint info available.
     */
    private function translateReplace(Parser $parser): string
    {
        /** @var ReplaceStatement $stmt */
        $stmt = $parser->statements[0];
        $rw = $this->createRewriter($parser);

        while ($rw->hasMore()) {
            $token = $rw->peek();
            if ($token === null) {
                break;
            }

            if ($token->type === TokenType::Keyword && $token->keyword === 'REPLACE') {
                $rw->skip();
                $rw->add('INSERT');
                continue;
            }

            $this->translateExpression($rw);
        }

        $insertSql = $rw->getResult();
        $table = $stmt->into->dest->table ?? '';
        $columns = $stmt->into->columns ?? [];

        // Query information_schema for actual constraint columns
        $constraintCols = $this->getConstraintColumns($table);

        if ($constraintCols === [] || $columns === []) {
            return $insertSql . ' ON CONFLICT DO NOTHING';
        }

        $conflictTarget = implode(', ', array_map(fn(string $c): string => $this->quoteId($c), $constraintCols));

        // Non-constraint columns get DO UPDATE SET; if all are constraint, SET all (no-op UPDATE for affected_rows=1)
        $updateCols = array_values(array_diff($columns, $constraintCols));
        if ($updateCols === []) {
            $updateCols = $columns;
        }

        $updateSet = implode(', ', array_map(
            fn(string $c): string => $this->quoteId($c) . ' = EXCLUDED.' . $this->quoteId($c),
            $updateCols,
        ));

        return $insertSql . ' ON CONFLICT (' . $conflictTarget . ') DO UPDATE SET ' . $updateSet;
    }

    private function translateUpdate(UpdateStatement $stmt, Parser $parser): string
    {
        // PostgreSQL does not support UPDATE ... LIMIT — wrap with ctid subquery
        if ($stmt->limit !== null) {
            return $this->rewriteWithCtidSubquery($stmt, $parser, 'UPDATE');
        }

        return $this->rewriteTokens($parser);
    }

    private function translateDelete(DeleteStatement $stmt, Parser $parser): string
    {
        if ($stmt->limit !== null) {
            return $this->rewriteWithCtidSubquery($stmt, $parser, 'DELETE');
        }

        // DELETE JOIN: DELETE a FROM t1 a JOIN t2 b ON ... → USING syntax
        if ($stmt->join !== null && $stmt->join !== []) {
            return $this->rewriteDeleteJoin($stmt);
        }

        return $this->rewriteTokens($parser);
    }

    /**
     * Rewrite DELETE JOIN to PostgreSQL USING syntax.
     *
     * MySQL:  DELETE a FROM t1 a JOIN t2 b ON a.col = b.col WHERE ...
     * PgSQL:  DELETE FROM t1 a USING t2 b WHERE a.col = b.col AND ...
     */
    private function rewriteDeleteJoin(DeleteStatement $stmt): string
    {
        $table = $stmt->from[0]->table ?? '';
        $alias = $stmt->from[0]->alias ?? '';
        $quotedTable = $this->quoteId($table);
        $aliasClause = $alias !== '' ? ' ' . $alias : '';

        $usingParts = [];
        $onConditions = [];

        foreach ($stmt->join as $join) {
            $joinTable = $join->expr->table ?? $join->expr->expr ?? '';
            $joinAlias = $join->expr->alias ?? '';
            $usingParts[] = $this->quoteId($joinTable) . ($joinAlias !== '' ? ' ' . $joinAlias : '');
            if ($join->on !== null) {
                foreach ($join->on as $cond) {
                    if (!$cond->isOperator) {
                        $onConditions[] = $cond->expr;
                    }
                }
            }
        }

        $whereParts = [];
        if ($stmt->where !== null) {
            foreach ($stmt->where as $cond) {
                if (!$cond->isOperator) {
                    $whereParts[] = $cond->expr;
                }
            }
        }

        $allConditions = [...$onConditions, ...$whereParts];
        $whereClause = $allConditions !== [] ? ' WHERE ' . implode(' AND ', $allConditions) : '';

        return \sprintf(
            'DELETE FROM %s%s USING %s%s',
            $quotedTable,
            $aliasClause,
            implode(', ', $usingParts),
            $whereClause,
        );
    }

    /**
     * Rewrite UPDATE/DELETE with LIMIT using ctid subquery for PostgreSQL.
     */
    private function rewriteWithCtidSubquery(UpdateStatement|DeleteStatement $stmt, Parser $parser, string $verb): string
    {
        $tableName = match (true) {
            $stmt instanceof UpdateStatement => $stmt->tables[0]->table ?? null,
            $stmt instanceof DeleteStatement => $stmt->from[0]->table ?? null,
        };

        if ($tableName === null) {
            return $this->rewriteTokens($parser);
        }

        $quotedTable = $this->quoteId($tableName);
        $limit = $stmt->limit->rowCount;

        $whereParts = [];
        if ($stmt->where !== null) {
            foreach ($stmt->where as $cond) {
                $whereParts[] = $cond->expr;
            }
        }
        $whereClause = $whereParts !== [] ? implode(' ', $whereParts) : '1=1';

        $orderParts = [];
        if ($stmt->order !== null) {
            foreach ($stmt->order as $order) {
                $orderParts[] = $order->expr->expr . ' ' . $order->type->value;
            }
        }
        $orderClause = $orderParts !== [] ? ' ORDER BY ' . implode(', ', $orderParts) : '';

        $subquery = \sprintf(
            'ctid IN (SELECT ctid FROM %s WHERE %s%s LIMIT %s)',
            $quotedTable,
            $whereClause,
            $orderClause,
            $limit,
        );

        $rw = $this->createRewriter($parser);

        while ($rw->hasMore()) {
            $token = $rw->peek();
            if ($token === null) {
                break;
            }

            if ($token->type === TokenType::Keyword
                && \in_array($token->keyword, ['WHERE', 'ORDER BY', 'LIMIT'], true)) {
                do {
                    $rw->skip();
                } while ($rw->peek() !== null);

                $rw->add(' WHERE ' . $subquery);

                return $rw->getResult();
            }

            $this->translateExpression($rw);
        }

        $rw->add(' WHERE ' . $subquery);

        return $rw->getResult();
    }

    /**
     * @return list<string>
     */
    private function translateAlter(AlterStatement $stmt, Parser $parser): array
    {
        $table = $stmt->table->table ?? '';

        // Any ALTER TABLE may add, drop, or rename a UNIQUE / PRIMARY KEY
        // constraint. If we keep serving cached constraint columns for this
        // table, a later INSERT ... ON DUPLICATE KEY will target the wrong
        // ON CONFLICT (...) clause. Invalidate eagerly — cost of one more
        // information_schema lookup on the next REPLACE/INSERT is trivial.
        if ($table !== '') {
            unset($this->constraintCache[$table]);
        }

        if ($stmt->altered !== null) {
            foreach ($stmt->altered as $alter) {
                $optStr = strtoupper(trim(implode(' ', array_filter($alter->options->options ?? [], '\is_string'))));

                // CHANGE COLUMN / MODIFY COLUMN → ALTER COLUMN TYPE (+ optional RENAME)
                if (str_contains($optStr, 'CHANGE') || str_contains($optStr, 'MODIFY')) {
                    return $this->translateAlterChange($stmt, $alter);
                }
            }
        }

        // Token-rewrite then post-process for INDEX operations
        $sql = $this->rewriteTokens($parser);
        $quotedTable = $this->quoteId($table);

        // ADD [UNIQUE] INDEX name (cols) → CREATE [UNIQUE] INDEX name ON table (cols)
        if (preg_match('/\bADD\s+(UNIQUE\s+)?(?:INDEX|KEY)\s+("?\w+"?)\s*(\([^)]+\))/i', $sql, $m)) {
            return [\sprintf('CREATE %sINDEX %s ON %s %s', $m[1], $m[2], $quotedTable, $m[3])];
        }

        // DROP INDEX name → DROP INDEX IF EXISTS name
        if (preg_match('/\bDROP\s+(?:INDEX|KEY)\s+("?\w+"?)/i', $sql, $m)) {
            return [\sprintf('DROP INDEX IF EXISTS %s', $m[1])];
        }

        return [$sql];
    }

    /**
     * ALTER TABLE t CHANGE/MODIFY COLUMN → ALTER COLUMN TYPE + optional RENAME COLUMN.
     *
     * @return list<string>
     */
    private function translateAlterChange(AlterStatement $stmt, \PhpMyAdmin\SqlParser\Components\AlterOperation $alter): array
    {
        $table = $this->quoteId($stmt->table->table ?? '');
        $results = [];

        $optStr = strtoupper(trim(implode(' ', array_filter($alter->options->options ?? [], '\is_string'))));
        $isChange = str_contains($optStr, 'CHANGE');

        $oldName = $alter->field->column ?? $alter->field->name ?? null;
        $newName = $oldName;

        // CHANGE: first unknown token is the new column name
        if ($isChange && $alter->unknown !== []) {
            $newName = $alter->unknown[0]->value ?? $oldName;
        }

        // Extract type from unknown tokens (skip new_name for CHANGE)
        $typeTokens = $alter->unknown;
        if ($isChange && $typeTokens !== []) {
            // Skip the new column name and whitespace
            array_shift($typeTokens); // new_name
            while ($typeTokens !== [] && $typeTokens[0]->type === \PhpMyAdmin\SqlParser\TokenType::Whitespace) {
                array_shift($typeTokens);
            }
        }
        $typeSql = trim(implode('', array_map(static fn($t) => $t->token, $typeTokens)));

        // ALTER COLUMN TYPE
        if ($oldName !== null && $typeSql !== '') {
            $pgsqlType = $this->mapPgsqlType(strtoupper(explode('(', explode(' ', $typeSql)[0])[0]));
            $results[] = \sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE %s',
                $table,
                $this->quoteId($oldName),
                $pgsqlType,
            );
        }

        // RENAME COLUMN (only if names differ)
        if ($isChange && $oldName !== null && $newName !== null && $oldName !== $newName) {
            $results[] = \sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $table,
                $this->quoteId($oldName),
                $this->quoteId($newName),
            );
        }

        return $results !== [] ? $results : [];
    }

    /**
     * TRUNCATE TABLE → TRUNCATE TABLE ... RESTART IDENTITY.
     *
     * MySQL TRUNCATE resets AUTO_INCREMENT to 1. PostgreSQL TRUNCATE
     * does not reset SERIAL sequences unless RESTART IDENTITY is specified.
     */
    private function translateTruncate(TruncateStatement $stmt, Parser $parser): string
    {
        $rw = $this->createRewriter($parser);
        $rw->consumeAll();

        return rtrim($rw->getResult()) . ' RESTART IDENTITY';
    }

    // ── DDL handlers ──

    /**
     * Translate CREATE TABLE using AST CreateDefinition[] directly.
     *
     * For CREATE INDEX / CREATE VIEW, falls back to token rewriting.
     *
     * @return list<string>
     */
    private function translateCreate(CreateStatement $stmt, Parser $parser): array
    {
        if (!\is_array($stmt->fields) || $stmt->fields === []) {
            return [$this->rewriteTokens($parser)];
        }

        return $this->buildCreateTable($stmt);
    }

    /**
     * @return list<string>
     */
    private function buildCreateTable(CreateStatement $stmt): array
    {
        $rawTableName = $stmt->name->table ?? '';
        $tableName = $this->quoteId($rawTableName);
        $ifNotExists = ($stmt->options?->has('IF NOT EXISTS')) ? 'IF NOT EXISTS ' : '';

        $parts = [];
        $indexStatements = [];
        $triggers = [];

        foreach ($stmt->fields as $field) {
            if ($field->type !== null) {
                $parts[] = $this->buildColumnDef($field);

                // ON UPDATE CURRENT_TIMESTAMP → PgSQL trigger
                $optionsBuild = strtoupper($field->options?->build() ?? '');
                if (str_contains($optionsBuild, 'ON UPDATE CURRENT_TIMESTAMP') && $field->name !== null) {
                    $col = $field->name;
                    $funcName = "_wppack_{$rawTableName}_{$col}_update";
                    $triggers[] = \sprintf(
                        "CREATE OR REPLACE FUNCTION %s() RETURNS TRIGGER AS $$ BEGIN NEW.%s = NOW(); RETURN NEW; END; $$ LANGUAGE plpgsql",
                        $this->quoteId($funcName),
                        $this->quoteId($col),
                    );
                    $triggers[] = \sprintf(
                        'CREATE TRIGGER %s BEFORE UPDATE ON %s FOR EACH ROW EXECUTE FUNCTION %s()',
                        $this->quoteId("__{$rawTableName}_{$col}_on_update__"),
                        $tableName,
                        $this->quoteId($funcName),
                    );
                }
            } elseif ($field->key !== null) {
                // PRIMARY KEY and UNIQUE constraints stay inline;
                // regular KEY/INDEX must be separate CREATE INDEX statements
                if ($field->key->type === 'PRIMARY KEY' || $field->key->type === 'UNIQUE KEY') {
                    $parts[] = $this->buildKeyDef($field->key);
                } else {
                    $indexStatements[] = $this->buildCreateIndex($rawTableName, $field->key);
                }
            }
        }

        $results = [\sprintf("CREATE TABLE %s%s (%s)", $ifNotExists, $tableName, implode(', ', $parts))];

        return [...$results, ...$indexStatements, ...$triggers];
    }

    private function buildColumnDef(\PhpMyAdmin\SqlParser\Components\CreateDefinition $field): string
    {
        $name = $this->quoteIdLower($field->name ?? '');
        $typeName = $field->type !== null ? strtoupper($field->type->name) : '';

        // Map MySQL type → PostgreSQL type
        $type = $this->mapPgsqlType($typeName);

        // Preserve (N) for types that support it in PostgreSQL
        $typeParams = $field->type !== null ? $field->type->parameters : [];
        if ($typeParams !== [] && \in_array($typeName, ['VARCHAR', 'CHAR', 'DECIMAL', 'NUMERIC'], true)) {
            $type .= '(' . implode(', ', $typeParams) . ')';
        }

        $clauses = [$name, $type];

        // NOT NULL
        if ($field->options?->has('NOT NULL')) {
            $clauses[] = 'NOT NULL';
        }

        // AUTO_INCREMENT → SERIAL / BIGSERIAL / SMALLSERIAL
        if ($field->options?->has('AUTO_INCREMENT')) {
            $clauses[1] = match ($typeName) {
                'BIGINT' => 'BIGSERIAL',
                'SMALLINT', 'TINYINT' => 'SMALLSERIAL',
                default => 'SERIAL',
            };
        }

        // PRIMARY KEY (inline)
        if ($field->options?->has('PRIMARY KEY')) {
            $clauses[] = 'PRIMARY KEY';
        }

        // DEFAULT (convert MySQL zero dates → PostgreSQL sentinel)
        $defaultExpr = $field->options?->get('DEFAULT', true);
        if ($defaultExpr instanceof \PhpMyAdmin\SqlParser\Components\Expression && $defaultExpr->expr !== null && $defaultExpr->expr !== '') {
            $expr = $defaultExpr->expr;

            // MySQL zero dates are invalid in PostgreSQL
            $expr = str_replace(
                ["'0000-00-00 00:00:00'", "'0000-00-00'"],
                ["'0001-01-01 00:00:00'", "'0001-01-01'"],
                $expr,
            );

            $clauses[] = 'DEFAULT ' . $expr;
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
                $columns[] = $this->quoteIdLower($col['name']);
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
     * PostgreSQL does not support inline KEY definitions in CREATE TABLE.
     */
    private function buildCreateIndex(string $table, \PhpMyAdmin\SqlParser\Components\Key $key): string
    {
        $columns = [];

        foreach ($key->columns as $col) {
            if (isset($col['name'])) {
                $columns[] = $this->quoteIdLower($col['name']);
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

    private function mapPgsqlType(string $mysqlType): string
    {
        return match ($mysqlType) {
            'TINYINT' => 'SMALLINT',
            'MEDIUMINT', 'INT' => 'INTEGER',
            'BIGINT' => 'BIGINT',
            'INTEGER' => 'INTEGER',
            'SMALLINT' => 'SMALLINT',
            'DOUBLE' => 'DOUBLE PRECISION',
            'FLOAT' => 'REAL',
            'DATETIME' => 'TIMESTAMP',
            'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT', 'TEXT' => 'TEXT',
            'VARCHAR' => 'VARCHAR',
            'CHAR' => 'CHAR',
            'DECIMAL' => 'DECIMAL',
            'NUMERIC' => 'NUMERIC',
            'REAL' => 'REAL',
            'BOOLEAN' => 'BOOLEAN',
            'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB', 'BLOB' => 'BYTEA',
            'VARBINARY', 'BINARY' => 'BYTEA',
            'ENUM' => 'TEXT',
            'JSON' => 'JSONB',
            'DATE' => 'DATE',
            'TIME' => 'TIME',
            'TIMESTAMP' => 'TIMESTAMP',
            default => 'TEXT',
        };
    }

    private function quoteId(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * Quote identifier with forced lowercase for DDL.
     *
     * PostgreSQL folds unquoted identifiers to lowercase. When WordPress
     * creates tables with "ID" (uppercase quoted) but queries with unquoted
     * ID (resolved to lowercase "id"), the names don't match. Forcing
     * lowercase in DDL ensures compatibility with WordPress query patterns.
     */
    private function quoteIdLower(string $identifier): string
    {
        return '"' . str_replace('"', '""', strtolower($identifier)) . '"';
    }

    /**
     * Detect ORDER BY columns missing from SELECT when DISTINCT is used.
     *
     * PostgreSQL requires all ORDER BY columns to be in the SELECT list
     * when DISTINCT is used. MySQL allows this.
     *
     * @return list<string>
     */
    private function detectMissingOrderByColumns(SelectStatement $stmt): array
    {
        $hasDistinct = $stmt->options !== null && $stmt->options->has('DISTINCT');
        if (!$hasDistinct || $stmt->order === null || $stmt->expr === []) {
            return [];
        }

        // Collect SELECT expressions
        $selectExprs = [];
        foreach ($stmt->expr as $expr) {
            $e = $expr->expr ?? '';
            if ($e === '*') {
                return []; // Wildcard includes all columns
            }
            $selectExprs[] = $e;
            if ($expr->alias !== null) {
                $selectExprs[] = $expr->alias;
            }
        }

        // Find ORDER BY columns not in SELECT
        $missing = [];
        foreach ($stmt->order as $order) {
            $orderExpr = $order->expr->expr ?? '';
            if ($orderExpr === '') {
                continue;
            }

            $found = false;
            foreach ($selectExprs as $se) {
                if ($se === $orderExpr || str_ends_with($se, '.' . $orderExpr)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $missing[] = $orderExpr;
            }
        }

        return $missing;
    }

    // ── Expression translation ──

    private function translateExpression(QueryRewriter $rw): void
    {
        $token = $rw->peek();
        if ($token === null) {
            return;
        }

        // String literals → pass through
        if ($token->type === TokenType::String) {
            $rw->consume();

            return;
        }

        if ($token->type !== TokenType::Keyword || $token->keyword === null) {
            $rw->consume();

            return;
        }

        $kw = $token->keyword;

        // FROM DUAL → skip (MySQL compatibility — PostgreSQL has no DUAL table)
        if ($kw === 'FROM') {
            $next = $rw->peekNth(2);
            if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'DUAL') {
                $rw->skip(); // FROM
                $rw->skip(); // DUAL

                return;
            }
        }

        // ── Zero-arg functions ──
        if (isset(self::ZERO_ARG_MAP[$kw])
            && $rw->peekNth(2)?->token === '('
            && $rw->peekNth(3)?->token === ')') {
            $rw->skip();
            $rw->skip();
            $rw->skip();
            $rw->add(self::ZERO_ARG_MAP[$kw]);

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

        // ── REGEXP [BINARY] → ~* (case-insensitive) or ~ (case-sensitive) ──
        if ($kw === 'REGEXP') {
            $next = $rw->peekNth(2);
            if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'BINARY') {
                $rw->skip(); // REGEXP
                $rw->skip(); // BINARY
                $rw->add('~'); // case-sensitive
            } else {
                $rw->skip();
                $rw->add('~*'); // case-insensitive
            }

            return;
        }

        // ── LIKE → ILIKE (MySQL LIKE is case-insensitive by default) ──
        if ($kw === 'LIKE') {
            $next = $rw->peekNth(2);
            // LIKE BINARY → keep as LIKE (case-sensitive in PgSQL)
            if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'BINARY') {
                $rw->consume(); // LIKE
                $rw->skip(); // BINARY

                return;
            }

            // Regular LIKE → ILIKE + ESCAPE handling
            $rw->skip();
            $rw->add('ILIKE');
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

            // MySQL treats \ as the default LIKE escape character; PostgreSQL does not.
            // Add ESCAPE '\' for parameterised patterns (?) and plain string patterns.
            $afterPattern = $rw->peekNth(2);
            $hasEscape = $afterPattern !== null
                && $afterPattern->type === TokenType::Keyword
                && $afterPattern->keyword === 'ESCAPE';

            if (!$hasEscape) {
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

        // ── BINARY: CAST context → BYTEA, otherwise skip ──
        if ($kw === 'BINARY') {
            $rw->skip();
            if ($rw->getDepth() > 0) {
                $rw->add('BYTEA');
            }

            return;
        }

        // ── COLLATE clause → skip ──
        if ($kw === 'COLLATE') {
            $rw->skip(); // COLLATE
            $rw->skip(); // collation name

            return;
        }

        // ── Empty IN clause: IN () → IN (NULL) ──
        if ($kw === 'IN') {
            $rw->consume();
            $next = $rw->peek();
            if ($next !== null && $next->token === '(') {
                $afterOpen = $rw->peekNth(2);
                if ($afterOpen !== null && $afterOpen->token === ')') {
                    $rw->skip();
                    $rw->skip();
                    $rw->add('(NULL)');

                    return;
                }
            }

            return;
        }

        // ── LOW_PRIORITY / DELAYED / HIGH_PRIORITY → skip ──
        if (\in_array($kw, ['LOW_PRIORITY', 'DELAYED', 'HIGH_PRIORITY'], true)) {
            $rw->skip();

            return;
        }

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
            'IF' => $this->transformIfFunc($rw),
            'CONCAT' => $this->transformConcat($rw),
            'CONCAT_WS' => $this->transformConcatWs($rw),
            'DATEDIFF' => $this->transformDatediff($rw),
            'MONTH' => $this->transformDateExtract($rw, 'MONTH'),
            'YEAR' => $this->transformDateExtract($rw, 'YEAR'),
            'DAY', 'DAYOFMONTH' => $this->transformDateExtract($rw, 'DAY'),
            'HOUR' => $this->transformDateExtract($rw, 'HOUR'),
            'MINUTE' => $this->transformDateExtract($rw, 'MINUTE'),
            'SECOND' => $this->transformDateExtract($rw, 'SECOND'),
            'DAYOFWEEK' => $this->transformDayOfWeek($rw),
            'DAYOFYEAR' => $this->transformDateExtract($rw, 'DOY'),
            'WEEKDAY' => $this->transformWeekday($rw),
            'LOCATE' => $this->transformLocate($rw),
            'GROUP_CONCAT' => $this->transformGroupConcat($rw),
            'ISNULL' => $this->transformIsnull($rw),
            'WEEK' => $this->transformWeek($rw),
            'CONVERT' => $this->transformConvert($rw),
            'FIELD' => $this->transformField($rw),
            'UNHEX' => $this->transformUnhex($rw),
            'TO_BASE64' => $this->transformToBase64($rw),
            'FROM_BASE64' => $this->transformFromBase64($rw),
            'INET_ATON' => $this->transformInetAton($rw),
            'INET_NTOA' => $this->transformInetNtoa($rw),
            'LOG' => $this->transformLog($rw),
            'GET_LOCK' => $this->transformGetLock($rw),
            'RELEASE_LOCK' => $this->transformReleaseLock($rw),
            'IS_FREE_LOCK' => $this->transformIsFreeLock($rw),
            default => false,
        };
    }

    private function transformDateAddSub(QueryRewriter $rw, string $sign): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $intervalParts = $this->parseIntervalArg($args[1]);
        if ($intervalParts === null) {
            return false;
        }

        [$number, $unit] = $intervalParts;
        $dateExpr = $this->transformArgExpression($args[0]);

        $rw->add(\sprintf("%s %s INTERVAL '%s %s'", $dateExpr, $sign, $number, strtolower($unit)));

        return true;
    }

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

        // Use strtr() for simultaneous replacement (avoids cascading issues)
        $format = strtr((string) $formatToken->value, [
            '%Y' => 'YYYY', '%y' => 'YY', '%m' => 'MM', '%c' => 'FMMM',
            '%d' => 'DD', '%e' => 'FMDD', '%H' => 'HH24', '%h' => 'HH12',
            '%I' => 'HH12', '%i' => 'MI', '%s' => 'SS', '%S' => 'SS',
            '%j' => 'DDD', '%W' => 'Day', '%w' => 'D', '%p' => 'AM',
            '%T' => 'HH24:MI:SS', '%r' => 'HH12:MI:SS AM',
            '%a' => 'Dy', '%b' => 'Mon', '%M' => 'FMMonth',
            '%D' => 'FMDDth', '%k' => 'FMHH24', '%l' => 'FMHH12',
            '%U' => 'WW', '%u' => 'IW', '%V' => 'IW', '%v' => 'IW',
            '%X' => 'YYYY', '%x' => 'YY', '%f' => 'US',
        ]);

        $rw->add(\sprintf("TO_CHAR(%s, '%s')", $dateExpr, $format));

        return true;
    }

    private function transformFromUnixtime(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf('TO_TIMESTAMP(%s)', $expr));

        return true;
    }

    private function transformLeftFunc(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $strExpr = $this->transformArgExpression($args[0]);
        $lenExpr = $this->transformArgExpression($args[1]);
        $rw->add(\sprintf('SUBSTRING(%s FROM 1 FOR %s)', $strExpr, $lenExpr));

        return true;
    }

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
     * CONCAT(a, b, c) → PostgreSQL supports CONCAT natively, but pass through.
     * We handle it here for consistency with the structural transform pattern.
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

        $rw->add('CONCAT(' . implode(', ', $parts) . ')');

        return true;
    }

    /**
     * CONCAT_WS(sep, a, b) → PostgreSQL supports CONCAT_WS natively.
     */
    private function transformConcatWs(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $parts = [];
        foreach ($args as $arg) {
            $parts[] = $this->transformArgExpression($arg);
        }

        $rw->add('CONCAT_WS(' . implode(', ', $parts) . ')');

        return true;
    }

    /**
     * DATEDIFF(d1, d2) → CAST(DATE_PART('day', d1::timestamp - d2::timestamp) AS INTEGER)
     */
    private function transformDatediff(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $d1 = $this->transformArgExpression($args[0]);
        $d2 = $this->transformArgExpression($args[1]);
        $rw->add(\sprintf("CAST(DATE_PART('day', %s::timestamp - %s::timestamp) AS INTEGER)", $d1, $d2));

        return true;
    }

    /**
     * MONTH(d) → EXTRACT(MONTH FROM d)
     * YEAR(d)  → EXTRACT(YEAR FROM d)
     * etc.
     */
    private function transformDateExtract(QueryRewriter $rw, string $field): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf('EXTRACT(%s FROM %s)', $field, $expr));

        return true;
    }

    /**
     * DAYOFWEEK(d) → EXTRACT(DOW FROM d) + 1
     * MySQL: 1=Sunday, 7=Saturday; PostgreSQL DOW: 0=Sunday, 6=Saturday
     */
    private function transformDayOfWeek(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf('(EXTRACT(DOW FROM %s) + 1)', $expr));

        return true;
    }

    /**
     * WEEKDAY(d) → EXTRACT(ISODOW FROM d) - 1
     * MySQL: 0=Monday, 6=Sunday; PostgreSQL ISODOW: 1=Monday, 7=Sunday
     */
    private function transformWeekday(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf('(EXTRACT(ISODOW FROM %s) - 1)', $expr));

        return true;
    }

    /**
     * LOCATE(sub, str) → POSITION(sub IN str)
     */
    private function transformLocate(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 2) {
            return false;
        }

        $sub = $this->transformArgExpression($args[0]);
        $str = $this->transformArgExpression($args[1]);
        $rw->add(\sprintf('POSITION(%s IN %s)', $sub, $str));

        return true;
    }

    /**
     * GROUP_CONCAT(expr [SEPARATOR sep]) → STRING_AGG(expr::text, sep)
     */
    private function transformGroupConcat(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || $args === []) {
            return false;
        }

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

        if (!$foundSep && \count($args) >= 2) {
            $sepStr = $this->findStringToken($args[\count($args) - 1]);
            if ($sepStr !== null) {
                $separator = $sepStr->token;
            }
        }

        $expr = $exprTokens !== [] ? $this->transformArgExpression($exprTokens) : $this->transformArgExpression($args[0]);
        $rw->add(\sprintf('STRING_AGG(%s::text, %s)', $expr, $separator));

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
     * WEEK(d [, mode]) → EXTRACT(WEEK FROM d)
     *
     * PostgreSQL's EXTRACT(WEEK) returns the ISO 8601 week number (Monday start).
     * MySQL's mode parameter (0-7) controls Sunday/Monday start and range,
     * but PostgreSQL has no direct equivalent. We always use ISO week.
     */
    private function transformWeek(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $expr = $this->transformArgExpression($args[0]);
        // mode parameter is consumed but PgSQL only supports ISO week (EXTRACT WEEK)

        $rw->add(\sprintf('EXTRACT(WEEK FROM %s)', $expr));

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
     * FIELD(val, 'a', 'b', 'c') → CASE WHEN val='a' THEN 1 WHEN val='b' THEN 2 ... ELSE 0 END
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
     * INSERT ... SET col=val → INSERT INTO t (col) VALUES (val)
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

    /**
     * UNHEX(hex) → decode(hex, 'hex')
     */
    private function transformUnhex(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $rw->add(\sprintf("decode(%s, 'hex')", $this->transformArgExpression($args[0])));

        return true;
    }

    /**
     * TO_BASE64(str) → encode(str::bytea, 'base64')
     */
    private function transformToBase64(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $rw->add(\sprintf("encode(%s::bytea, 'base64')", $this->transformArgExpression($args[0])));

        return true;
    }

    /**
     * FROM_BASE64(str) → decode(str, 'base64')
     */
    private function transformFromBase64(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $rw->add(\sprintf("decode(%s, 'base64')", $this->transformArgExpression($args[0])));

        return true;
    }

    /**
     * INET_ATON(ip) → (ip::inet - '0.0.0.0'::inet)
     */
    private function transformInetAton(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $rw->add(\sprintf("(%s::inet - '0.0.0.0'::inet)", $this->transformArgExpression($args[0])));

        return true;
    }

    /**
     * INET_NTOA(num) → ('0.0.0.0'::inet + num)::text
     */
    private function transformInetNtoa(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || \count($args) < 1) {
            return false;
        }

        $rw->add(\sprintf("('0.0.0.0'::inet + %s)::text", $this->transformArgExpression($args[0])));

        return true;
    }

    /**
     * MySQL LOG(x) = natural log; PostgreSQL LOG(x) = log base 10.
     * LOG(b, x) has the same meaning in both.
     */
    private function transformLog(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || $args === []) {
            return false;
        }

        if (\count($args) === 1) {
            // MySQL LOG(x) = natural log → PostgreSQL LN(x)
            $rw->add('LN(' . $this->transformArgExpression($args[0]) . ')');
        } else {
            // MySQL LOG(b, x) = log base b → PostgreSQL LOG(b, x) — same semantics
            $rw->add('LOG(' . $this->transformArgExpression($args[0]) . ', ' . $this->transformArgExpression($args[1]) . ')');
        }

        return true;
    }

    /**
     * GET_LOCK('name', timeout) → pg_try_advisory_lock(hashtext('name')::bigint)::int
     */
    private function transformGetLock(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || $args === []) {
            return false;
        }

        $name = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf('pg_try_advisory_lock(hashtext(%s)::bigint)::int', $name));

        return true;
    }

    /**
     * RELEASE_LOCK('name') → pg_advisory_unlock(hashtext('name')::bigint)::int
     */
    private function transformReleaseLock(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || $args === []) {
            return false;
        }

        $name = $this->transformArgExpression($args[0]);
        $rw->add(\sprintf('pg_advisory_unlock(hashtext(%s)::bigint)::int', $name));

        return true;
    }

    /**
     * IS_FREE_LOCK('name') → check if advisory lock is free.
     *
     * Try to acquire + immediately release. Returns 1 if free, 0 if held.
     */
    private function transformIsFreeLock(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || $args === []) {
            return false;
        }

        $name = $this->transformArgExpression($args[0]);
        $key = \sprintf('hashtext(%s)::bigint', $name);
        // Try acquire: if success, immediately release and return 1 (free)
        // If fail: return 0 (held)
        $rw->add(\sprintf('(CASE WHEN pg_try_advisory_lock(%s) THEN pg_advisory_unlock(%s)::int ELSE 0 END)', $key, $key));

        return true;
    }

    // ── LIMIT ──

    /**
     * @param int|string $offset
     * @param int|string $rowCount
     */
    private function rewriteLimit(QueryRewriter $rw, int|string $offset, int|string $rowCount): void
    {
        $rw->skip(); // LIMIT

        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Number) {
            $rw->skip();
        }

        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Operator && $next->token === ',') {
            $rw->skip();
            $next = $rw->peek();
            if ($next !== null && $next->type === TokenType::Number) {
                $rw->skip();
            }
        }

        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'OFFSET') {
            $rw->skip();
            $next = $rw->peek();
            if ($next !== null && $next->type === TokenType::Number) {
                $rw->skip();
            }
        }

        $offsetVal = (int) $offset;
        if ($offsetVal === 0) {
            $rw->add('LIMIT ' . $rowCount);
        } else {
            $rw->add('LIMIT ' . $rowCount . ' OFFSET ' . $offset);
        }
    }

    private function rewriteLimitFromTokens(QueryRewriter $rw): void
    {
        $rw->skip();

        $first = $rw->peek();
        if ($first === null || $first->type !== TokenType::Number) {
            $rw->add('LIMIT');

            return;
        }

        $firstNum = $first->token;
        $rw->skip();

        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Operator && $next->token === ',') {
            $rw->skip();
            $second = $rw->peek();
            if ($second !== null && $second->type === TokenType::Number) {
                $secondNum = $second->token;
                $rw->skip();

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

    // ── Argument extraction ──

    /**
     * @return list<list<Token>>|null
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
                    $rw->skip();
                    break;
                }
            } elseif ($token->type === TokenType::Operator && $token->token === ',' && $depth === 1) {
                $rw->skip();
                $argIndex++;
                $args[$argIndex] = [];
                continue;
            }

            $args[$argIndex][] = $rw->skip();
        }

        return $args;
    }

    /**
     * @param list<Token> $tokens
     */
    private function transformArgExpression(array $tokens): string
    {
        $semantic = array_filter($tokens, fn(Token $t) => !$this->isSemanticVoid($t));
        if ($semantic === []) {
            return '';
        }

        $rw = new QueryRewriter($tokens, \count($tokens));

        while ($rw->hasMore()) {
            $this->translateExpression($rw);
        }

        return trim($rw->getResult());
    }

    /**
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
     * @param list<Token> $tokens
     * @return array{string, string}|null
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

    private function rewriteTokens(Parser $parser): string
    {
        $rw = $this->createRewriter($parser);

        while ($rw->hasMore()) {
            $token = $rw->peek();
            if ($token === null) {
                break;
            }

            if ($token->type === TokenType::Keyword && $token->keyword === 'LIMIT') {
                $this->rewriteLimitFromTokens($rw);
                continue;
            }

            $this->translateExpression($rw);
        }

        return $this->postProcessPgsql($rw->getResult());
    }

    private function createRewriter(Parser $parser): QueryRewriter
    {
        return new QueryRewriter($parser->list->tokens, $parser->list->count);
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

    private function translateSystemVariable(string $sql): string
    {
        if (preg_match_all('/@@(?:SESSION\.|GLOBAL\.)?(\w+)/i', $sql, $matches)) {
            $columns = [];
            foreach ($matches[1] as $i => $varName) {
                $value = self::SYSTEM_VARIABLE_DEFAULTS[strtolower($varName)] ?? '';
                $alias = $matches[0][$i];
                $columns[] = \sprintf("'%s' AS \"%s\"", str_replace("'", "''", $value), $alias);
            }

            return 'SELECT ' . implode(', ', $columns);
        }

        return "SELECT '' AS \"@@value\"";
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

        if (preg_match('/^\s*SHOW\s+FULL\s+TABLES\s+LIKE\s+[\'"](.+?)[\'"]\s*$/i', $sql, $m)) {
            return [\sprintf("SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = 'public' AND table_name LIKE '%s'", str_replace("'", "''", $m[1]))];
        }

        if (preg_match('/^\s*SHOW\s+TABLES\s+LIKE\s+[\'"](.+?)[\'"]\s*$/i', $sql, $m)) {
            return [\sprintf("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' AND table_name LIKE '%s'", str_replace("'", "''", $m[1]))];
        }

        if (preg_match('/^\s*SHOW\s+FULL\s+TABLES\s*/i', $sql)) {
            return ["SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = 'public'"];
        }

        if (preg_match('/^\s*SHOW\s+TABLES\s*/i', $sql)) {
            return ["SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"];
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

        if (preg_match('/^\s*SHOW\s+CREATE\s+TABLE\s+[`"]?(\w+)[`"]?\s*/i', $sql, $m)) {
            $t = str_replace("'", "''", $m[1]);

            return [\sprintf(
                "SELECT '%s' AS \"Table\", 'CREATE TABLE \"' || '%s' || '\" (' || "
                . "string_agg('\"' || column_name || '\" ' || data_type || "
                . "CASE WHEN character_maximum_length IS NOT NULL THEN '(' || character_maximum_length || ')' ELSE '' END || "
                . "CASE WHEN is_nullable = 'NO' THEN ' NOT NULL' ELSE '' END || "
                . "CASE WHEN column_default IS NOT NULL THEN ' DEFAULT ' || column_default ELSE '' END"
                . ", ', ' ORDER BY ordinal_position) || ')' AS \"Create Table\" "
                . "FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '%s'",
                $t,
                $t,
                $t,
            )];
        }

        if (preg_match('/^\s*SHOW\s+(?:INDEX|KEYS?)\s+FROM\s+[`"]?(\w+)[`"]?\s*/i', $sql, $m)) {
            return [\sprintf(
                "SELECT indexname AS \"Key_name\", indexdef AS \"Index_type\" FROM pg_indexes WHERE schemaname = 'public' AND tablename = '%s'",
                str_replace("'", "''", $m[1]),
            )];
        }

        if (preg_match('/^\s*SHOW\s+TABLE\s+STATUS\s+LIKE\s+[\'"](.+?)[\'"]\s*$/i', $sql, $m)) {
            return [\sprintf(
                "SELECT t.table_name AS \"Name\", 'InnoDB' AS \"Engine\", 0 AS \"Version\", 'Dynamic' AS \"Row_format\", "
                . "COALESCE(c.reltuples::bigint, 0) AS \"Rows\", 0 AS \"Avg_row_length\", "
                . "COALESCE(pg_total_relation_size(c.oid), 0) AS \"Data_length\", "
                . "0 AS \"Index_length\", '' AS \"Comment\" "
                . "FROM information_schema.tables t LEFT JOIN pg_class c ON c.relname = t.table_name AND c.relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = 'public') "
                . "WHERE t.table_schema = 'public' AND t.table_name LIKE '%s'",
                str_replace("'", "''", $m[1]),
            )];
        }

        if (preg_match('/^\s*SHOW\s+TABLE\s+STATUS/i', $sql)) {
            return ["SELECT t.table_name AS \"Name\", 'InnoDB' AS \"Engine\", 0 AS \"Version\", 'Dynamic' AS \"Row_format\", "
                . "COALESCE(c.reltuples::bigint, 0) AS \"Rows\", 0 AS \"Avg_row_length\", "
                . "COALESCE(pg_total_relation_size(c.oid), 0) AS \"Data_length\", "
                . "0 AS \"Index_length\", '' AS \"Comment\" "
                . "FROM information_schema.tables t LEFT JOIN pg_class c ON c.relname = t.table_name AND c.relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = 'public') "
                . "WHERE t.table_schema = 'public'"];
        }

        if (preg_match('/^\s*DESCRIBE\s+[`"]?(\w+)[`"]?\s*/i', $sql, $m)) {
            return [\sprintf(
                "SELECT column_name AS \"Field\", data_type AS \"Type\" FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '%s'",
                $m[1],
            )];
        }

        // SHOW GRANTS → dummy
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
