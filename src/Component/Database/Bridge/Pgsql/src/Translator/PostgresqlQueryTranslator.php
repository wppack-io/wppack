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
        '/^\s*CHECK\s+TABLE\s+/i',
        '/^\s*REPAIR\s+TABLE\s+/i',
        '/^\s*CREATE\s+DATABASE\b/i',
        '/^\s*DROP\s+DATABASE\b/i',
    ];

    /** @var array<string, string> */
    private const ZERO_ARG_MAP = [
        'CURDATE' => 'CURRENT_DATE',
        'CURTIME' => 'CURRENT_TIME',
        'UNIX_TIMESTAMP' => 'EXTRACT(EPOCH FROM NOW())::INTEGER',
        'DATABASE' => 'CURRENT_DATABASE()',
        'FOUND_ROWS' => '-1',
    ];

    /** @var array<string, string> */
    private const RENAME_MAP = [
        'RAND' => 'random',
        'IFNULL' => 'COALESCE',
        'LAST_INSERT_ID' => 'lastval',
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

        $parser = new Parser($sql);
        $stmt = $parser->statements[0] ?? null;

        if ($stmt === null) {
            return [$this->rewriteTokens($parser)];
        }

        return match (true) {
            $stmt instanceof SelectStatement => [$this->translateSelect($stmt, $parser)],
            $stmt instanceof InsertStatement => $this->translateInsert($stmt, $parser),
            $stmt instanceof UpdateStatement => [$this->rewriteTokens($parser)],
            $stmt instanceof DeleteStatement => [$this->rewriteTokens($parser)],
            $stmt instanceof CreateStatement => [$this->translateCreate($parser)],
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

    private function translateTruncate(TruncateStatement $stmt, Parser $parser): string
    {
        $rw = $this->createRewriter($parser);
        $rw->consumeAll();

        return $rw->getResult();
    }

    // ── DDL handlers ──

    private function translateCreate(Parser $parser): string
    {
        $rw = $this->createRewriter($parser);

        while ($rw->hasMore()) {
            $token = $rw->peek();
            if ($token === null) {
                break;
            }

            // Data type keywords
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
                    $rw->skip(); // AUTO_INCREMENT
                    $rw->skip(); // =
                    $rw->skip(); // N
                } else {
                    $rw->skip();
                    $rw->add('SERIAL');
                }
                continue;
            }

            // MySQL clauses → skip
            if ($token->type === TokenType::Keyword
                && \in_array($token->keyword, ['ENGINE', 'DEFAULT CHARSET', 'COLLATE', 'CHARACTER SET'], true)) {
                $this->skipMysqlClause($rw);
                continue;
            }

            if ($token->type === TokenType::Keyword && $token->keyword === 'CHARACTER') {
                $next = $rw->peekNth(2);
                if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'SET') {
                    $this->skipMysqlClause($rw);
                    continue;
                }
            }

            $rw->consume();
        }

        return $rw->getResult();
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

        // ── SIGNED → INTEGER ──
        if ($kw === 'SIGNED') {
            $rw->skip();
            $rw->add('INTEGER');

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
            ['%Y', '%m', '%d', '%H', '%i', '%s'],
            ['YYYY', 'MM', 'DD', 'HH24', 'MI', 'SS'],
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

    private function rewriteDataType(QueryRewriter $rw): void
    {
        $token = $rw->peek();
        if ($token === null) {
            return;
        }

        $kw = $token->keyword ?? '';

        // Types that PostgreSQL supports natively with (N) — pass through
        if (\in_array($kw, ['VARCHAR', 'CHAR', 'DECIMAL', 'NUMERIC', 'REAL'], true)) {
            $rw->consume();

            return;
        }

        // Types that are valid in PostgreSQL — uppercase, strip (N)
        if (\in_array($kw, ['TEXT', 'DATE', 'TIME', 'TIMESTAMP', 'BOOLEAN', 'INTEGER', 'SMALLINT', 'BIGINT'], true)) {
            $rw->skip();
            // Skip trailing (N)
            $next = $rw->peek();
            if ($next !== null && $next->type === TokenType::Operator && $next->token === '(') {
                $this->skipMatchingParen($rw);
            }
            $rw->add($kw);

            return;
        }

        // Types that need conversion
        $pgsqlType = match (true) {
            $kw === 'TINYINT' => 'SMALLINT',
            \in_array($kw, ['MEDIUMINT', 'INT'], true) => 'INTEGER',
            $kw === 'DOUBLE' => 'DOUBLE PRECISION',
            $kw === 'FLOAT' => 'REAL',
            $kw === 'DATETIME' => 'TIMESTAMP',
            \in_array($kw, ['TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT'], true) => 'TEXT',
            \in_array($kw, ['TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB', 'BLOB'], true) => 'BYTEA',
            \in_array($kw, ['VARBINARY', 'BINARY'], true) => 'BYTEA',
            $kw === 'ENUM' => 'TEXT',
            $kw === 'JSON' => 'JSONB',
            default => null,
        };

        if ($pgsqlType === null) {
            $rw->consume();

            return;
        }

        $rw->skip();
        // Skip trailing (N) or ENUM(...)
        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Operator && $next->token === '(') {
            $this->skipMatchingParen($rw);
        }
        $rw->add($pgsqlType);
    }

    private function skipMysqlClause(QueryRewriter $rw): void
    {
        $rw->skip(); // keyword

        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Keyword && $next->keyword === 'SET') {
            $rw->skip();
        }

        $next = $rw->peek();
        if ($next !== null && $next->type === TokenType::Operator && $next->token === '=') {
            $rw->skip();
            $rw->skip(); // value
        } elseif ($next !== null
            && \in_array($next->type, [TokenType::None, TokenType::String, TokenType::Number], true)) {
            $rw->skip();
        }
    }

    private function skipMatchingParen(QueryRewriter $rw): void
    {
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
