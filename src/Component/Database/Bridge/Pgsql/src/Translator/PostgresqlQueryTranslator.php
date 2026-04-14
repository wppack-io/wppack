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

use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Token;
use PhpMyAdmin\SqlParser\TokenType;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Translates MySQL SQL to PostgreSQL SQL using token-stream walking.
 *
 * Uses phpmyadmin/sql-parser's Lexer to tokenize the input SQL, then walks
 * the token stream applying transformations. String literals (TokenType::String)
 * are always passed through unchanged, making this approach inherently safe
 * from string-literal corruption.
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

    /**
     * Zero-argument function replacements.
     *
     * @var array<string, string>
     */
    private const ZERO_ARG_MAP = [
        'CURDATE' => 'CURRENT_DATE',
        'CURTIME' => 'CURRENT_TIME',
        'UNIX_TIMESTAMP' => 'EXTRACT(EPOCH FROM NOW())::INTEGER',
        'DATABASE' => 'CURRENT_DATABASE()',
        'FOUND_ROWS' => '-1',
    ];

    /**
     * Function rename map.
     *
     * @var array<string, string>
     */
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

        $lexer = new Lexer($sql);
        /** @var list<Token> $tokens */
        $tokens = $lexer->list->tokens;
        $count = $lexer->list->count;

        $firstKw = $this->findFirstKeyword($tokens, $count);

        if ($firstKw === 'CREATE') {
            return [$this->transformCreateTokens($tokens, $count)];
        }

        // SET statement (not caught by IGNORED_PATTERNS)
        if ($firstKw === 'SET') {
            return [];
        }

        $result = $this->transformRange($tokens, 0, $count);

        if ($result === '') {
            return [];
        }

        return [$result];
    }

    // ── Token stream transformation (DML) ──

    /**
     * @param list<Token> $tokens
     */
    private function transformRange(array $tokens, int $start, int $end): string
    {
        $output = '';
        $i = $start;
        $inOnConflictUpdate = false;

        while ($i < $end) {
            $token = $tokens[$i];

            if ($token->type === TokenType::Delimiter) {
                $i++;
                continue;
            }

            // String literals — pass through unchanged
            if ($token->type === TokenType::String) {
                $output .= $token->token;
                $i++;
                continue;
            }

            // Backtick identifiers → double-quoted
            if ($token->type === TokenType::Symbol
                && ($token->flags & Token::FLAG_SYMBOL_BACKTICK) !== 0) {
                $output .= '"' . str_replace('"', '""', (string) $token->value) . '"';
                $i++;
                continue;
            }

            if ($token->type === TokenType::Keyword && $token->keyword !== null) {
                $kw = $token->keyword;

                // ── DML transforms ──

                // INSERT ... IGNORE → INSERT INTO ... ON CONFLICT DO NOTHING
                if ($kw === 'INSERT') {
                    $nextIdx = $this->findNextNonWhitespace($tokens, $i + 1, $end);
                    if ($nextIdx !== null
                        && $tokens[$nextIdx]->type === TokenType::Keyword
                        && $tokens[$nextIdx]->keyword === 'IGNORE') {
                        $output .= 'INSERT';
                        // Skip IGNORE, will be handled at end
                        $i = $nextIdx + 1;
                        // Find statement end and append ON CONFLICT DO NOTHING
                        $rest = $this->transformRange($tokens, $i, $end);
                        $output .= $rest;
                        // Append ON CONFLICT DO NOTHING
                        $output = rtrim($output, " \t\n\r;") . ' ON CONFLICT DO NOTHING';

                        return $output;
                    }
                }

                // ON DUPLICATE KEY UPDATE → ON CONFLICT DO UPDATE SET
                if ($kw === 'ON') {
                    $lastIdx = $this->matchKeywordSequence($tokens, $i, $end, ['DUPLICATE', 'KEY', 'UPDATE']);
                    if ($lastIdx !== null) {
                        $output .= 'ON CONFLICT DO UPDATE SET';
                        $i = $lastIdx + 1;
                        $inOnConflictUpdate = true;
                        continue;
                    }
                }

                // VALUES(col) in ON CONFLICT context → excluded.col
                if ($inOnConflictUpdate && $kw === 'VALUES'
                    && $this->isFollowedByOpenParen($tokens, $i, $end)) {
                    $openIdx = $this->findNextNonWhitespace($tokens, $i + 1, $end);
                    if ($openIdx !== null) {
                        $closeIdx = $this->findMatchingParen($tokens, $openIdx, $end);
                        if ($closeIdx !== null) {
                            $inner = trim($this->buildRawRange($tokens, $openIdx + 1, $closeIdx));
                            $output .= 'excluded.' . $inner;
                            $i = $closeIdx + 1;
                            continue;
                        }
                    }
                }

                // ── Zero-arg functions ──
                if (isset(self::ZERO_ARG_MAP[$kw])
                    && $this->isFollowedByEmptyParens($tokens, $i, $end)) {
                    $output .= self::ZERO_ARG_MAP[$kw];
                    $i = $this->skipPastParens($tokens, $i + 1, $end);
                    continue;
                }

                // ── Function renames ──
                if (isset(self::RENAME_MAP[$kw])
                    && $this->isFollowedByOpenParen($tokens, $i, $end)) {
                    $output .= self::RENAME_MAP[$kw];
                    $i++;
                    continue;
                }

                // ── Structural transforms ──
                if ($this->isFollowedByOpenParen($tokens, $i, $end)) {
                    $consumed = $this->tryStructuralTransform($tokens, $i, $end, $replacement);
                    if ($consumed > 0) {
                        $output .= $replacement;
                        $i += $consumed;
                        continue;
                    }
                }

                // ── LIMIT offset,count → LIMIT count OFFSET offset ──
                if ($kw === 'LIMIT') {
                    $consumed = 0;
                    $output .= $this->transformLimit($tokens, $i, $end, $consumed);
                    $i += $consumed;
                    continue;
                }

                // ── REGEXP → ~* ──
                if ($kw === 'REGEXP') {
                    $output .= '~*';
                    $i++;
                    continue;
                }

                // ── CAST(x AS SIGNED) → CAST(x AS INTEGER) ──
                if ($kw === 'SIGNED') {
                    $output .= 'INTEGER';
                    $i++;
                    continue;
                }
            }

            // Default
            $output .= $token->token;
            $i++;
        }

        return $output;
    }

    // ── Structural transforms ──

    /**
     * @param list<Token> $tokens
     */
    private function tryStructuralTransform(array $tokens, int $i, int $end, ?string &$out): int
    {
        $kw = $tokens[$i]->keyword;

        return match ($kw) {
            'DATE_ADD' => $this->transformDateAddSub($tokens, $i, $end, $out, '+'),
            'DATE_SUB' => $this->transformDateAddSub($tokens, $i, $end, $out, '-'),
            'DATE_FORMAT' => $this->transformDateFormat($tokens, $i, $end, $out),
            'FROM_UNIXTIME' => $this->transformFromUnixtime($tokens, $i, $end, $out),
            'LEFT' => $this->transformLeftFunc($tokens, $i, $end, $out),
            'IF' => $this->transformIfFunc($tokens, $i, $end, $out),
            default => 0,
        };
    }

    /**
     * DATE_ADD(d, INTERVAL n unit) → d + INTERVAL 'n unit'
     * DATE_SUB(d, INTERVAL n unit) → d - INTERVAL 'n unit'
     *
     * @param list<Token> $tokens
     */
    private function transformDateAddSub(array $tokens, int $i, int $end, ?string &$out, string $sign): int
    {
        $openIdx = $this->findNextNonWhitespace($tokens, $i + 1, $end);
        if ($openIdx === null || $tokens[$openIdx]->token !== '(') {
            return 0;
        }

        $closeIdx = $this->findMatchingParen($tokens, $openIdx, $end);
        if ($closeIdx === null) {
            return 0;
        }

        $args = $this->splitArguments($tokens, $openIdx + 1, $closeIdx);
        if (\count($args) < 2) {
            return 0;
        }

        $dateExpr = trim($this->transformRange($tokens, $args[0][0], $args[0][1] + 1));
        $interval = $this->extractInterval($tokens, $args[1][0], $args[1][1]);

        if ($interval === null) {
            return 0;
        }

        [$number, $unit] = $interval;
        $out = \sprintf("%s %s INTERVAL '%s %s'", $dateExpr, $sign, $number, strtolower($unit));

        return $closeIdx - $i + 1;
    }

    /**
     * DATE_FORMAT(d, 'format') → TO_CHAR(d, 'converted_format')
     *
     * @param list<Token> $tokens
     */
    private function transformDateFormat(array $tokens, int $i, int $end, ?string &$out): int
    {
        $openIdx = $this->findNextNonWhitespace($tokens, $i + 1, $end);
        if ($openIdx === null || $tokens[$openIdx]->token !== '(') {
            return 0;
        }

        $closeIdx = $this->findMatchingParen($tokens, $openIdx, $end);
        if ($closeIdx === null) {
            return 0;
        }

        $args = $this->splitArguments($tokens, $openIdx + 1, $closeIdx);
        if (\count($args) < 2) {
            return 0;
        }

        $dateExpr = trim($this->transformRange($tokens, $args[0][0], $args[0][1] + 1));
        $formatStr = $this->extractStringLiteral($tokens, $args[1][0], $args[1][1]);

        if ($formatStr === null) {
            return 0;
        }

        $format = str_replace(
            ['%Y', '%m', '%d', '%H', '%i', '%s'],
            ['YYYY', 'MM', 'DD', 'HH24', 'MI', 'SS'],
            $formatStr,
        );

        $out = \sprintf("TO_CHAR(%s, '%s')", $dateExpr, $format);

        return $closeIdx - $i + 1;
    }

    /**
     * FROM_UNIXTIME(t) → TO_TIMESTAMP(t)
     *
     * @param list<Token> $tokens
     */
    private function transformFromUnixtime(array $tokens, int $i, int $end, ?string &$out): int
    {
        $openIdx = $this->findNextNonWhitespace($tokens, $i + 1, $end);
        if ($openIdx === null || $tokens[$openIdx]->token !== '(') {
            return 0;
        }

        $closeIdx = $this->findMatchingParen($tokens, $openIdx, $end);
        if ($closeIdx === null) {
            return 0;
        }

        $inner = trim($this->transformRange($tokens, $openIdx + 1, $closeIdx));
        $out = \sprintf('TO_TIMESTAMP(%s)', $inner);

        return $closeIdx - $i + 1;
    }

    /**
     * LEFT(s, n) → SUBSTRING(s FROM 1 FOR n)
     *
     * @param list<Token> $tokens
     */
    private function transformLeftFunc(array $tokens, int $i, int $end, ?string &$out): int
    {
        $openIdx = $this->findNextNonWhitespace($tokens, $i + 1, $end);
        if ($openIdx === null || $tokens[$openIdx]->token !== '(') {
            return 0;
        }

        $closeIdx = $this->findMatchingParen($tokens, $openIdx, $end);
        if ($closeIdx === null) {
            return 0;
        }

        $args = $this->splitArguments($tokens, $openIdx + 1, $closeIdx);
        if (\count($args) < 2) {
            return 0;
        }

        $strExpr = trim($this->transformRange($tokens, $args[0][0], $args[0][1] + 1));
        $lenExpr = trim($this->transformRange($tokens, $args[1][0], $args[1][1] + 1));

        $out = \sprintf('SUBSTRING(%s FROM 1 FOR %s)', $strExpr, $lenExpr);

        return $closeIdx - $i + 1;
    }

    /**
     * IF(cond, t, f) → CASE WHEN cond THEN t ELSE f END
     *
     * @param list<Token> $tokens
     */
    private function transformIfFunc(array $tokens, int $i, int $end, ?string &$out): int
    {
        $openIdx = $this->findNextNonWhitespace($tokens, $i + 1, $end);
        if ($openIdx === null || $tokens[$openIdx]->token !== '(') {
            return 0;
        }

        $closeIdx = $this->findMatchingParen($tokens, $openIdx, $end);
        if ($closeIdx === null) {
            return 0;
        }

        $args = $this->splitArguments($tokens, $openIdx + 1, $closeIdx);
        if (\count($args) < 3) {
            return 0;
        }

        $cond = trim($this->transformRange($tokens, $args[0][0], $args[0][1] + 1));
        $trueVal = trim($this->transformRange($tokens, $args[1][0], $args[1][1] + 1));
        $falseVal = trim($this->transformRange($tokens, $args[2][0], $args[2][1] + 1));

        $out = \sprintf('CASE WHEN %s THEN %s ELSE %s END', $cond, $trueVal, $falseVal);

        return $closeIdx - $i + 1;
    }

    // ── LIMIT ──

    /**
     * @param list<Token> $tokens
     */
    private function transformLimit(array $tokens, int $i, int $end, int &$consumed): string
    {
        $firstNumIdx = null;

        for ($j = $i + 1; $j < $end; $j++) {
            if ($tokens[$j]->type === TokenType::Number) {
                $firstNumIdx = $j;
                break;
            }
            if ($tokens[$j]->type !== TokenType::Whitespace) {
                break;
            }
        }

        if ($firstNumIdx === null) {
            $consumed = 1;

            return 'LIMIT';
        }

        $afterFirst = $this->findNextNonWhitespace($tokens, $firstNumIdx + 1, $end);

        if ($afterFirst !== null
            && $tokens[$afterFirst]->type === TokenType::Operator
            && $tokens[$afterFirst]->token === ',') {
            $secondNumIdx = null;

            for ($j = $afterFirst + 1; $j < $end; $j++) {
                if ($tokens[$j]->type === TokenType::Number) {
                    $secondNumIdx = $j;
                    break;
                }
                if ($tokens[$j]->type !== TokenType::Whitespace) {
                    break;
                }
            }

            if ($secondNumIdx !== null) {
                $offset = $tokens[$firstNumIdx]->token;
                $limitCount = $tokens[$secondNumIdx]->token;
                $consumed = $secondNumIdx - $i + 1;

                if ($offset === '0') {
                    return 'LIMIT ' . $limitCount;
                }

                return 'LIMIT ' . $limitCount . ' OFFSET ' . $offset;
            }
        }

        $result = '';

        for ($j = $i; $j <= $firstNumIdx; $j++) {
            $result .= $tokens[$j]->token;
        }

        $consumed = $firstNumIdx - $i + 1;

        return $result;
    }

    // ── DDL (CREATE TABLE) ──

    /**
     * @param list<Token> $tokens
     */
    private function transformCreateTokens(array $tokens, int $count): string
    {
        $output = '';
        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i];

            if ($token->type === TokenType::Delimiter) {
                $i++;
                continue;
            }

            if ($token->type === TokenType::String) {
                $output .= $token->token;
                $i++;
                continue;
            }

            if ($token->type === TokenType::Symbol
                && ($token->flags & Token::FLAG_SYMBOL_BACKTICK) !== 0) {
                $output .= '"' . str_replace('"', '""', (string) $token->value) . '"';
                $i++;
                continue;
            }

            if ($token->type === TokenType::Keyword && $token->keyword !== null) {
                $kw = $token->keyword;

                // Data type keywords → PostgreSQL types
                if (($token->flags & Token::FLAG_KEYWORD_DATA_TYPE) !== 0
                    && $kw !== 'INTERVAL') {
                    $consumed = 0;
                    $output .= $this->transformDataType($tokens, $i, $count, $consumed);
                    $i += $consumed;
                    continue;
                }

                // UNSIGNED → skip
                if ($kw === 'UNSIGNED') {
                    $i++;
                    continue;
                }

                // AUTO_INCREMENT → SERIAL (column) or skip =N (table)
                if ($kw === 'AUTO_INCREMENT') {
                    $next = $this->findNextNonWhitespace($tokens, $i + 1, $count);
                    if ($next !== null && $tokens[$next]->type === TokenType::Operator && $tokens[$next]->token === '=') {
                        $i = $this->skipMysqlClause($tokens, $i, $count);
                    } else {
                        $output .= 'SERIAL';
                        $i++;
                    }
                    continue;
                }

                // MySQL-specific clauses → skip
                if (\in_array($kw, ['ENGINE', 'DEFAULT CHARSET', 'COLLATE', 'CHARACTER SET'], true)) {
                    $i = $this->skipMysqlClause($tokens, $i, $count);
                    continue;
                }

                if ($kw === 'CHARACTER') {
                    $next = $this->findNextNonWhitespace($tokens, $i + 1, $count);
                    if ($next !== null && $tokens[$next]->type === TokenType::Keyword
                        && $tokens[$next]->keyword === 'SET') {
                        $i = $this->skipMysqlClause($tokens, $i, $count);
                        continue;
                    }
                }
            }

            $output .= $token->token;
            $i++;
        }

        return $output;
    }

    /**
     * @param list<Token> $tokens
     */
    private function transformDataType(array $tokens, int $i, int $count, int &$consumed): string
    {
        $kw = $tokens[$i]->keyword ?? '';
        $consumed = 1;

        // Detect trailing (N) if present
        $closeIdx = null;
        $next = $this->findNextNonWhitespace($tokens, $i + 1, $count);
        if ($next !== null && $tokens[$next]->type === TokenType::Operator && $tokens[$next]->token === '(') {
            $closeIdx = $this->findMatchingParen($tokens, $next, $count);
        }

        // Types that PostgreSQL supports natively with (N) — keep as-is, don't consume (N)
        if (\in_array($kw, ['VARCHAR', 'CHAR', 'DECIMAL', 'NUMERIC', 'REAL'], true)) {
            return $tokens[$i]->token;
        }

        // Types that are valid in PostgreSQL — just uppercase, strip (N) if present
        if (\in_array($kw, ['TEXT', 'DATE', 'TIME', 'TIMESTAMP', 'BOOLEAN', 'INTEGER', 'SMALLINT', 'BIGINT'], true)) {
            if ($closeIdx !== null) {
                $consumed = $closeIdx - $i + 1;
            }

            return $kw;
        }

        // Types that need conversion — consume (N) first
        if ($closeIdx !== null) {
            $consumed = $closeIdx - $i + 1;
        }

        return match (true) {
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
            default => $tokens[$i]->token,
        };
    }

    /**
     * @param list<Token> $tokens
     */
    private function skipMysqlClause(array $tokens, int $i, int $count): int
    {
        $i++;

        while ($i < $count && $tokens[$i]->type === TokenType::Whitespace) {
            $i++;
        }

        if ($i < $count && $tokens[$i]->type === TokenType::Keyword
            && $tokens[$i]->keyword === 'SET') {
            $i++;
            while ($i < $count && $tokens[$i]->type === TokenType::Whitespace) {
                $i++;
            }
        }

        if ($i < $count && $tokens[$i]->type === TokenType::Operator && $tokens[$i]->token === '=') {
            $i++;
            while ($i < $count && $tokens[$i]->type === TokenType::Whitespace) {
                $i++;
            }

            if ($i < $count
                && \in_array($tokens[$i]->type, [TokenType::None, TokenType::String, TokenType::Number, TokenType::Keyword], true)) {
                $i++;
            }
        } elseif ($i < $count
            && \in_array($tokens[$i]->type, [TokenType::None, TokenType::String, TokenType::Number], true)) {
            $i++;
        }

        return $i;
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

    // ── Token helpers ──

    /**
     * @param list<Token> $tokens
     */
    private function findFirstKeyword(array $tokens, int $count): ?string
    {
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i]->type === TokenType::Keyword) {
                return $tokens[$i]->keyword;
            }
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     */
    private function findNextNonWhitespace(array $tokens, int $from, int $end): ?int
    {
        for ($i = $from; $i < $end; $i++) {
            if ($tokens[$i]->type !== TokenType::Whitespace && $tokens[$i]->type !== TokenType::Comment) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     */
    private function findMatchingParen(array $tokens, int $openIdx, int $end): ?int
    {
        $depth = 1;

        for ($j = $openIdx + 1; $j < $end; $j++) {
            if ($tokens[$j]->type === TokenType::Operator) {
                if ($tokens[$j]->token === '(') {
                    $depth++;
                } elseif ($tokens[$j]->token === ')') {
                    $depth--;
                    if ($depth === 0) {
                        return $j;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     * @return list<array{int, int}>
     */
    private function splitArguments(array $tokens, int $start, int $end): array
    {
        $args = [];
        $argStart = $start;
        $depth = 0;

        for ($i = $start; $i < $end; $i++) {
            if ($tokens[$i]->type === TokenType::Operator) {
                if ($tokens[$i]->token === '(') {
                    $depth++;
                } elseif ($tokens[$i]->token === ')') {
                    $depth--;
                } elseif ($tokens[$i]->token === ',' && $depth === 0) {
                    $args[] = [$argStart, $i - 1];
                    $argStart = $i + 1;
                }
            }
        }

        $args[] = [$argStart, $end - 1];

        return $args;
    }

    /**
     * @param list<Token> $tokens
     */
    private function buildRawRange(array $tokens, int $start, int $end): string
    {
        $output = '';

        for ($i = $start; $i < $end; $i++) {
            if ($tokens[$i]->type === TokenType::Delimiter) {
                continue;
            }

            if ($tokens[$i]->type === TokenType::Symbol
                && ($tokens[$i]->flags & Token::FLAG_SYMBOL_BACKTICK) !== 0) {
                $output .= '"' . str_replace('"', '""', (string) $tokens[$i]->value) . '"';
            } else {
                $output .= $tokens[$i]->token;
            }
        }

        return $output;
    }

    /**
     * @param list<Token> $tokens
     */
    private function isFollowedByOpenParen(array $tokens, int $i, int $end): bool
    {
        $next = $this->findNextNonWhitespace($tokens, $i + 1, $end);

        return $next !== null
            && $tokens[$next]->type === TokenType::Operator
            && $tokens[$next]->token === '(';
    }

    /**
     * @param list<Token> $tokens
     */
    private function isFollowedByEmptyParens(array $tokens, int $i, int $end): bool
    {
        $open = $this->findNextNonWhitespace($tokens, $i + 1, $end);
        if ($open === null || $tokens[$open]->token !== '(') {
            return false;
        }

        $close = $this->findNextNonWhitespace($tokens, $open + 1, $end);

        return $close !== null && $tokens[$close]->token === ')';
    }

    /**
     * @param list<Token> $tokens
     */
    private function skipPastParens(array $tokens, int $from, int $end): int
    {
        $open = $this->findNextNonWhitespace($tokens, $from, $end);
        if ($open === null || $tokens[$open]->token !== '(') {
            return $from;
        }

        $close = $this->findMatchingParen($tokens, $open, $end);

        return $close !== null ? $close + 1 : $from;
    }

    /**
     * @param list<Token>   $tokens
     * @param list<string>  $keywords
     */
    private function matchKeywordSequence(array $tokens, int $i, int $end, array $keywords): ?int
    {
        $pos = $i;

        foreach ($keywords as $expected) {
            $next = $this->findNextNonWhitespace($tokens, $pos + 1, $end);
            if ($next === null || $tokens[$next]->type !== TokenType::Keyword || $tokens[$next]->keyword !== $expected) {
                return null;
            }
            $pos = $next;
        }

        return $pos;
    }

    /**
     * @param list<Token> $tokens
     * @return array{string, string}|null
     */
    private function extractInterval(array $tokens, int $start, int $end): ?array
    {
        $number = null;
        $unit = null;

        for ($i = $start; $i <= $end; $i++) {
            if ($tokens[$i]->type === TokenType::Whitespace || $tokens[$i]->type === TokenType::Delimiter) {
                continue;
            }

            if ($tokens[$i]->type === TokenType::Keyword && $tokens[$i]->keyword === 'INTERVAL') {
                continue;
            }

            if ($tokens[$i]->type === TokenType::Number && $number === null) {
                $number = $tokens[$i]->token;
                continue;
            }

            if ($tokens[$i]->type === TokenType::Keyword && $number !== null) {
                $unit = $tokens[$i]->token;
                break;
            }
        }

        if ($number === null || $unit === null) {
            return null;
        }

        return [$number, $unit];
    }

    /**
     * @param list<Token> $tokens
     */
    private function extractStringLiteral(array $tokens, int $start, int $end): ?string
    {
        for ($i = $start; $i <= $end; $i++) {
            if ($tokens[$i]->type === TokenType::String) {
                return (string) $tokens[$i]->value;
            }
        }

        return null;
    }
}
