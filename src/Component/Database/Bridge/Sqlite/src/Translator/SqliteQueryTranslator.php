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

use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Token;
use PhpMyAdmin\SqlParser\TokenType;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Translates MySQL SQL to SQLite SQL using token-stream walking.
 *
 * Uses phpmyadmin/sql-parser's Lexer to tokenize the input SQL, then walks
 * the token stream applying transformations. String literals (TokenType::String)
 * are always passed through unchanged, making this approach inherently safe
 * from the string-literal corruption bug that regex-based transformers suffer.
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
     * Zero-argument function replacements.
     * When keyword is followed by empty parens (), the entire FUNC() is replaced.
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
     * Standalone keyword replacements (no parentheses required).
     *
     * @var array<string, string>
     */
    private const KEYWORD_MAP = [
        'CURRENT_TIMESTAMP' => "datetime('now')",
    ];

    /**
     * Function rename map: just the function name is replaced, parens and args stay.
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

        $lexer = new Lexer($sql);
        /** @var list<Token> $tokens */
        $tokens = $lexer->list->tokens;
        $count = $lexer->list->count;

        $firstKw = $this->findFirstKeyword($tokens, $count);

        if ($firstKw === 'TRUNCATE') {
            return $this->translateTruncate($tokens, $count);
        }

        if ($firstKw === 'ALTER') {
            return $this->translateAlter($tokens, $count);
        }

        if ($firstKw === 'CREATE') {
            return [$this->transformCreateTokens($tokens, $count)];
        }

        $result = $this->transformRange($tokens, 0, $count);

        if ($result === '') {
            return [];
        }

        return [$result];
    }

    // ── Token stream transformation (DML) ──

    /**
     * Walk the token stream and apply all DML transformations.
     *
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

            // String literals — pass through unchanged (core safety guarantee)
            if ($token->type === TokenType::String) {
                $output .= $token->token;
                $i++;
                continue;
            }

            // Backtick identifiers → double-quoted identifiers
            if ($token->type === TokenType::Symbol
                && ($token->flags & Token::FLAG_SYMBOL_BACKTICK) !== 0) {
                $output .= '"' . str_replace('"', '""', (string) $token->value) . '"';
                $i++;
                continue;
            }

            if ($token->type === TokenType::Keyword && $token->keyword !== null) {
                $kw = $token->keyword;

                // ── Composed keywords ──

                if ($kw === 'FOR UPDATE') {
                    $output = rtrim($output);
                    $i++;
                    continue;
                }

                // ── DML statement transforms ──

                // INSERT ... IGNORE → INSERT OR IGNORE
                if ($kw === 'INSERT') {
                    $nextIdx = $this->findNextNonWhitespace($tokens, $i + 1, $end);
                    if ($nextIdx !== null
                        && $tokens[$nextIdx]->type === TokenType::Keyword
                        && $tokens[$nextIdx]->keyword === 'IGNORE') {
                        $output .= 'INSERT OR IGNORE';
                        $i = $nextIdx + 1;
                        continue;
                    }
                }

                // REPLACE (DML) → INSERT OR REPLACE
                if ($kw === 'REPLACE' && !$this->isFollowedByOpenParen($tokens, $i, $end)) {
                    $output .= 'INSERT OR REPLACE';
                    $i++;
                    continue;
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

                // ── Zero-arg functions: NOW() → datetime('now') ──
                if (isset(self::ZERO_ARG_MAP[$kw])
                    && $this->isFollowedByEmptyParens($tokens, $i, $end)) {
                    $output .= self::ZERO_ARG_MAP[$kw];
                    $i = $this->skipPastParens($tokens, $i + 1, $end);
                    continue;
                }

                // ── Standalone keyword replacements: CURRENT_TIMESTAMP ──
                if (isset(self::KEYWORD_MAP[$kw])
                    && !$this->isFollowedByOpenParen($tokens, $i, $end)) {
                    $output .= self::KEYWORD_MAP[$kw];
                    $i++;
                    continue;
                }

                // ── Function renames: RAND( → random( ──
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

                // ── CAST(x AS SIGNED) → CAST(x AS INTEGER) ──
                if ($kw === 'SIGNED') {
                    $output .= 'INTEGER';
                    $i++;
                    continue;
                }
            }

            // Default: output token as-is
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
     * DATE_ADD(d, INTERVAL n unit) → datetime(d, '+n unit')
     * DATE_SUB(d, INTERVAL n unit) → datetime(d, '-n unit')
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
        $out = \sprintf("datetime(%s, '%s%s %s')", $dateExpr, $sign, $number, strtolower($unit));

        return $closeIdx - $i + 1;
    }

    /**
     * DATE_FORMAT(d, 'format') → strftime('converted_format', d)
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
            ['%Y', '%m', '%d', '%H', '%i', '%s', '%j', '%W'],
            ['%Y', '%m', '%d', '%H', '%M', '%S', '%j', '%w'],
            $formatStr,
        );

        $out = \sprintf("strftime('%s', %s)", $format, $dateExpr);

        return $closeIdx - $i + 1;
    }

    /**
     * FROM_UNIXTIME(t) → datetime(t, 'unixepoch')
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
        $out = \sprintf("datetime(%s, 'unixepoch')", $inner);

        return $closeIdx - $i + 1;
    }

    /**
     * LEFT(s, n) → SUBSTR(s, 1, n)
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

        $out = \sprintf('SUBSTR(%s, 1, %s)', $strExpr, $lenExpr);

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
     * LIMIT offset, count → LIMIT count OFFSET offset
     * LIMIT count          → LIMIT count (unchanged)
     *
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

        // LIMIT count — preserve original tokens
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

                // Data type keywords → SQLite type
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

                // AUTO_INCREMENT: column property or table property (=N)
                if ($kw === 'AUTO_INCREMENT') {
                    $next = $this->findNextNonWhitespace($tokens, $i + 1, $count);
                    if ($next !== null && $tokens[$next]->type === TokenType::Operator && $tokens[$next]->token === '=') {
                        $i = $this->skipMysqlClause($tokens, $i, $count);
                    } else {
                        $output .= 'AUTOINCREMENT';
                        $i++;
                    }
                    continue;
                }

                // MySQL-specific clauses → skip
                if (\in_array($kw, ['ENGINE', 'DEFAULT CHARSET', 'COLLATE', 'CHARACTER SET'], true)) {
                    $i = $this->skipMysqlClause($tokens, $i, $count);
                    continue;
                }

                // CHARACTER (standalone, might be followed by SET)
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

        return $this->mergeAutoincrementPrimaryKey($output);
    }

    /**
     * @param list<Token> $tokens
     */
    private function transformDataType(array $tokens, int $i, int $count, int &$consumed): string
    {
        $kw = $tokens[$i]->keyword ?? '';
        $consumed = 1;

        // Skip trailing (N) if present
        $next = $this->findNextNonWhitespace($tokens, $i + 1, $count);
        if ($next !== null && $tokens[$next]->type === TokenType::Operator && $tokens[$next]->token === '(') {
            $close = $this->findMatchingParen($tokens, $next, $count);
            if ($close !== null) {
                $consumed = $close - $i + 1;
            }
        }

        return match (true) {
            \in_array($kw, ['BIGINT', 'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BOOLEAN'], true) => 'INTEGER',
            \in_array($kw, ['VARCHAR', 'CHAR', 'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT', 'ENUM', 'SET'], true) => 'TEXT',
            \in_array($kw, ['DATETIME', 'TIMESTAMP', 'DATE', 'TIME'], true) => 'TEXT',
            \in_array($kw, ['JSON'], true) => 'TEXT',
            \in_array($kw, ['FLOAT', 'DOUBLE', 'DECIMAL', 'NUMERIC', 'REAL'], true) => 'REAL',
            \in_array($kw, ['BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB', 'VARBINARY', 'BINARY'], true) => 'BLOB',
            default => $tokens[$i]->token,
        };
    }

    /**
     * Skip a MySQL clause like ENGINE=InnoDB, DEFAULT CHARSET=utf8mb4, etc.
     *
     * @param list<Token> $tokens
     */
    private function skipMysqlClause(array $tokens, int $i, int $count): int
    {
        $i++;

        // Skip whitespace
        while ($i < $count && $tokens[$i]->type === TokenType::Whitespace) {
            $i++;
        }

        // If next keyword is SET (for CHARACTER SET), skip it too
        if ($i < $count && $tokens[$i]->type === TokenType::Keyword
            && $tokens[$i]->keyword === 'SET') {
            $i++;
            while ($i < $count && $tokens[$i]->type === TokenType::Whitespace) {
                $i++;
            }
        }

        // Skip = if present
        if ($i < $count && $tokens[$i]->type === TokenType::Operator && $tokens[$i]->token === '=') {
            $i++;
            while ($i < $count && $tokens[$i]->type === TokenType::Whitespace) {
                $i++;
            }

            // Skip value
            if ($i < $count
                && \in_array($tokens[$i]->type, [TokenType::None, TokenType::String, TokenType::Number, TokenType::Keyword], true)) {
                $i++;
            }
        } elseif ($i < $count
            && \in_array($tokens[$i]->type, [TokenType::None, TokenType::String, TokenType::Number], true)) {
            // Value without = (e.g., CHARACTER SET utf8mb4)
            $i++;
        }

        return $i;
    }

    /**
     * Merge AUTOINCREMENT with separate PRIMARY KEY for SQLite.
     *
     * SQLite requires INTEGER PRIMARY KEY AUTOINCREMENT on the same line.
     * WordPress pattern: `ID` bigint(20) AUTO_INCREMENT ... PRIMARY KEY (`ID`)
     */
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

    // ── Statement handlers ──

    /**
     * TRUNCATE TABLE t → DELETE FROM "t"
     *
     * @param list<Token> $tokens
     * @return list<string>
     */
    private function translateTruncate(array $tokens, int $count): array
    {
        $pastTruncate = false;
        $pastTable = false;

        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i]->type === TokenType::Whitespace || $tokens[$i]->type === TokenType::Delimiter) {
                continue;
            }

            if (!$pastTruncate && $tokens[$i]->type === TokenType::Keyword && $tokens[$i]->keyword === 'TRUNCATE') {
                $pastTruncate = true;
                continue;
            }

            if ($pastTruncate && !$pastTable && $tokens[$i]->type === TokenType::Keyword && $tokens[$i]->keyword === 'TABLE') {
                $pastTable = true;
                continue;
            }

            if ($pastTruncate) {
                $name = ($tokens[$i]->type === TokenType::Symbol && ($tokens[$i]->flags & Token::FLAG_SYMBOL_BACKTICK) !== 0)
                    ? (string) $tokens[$i]->value
                    : $tokens[$i]->token;

                return ['DELETE FROM "' . str_replace('"', '""', $name) . '"'];
            }
        }

        return [];
    }

    /**
     * ALTER TABLE — only ADD COLUMN and RENAME are supported.
     *
     * @param list<Token> $tokens
     * @return list<string>
     */
    private function translateAlter(array $tokens, int $count): array
    {
        $sql = $this->convertIdentifiers($tokens, 0, $count);

        // ADD COLUMN (but not ADD INDEX, ADD KEY, ADD UNIQUE, ADD PRIMARY, ADD CONSTRAINT)
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
     * Convert backtick identifiers to double-quoted identifiers (no other transforms).
     *
     * @param list<Token> $tokens
     */
    private function convertIdentifiers(array $tokens, int $start, int $end): string
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
     * Split arguments at top-level commas within a token range.
     * Returns array of [startIdx, endIdx] pairs (inclusive).
     *
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
     * Build raw SQL from a token range without applying any transformations.
     *
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
     * Skip past the next pair of parentheses, returning the index after ')'.
     *
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
     * Match a keyword sequence starting from the token after $i.
     * Returns the index of the last matched keyword, or null.
     *
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
     * Extract INTERVAL n unit from a token range.
     *
     * @param list<Token> $tokens
     * @return array{string, string}|null [number, unit]
     */
    private function extractInterval(array $tokens, int $start, int $end): ?array
    {
        $number = null;
        $unit = null;

        for ($i = $start; $i <= $end; $i++) {
            if ($tokens[$i]->type === TokenType::Whitespace || $tokens[$i]->type === TokenType::Delimiter) {
                continue;
            }

            if ($tokens[$i]->type === TokenType::Keyword
                && $tokens[$i]->keyword === 'INTERVAL') {
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
     * Extract a string literal value (without quotes) from a token range.
     *
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
