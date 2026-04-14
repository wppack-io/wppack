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

        foreach (self::IGNORED_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return [];
            }
        }

        if ($result = $this->translateMetaCommand($trimmed)) {
            return $result;
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
            $stmt instanceof CreateStatement => [$this->translateCreate($stmt, $parser)],
            $stmt instanceof TruncateStatement => [$this->translateTruncate($stmt, $parser)],
            $stmt instanceof AlterStatement => [$this->rewriteTokens($parser)],
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

            // SQL_CALC_FOUND_ROWS → skip
            if ($token->type === TokenType::Keyword && $token->keyword === 'SQL_CALC_FOUND_ROWS') {
                $rw->skip();
                continue;
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

            // INSERT IGNORE → INSERT ... ON CONFLICT DO NOTHING
            if ($token->type === TokenType::Keyword && $token->keyword === 'IGNORE' && $hasIgnore) {
                $rw->skip(); // skip IGNORE
                continue;
            }

            // ON DUPLICATE KEY UPDATE → ON CONFLICT DO UPDATE SET
            if ($token->type === TokenType::Keyword && $token->keyword === 'ON' && $hasOnDuplicate && !$inOnConflictUpdate) {
                $next = $rw->peekNth(2);
                if ($next !== null && $next->keyword === 'DUPLICATE') {
                    $rw->skip(); // ON
                    $rw->skip(); // DUPLICATE
                    $rw->skip(); // KEY
                    $rw->skip(); // UPDATE
                    $rw->add('ON CONFLICT DO UPDATE SET');
                    $inOnConflictUpdate = true;
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

        return [$result];
    }

    /**
     * REPLACE INTO → INSERT ... ON CONFLICT DO UPDATE SET (all columns).
     *
     * PostgreSQL doesn't have REPLACE. We rewrite to INSERT with a
     * blanket ON CONFLICT DO UPDATE covering all non-PK columns.
     * Without PK info, we use ON CONFLICT DO NOTHING as fallback.
     */
    private function translateReplace(Parser $parser): string
    {
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

        return $rw->getResult();
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

        return $this->rewriteTokens($parser);
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

    private function translateTruncate(TruncateStatement $stmt, Parser $parser): string
    {
        $rw = $this->createRewriter($parser);
        $rw->consumeAll();

        return $rw->getResult();
    }

    // ── DDL handlers ──

    /**
     * Translate CREATE TABLE using AST CreateDefinition[] directly.
     *
     * For CREATE INDEX / CREATE VIEW, falls back to token rewriting.
     */
    private function translateCreate(CreateStatement $stmt, Parser $parser): string
    {
        if (!\is_array($stmt->fields) || $stmt->fields === []) {
            return $this->rewriteTokens($parser);
        }

        return $this->buildCreateTable($stmt);
    }

    private function buildCreateTable(CreateStatement $stmt): string
    {
        $tableName = $this->quoteId($stmt->name->table ?? '');
        $ifNotExists = ($stmt->options?->has('IF NOT EXISTS')) ? 'IF NOT EXISTS ' : '';

        $parts = [];

        foreach ($stmt->fields as $field) {
            if ($field->type !== null) {
                $parts[] = $this->buildColumnDef($field);
            } elseif ($field->key !== null) {
                $parts[] = $this->buildKeyDef($field->key);
            }
        }

        return \sprintf("CREATE TABLE %s%s (%s)", $ifNotExists, $tableName, implode(', ', $parts));
    }

    private function buildColumnDef(\PhpMyAdmin\SqlParser\Components\CreateDefinition $field): string
    {
        $name = $this->quoteId($field->name ?? '');
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

        // DEFAULT
        $defaultExpr = $field->options?->get('DEFAULT', true);
        if ($defaultExpr instanceof \PhpMyAdmin\SqlParser\Components\Expression && $defaultExpr->expr !== null && $defaultExpr->expr !== '') {
            $clauses[] = 'DEFAULT ' . $defaultExpr->expr;
        }

        return implode(' ', $clauses);
    }

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

        // ── REGEXP → ~* ──
        if ($kw === 'REGEXP') {
            $rw->skip();
            $rw->add('~*');

            return;
        }

        // ── LIKE with escaped wildcards → ESCAPE clause ──
        if ($kw === 'LIKE') {
            $rw->consume();
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

            return;
        }

        // ── SIGNED → INTEGER ──
        if ($kw === 'SIGNED') {
            $rw->skip();
            $rw->add('INTEGER');

            return;
        }

        // ── BINARY → BYTEA ──
        if ($kw === 'BINARY') {
            $rw->skip();
            $rw->add('BYTEA');

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
            'WEEK' => $this->transformDateExtract($rw, 'WEEK'),
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

        $format = str_replace(
            ['%Y', '%y', '%m', '%c', '%d', '%e', '%H', '%h', '%I', '%i', '%s', '%S',
             '%j', '%W', '%w', '%p', '%T', '%r', '%a', '%b', '%M'],
            ['YYYY', 'YY', 'MM', 'FMMM', 'DD', 'FMDD', 'HH24', 'HH12', 'HH12', 'MI', 'SS', 'SS',
             'DDD', 'Day', 'D', 'AM', 'HH24:MI:SS', 'HH12:MI:SS AM', 'Dy', 'Mon', 'FMMonth'],
            (string) $formatToken->value,
        );

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
     * GROUP_CONCAT(col SEPARATOR sep) → STRING_AGG(col, sep)
     */
    private function transformGroupConcat(QueryRewriter $rw): bool
    {
        $args = $this->extractFunctionArgs($rw);
        if ($args === null || $args === []) {
            return false;
        }

        // GROUP_CONCAT may have SEPARATOR keyword in its arguments
        $expr = $this->transformArgExpression($args[0]);
        $separator = "','";

        // Check for SEPARATOR keyword in remaining args
        if (\count($args) >= 2) {
            $sepTokens = $args[\count($args) - 1];
            $sepStr = $this->findStringToken($sepTokens);
            if ($sepStr !== null) {
                $separator = $sepStr->token;
            }
        }

        $rw->add(\sprintf('STRING_AGG(%s, %s)', $expr, $separator));

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
            return [\sprintf("SELECT table_name AS \"Name\" FROM information_schema.tables WHERE table_schema = 'public' AND table_name LIKE '%s'", str_replace("'", "''", $m[1]))];
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
