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
            $stmt instanceof UpdateStatement => [$this->translateUpdate($parser)],
            $stmt instanceof DeleteStatement => [$this->translateDelete($parser)],
            $stmt instanceof CreateStatement => [$this->translateCreate($stmt, $parser)],
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

    private function translateUpdate(Parser $parser): string
    {
        return $this->rewriteTokens($parser);
    }

    private function translateDelete(Parser $parser): string
    {
        return $this->rewriteTokens($parser);
    }

    // ── DDL handlers ──

    private function translateCreate(CreateStatement $stmt, Parser $parser): string
    {
        $rw = $this->createRewriter($parser);

        while ($rw->hasMore()) {
            $token = $rw->peek();
            if ($token === null) {
                break;
            }

            // Data type keywords → SQLite type
            if ($token->type === TokenType::Keyword
                && ($token->flags & Token::FLAG_KEYWORD_DATA_TYPE) !== 0
                && $token->keyword !== 'INTERVAL') {
                $this->rewriteDataType($rw);
                continue;
            }

            // UNSIGNED → skip
            if ($token->type === TokenType::Keyword && $token->keyword === 'UNSIGNED') {
                $rw->skip();
                continue;
            }

            // AUTO_INCREMENT
            if ($token->type === TokenType::Keyword && $token->keyword === 'AUTO_INCREMENT') {
                $next = $rw->peekNth(2);
                if ($next !== null && $next->type === TokenType::Operator && $next->token === '=') {
                    // Table-level AUTO_INCREMENT=N → skip
                    $rw->skip(); // AUTO_INCREMENT
                    $rw->skip(); // =
                    $rw->skip(); // N
                } else {
                    $rw->skip();
                    $rw->add('AUTOINCREMENT');
                }
                continue;
            }

            // MySQL-specific clauses → skip
            if ($token->type === TokenType::Keyword
                && \in_array($token->keyword, ['ENGINE', 'DEFAULT CHARSET', 'COLLATE', 'CHARACTER SET'], true)) {
                $this->skipMysqlClause($rw);
                continue;
            }

            // CHARACTER (might be followed by SET)
            if ($token->type === TokenType::Keyword && $token->keyword === 'CHARACTER') {
                $next = $rw->peekNth(2);
                if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'SET') {
                    $this->skipMysqlClause($rw);
                    continue;
                }
            }

            // String literals, identifiers, etc. → consume as-is
            $rw->consume();
        }

        return $this->mergeAutoincrementPrimaryKey($rw->getResult());
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
            'IF' => $this->transformIfFunc($rw),
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

    private function rewriteDataType(QueryRewriter $rw): void
    {
        $token = $rw->peek();
        if ($token === null) {
            return;
        }

        $kw = $token->keyword ?? '';

        // Determine SQLite type
        $sqliteType = match (true) {
            \in_array($kw, ['BIGINT', 'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BOOLEAN'], true) => 'INTEGER',
            \in_array($kw, ['VARCHAR', 'CHAR', 'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT', 'ENUM', 'SET'], true) => 'TEXT',
            \in_array($kw, ['DATETIME', 'TIMESTAMP', 'DATE', 'TIME'], true) => 'TEXT',
            $kw === 'JSON' => 'TEXT',
            \in_array($kw, ['FLOAT', 'DOUBLE', 'DECIMAL', 'NUMERIC', 'REAL'], true) => 'REAL',
            \in_array($kw, ['BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB', 'VARBINARY', 'BINARY'], true) => 'BLOB',
            default => null,
        };

        if ($sqliteType === null) {
            $rw->consume();

            return;
        }

        $rw->skip(); // skip type keyword

        // Skip trailing (N) if present
        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Operator && $next->token === '(') {
            // Skip through matching )
            $depth = 0;
            while ($rw->hasMore()) {
                $t = $rw->skip();
                if ($t === null) {
                    break;
                }
                if ($t->token === '(') {
                    $depth++;
                } elseif ($t->token === ')') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }
        }

        $rw->add($sqliteType);
    }

    private function skipMysqlClause(QueryRewriter $rw): void
    {
        $rw->skip(); // keyword (ENGINE, COLLATE, etc.)

        // Check for SET (CHARACTER SET)
        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'SET') {
            $rw->skip();
        }

        // Check for = value
        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Operator && $next->token === '=') {
            $rw->skip(); // =
            $rw->skip(); // value
        } elseif ($next !== null
            && \in_array($next->type, [TokenType::None, TokenType::String, TokenType::Number], true)) {
            $rw->skip(); // value without =
        }
    }

    private function mergeAutoincrementPrimaryKey(string $sql): string
    {
        if (!str_contains($sql, 'AUTOINCREMENT')
            || !preg_match('/PRIMARY\s+KEY\s*\(\s*"?(\w+)"?\s*\)/i', $sql, $pkMatch)) {
            return $sql;
        }

        $pkCol = $pkMatch[1];

        $sql = (string) preg_replace(
            '/("?' . preg_quote($pkCol, '/') . '"?\s+INTEGER\b[^,]*?)\bAUTOINCREMENT\b/i',
            '$1PRIMARY KEY AUTOINCREMENT',
            $sql,
        );

        $sql = (string) preg_replace('/,?\s*PRIMARY\s+KEY\s*\([^)]+\)/i', '', $sql);

        return $sql;
    }

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
