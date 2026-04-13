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

namespace WpPack\Component\Database\Translator\Sqlite;

use WpPack\Component\Database\Translator\MysqlToken;
use WpPack\Component\Database\Translator\MysqlTokenType;

/**
 * Rewrites MySQL tokens to SQLite-compatible equivalents.
 */
final class SqliteRewriter
{
    /**
     * @param list<MysqlToken> $tokens
     *
     * @return list<MysqlToken>
     */
    public function rewrite(array $tokens): array
    {
        $tokens = $this->rewriteIdentifiers($tokens);
        $tokens = $this->rewriteKeywords($tokens);
        $tokens = $this->rewriteFunctions($tokens);
        $tokens = $this->rewriteInsert($tokens);
        $tokens = $this->rewriteLimit($tokens);
        $tokens = $this->stripEngineClause($tokens);
        $tokens = $this->stripUnsigned($tokens);
        $tokens = $this->rewriteDataTypes($tokens);

        return $tokens;
    }

    /**
     * Convert backtick-quoted identifiers to double-quoted.
     *
     * @param list<MysqlToken> $tokens
     *
     * @return list<MysqlToken>
     */
    private function rewriteIdentifiers(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $token) {
            if ($token->type === MysqlTokenType::QuotedIdentifier) {
                // `name` → "name", handle escaped backticks
                $inner = substr($token->value, 1, -1);
                $inner = str_replace('``', '`', $inner);
                $inner = str_replace('"', '""', $inner);
                $result[] = new MysqlToken(MysqlTokenType::QuotedIdentifier, '"' . $inner . '"', $token->position);
            } else {
                $result[] = $token;
            }
        }

        return $result;
    }

    /**
     * Rewrite MySQL-specific keywords.
     *
     * @param list<MysqlToken> $tokens
     *
     * @return list<MysqlToken>
     */
    private function rewriteKeywords(array $tokens): array
    {
        $result = [];
        $count = \count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            // AUTO_INCREMENT → AUTOINCREMENT
            if ($token->isKeyword('AUTO_INCREMENT')) {
                $result[] = new MysqlToken(MysqlTokenType::Keyword, 'AUTOINCREMENT', $token->position);

                continue;
            }

            // START TRANSACTION → BEGIN
            if ($token->isKeyword('START')) {
                $next = $this->nextNonWhitespace($tokens, $i);

                if ($next !== null && $tokens[$next]->isKeyword('TRANSACTION')) {
                    $result[] = new MysqlToken(MysqlTokenType::Keyword, 'BEGIN', $token->position);
                    $i = $next;

                    continue;
                }
            }

            $result[] = $token;
        }

        return $result;
    }

    /**
     * Rewrite MySQL functions to SQLite equivalents.
     *
     * @param list<MysqlToken> $tokens
     *
     * @return list<MysqlToken>
     */
    private function rewriteFunctions(array $tokens): array
    {
        $result = [];
        $count = \count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            if ($token->type !== MysqlTokenType::Keyword && $token->type !== MysqlTokenType::Identifier) {
                $result[] = $token;

                continue;
            }

            $nextIdx = $this->nextNonWhitespace($tokens, $i);
            $isFunction = $nextIdx !== null && $tokens[$nextIdx]->type === MysqlTokenType::Punctuation && $tokens[$nextIdx]->value === '(';

            if (!$isFunction) {
                $result[] = $token;

                continue;
            }

            $upper = strtoupper($token->value);

            switch ($upper) {
                case 'NOW':
                    $result[] = new MysqlToken(MysqlTokenType::Identifier, "datetime", $token->position);
                    // Skip past NOW( and )
                    $closeIdx = $this->findClosingParen($tokens, $nextIdx);
                    $result[] = new MysqlToken(MysqlTokenType::Punctuation, '(', $tokens[$nextIdx]->position);
                    $result[] = new MysqlToken(MysqlTokenType::StringLiteral, "'now'", $tokens[$nextIdx]->position);
                    $result[] = new MysqlToken(MysqlTokenType::Punctuation, ')', $tokens[$closeIdx]->position);
                    $i = $closeIdx;

                    break;

                case 'CURDATE':
                    $closeIdx = $this->findClosingParen($tokens, $nextIdx);
                    $result[] = new MysqlToken(MysqlTokenType::Identifier, "date", $token->position);
                    $result[] = new MysqlToken(MysqlTokenType::Punctuation, '(', $tokens[$nextIdx]->position);
                    $result[] = new MysqlToken(MysqlTokenType::StringLiteral, "'now'", $tokens[$nextIdx]->position);
                    $result[] = new MysqlToken(MysqlTokenType::Punctuation, ')', $tokens[$closeIdx]->position);
                    $i = $closeIdx;

                    break;

                case 'RAND':
                    $result[] = new MysqlToken(MysqlTokenType::Identifier, 'random', $token->position);

                    break;

                case 'LAST_INSERT_ID':
                    $result[] = new MysqlToken(MysqlTokenType::Identifier, 'last_insert_rowid', $token->position);

                    break;

                case 'UNIX_TIMESTAMP':
                    $closeIdx = $this->findClosingParen($tokens, $nextIdx);
                    $result[] = new MysqlToken(MysqlTokenType::Identifier, "strftime", $token->position);
                    $result[] = new MysqlToken(MysqlTokenType::Punctuation, '(', $tokens[$nextIdx]->position);
                    $result[] = new MysqlToken(MysqlTokenType::StringLiteral, "'%s'", $tokens[$nextIdx]->position);
                    $result[] = new MysqlToken(MysqlTokenType::Punctuation, ',', $tokens[$nextIdx]->position);
                    $result[] = new MysqlToken(MysqlTokenType::StringLiteral, "'now'", $tokens[$nextIdx]->position);
                    $result[] = new MysqlToken(MysqlTokenType::Punctuation, ')', $tokens[$closeIdx]->position);
                    $i = $closeIdx;

                    break;

                default:
                    $result[] = $token;

                    break;
            }
        }

        return $result;
    }

    /**
     * INSERT IGNORE INTO → INSERT OR IGNORE INTO
     * REPLACE INTO → INSERT OR REPLACE INTO
     *
     * @param list<MysqlToken> $tokens
     *
     * @return list<MysqlToken>
     */
    private function rewriteInsert(array $tokens): array
    {
        $result = [];
        $count = \count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            if ($token->isKeyword('INSERT')) {
                $next = $this->nextNonWhitespace($tokens, $i);

                if ($next !== null && $tokens[$next]->isKeyword('IGNORE')) {
                    $result[] = new MysqlToken(MysqlTokenType::Keyword, 'INSERT', $token->position);
                    $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', $token->position);
                    $result[] = new MysqlToken(MysqlTokenType::Keyword, 'OR', $token->position);
                    $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', $token->position);
                    $result[] = new MysqlToken(MysqlTokenType::Keyword, 'IGNORE', $token->position);
                    // Skip whitespace + IGNORE
                    $i = $next;

                    continue;
                }
            }

            if ($token->isKeyword('REPLACE')) {
                $next = $this->nextNonWhitespace($tokens, $i);

                if ($next !== null && $tokens[$next]->isKeyword('INTO')) {
                    $result[] = new MysqlToken(MysqlTokenType::Keyword, 'INSERT', $token->position);
                    $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', $token->position);
                    $result[] = new MysqlToken(MysqlTokenType::Keyword, 'OR', $token->position);
                    $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', $token->position);
                    $result[] = new MysqlToken(MysqlTokenType::Keyword, 'REPLACE', $token->position);

                    continue;
                }
            }

            $result[] = $token;
        }

        return $result;
    }

    /**
     * LIMIT offset, count → LIMIT count OFFSET offset
     *
     * @param list<MysqlToken> $tokens
     *
     * @return list<MysqlToken>
     */
    private function rewriteLimit(array $tokens): array
    {
        $result = [];
        $count = \count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            if (!$token->isKeyword('LIMIT')) {
                $result[] = $token;

                continue;
            }

            // Collect: LIMIT <ws> <num1> <ws>? , <ws>? <num2>
            $firstIdx = $this->nextNonWhitespace($tokens, $i);

            if ($firstIdx === null || $tokens[$firstIdx]->type !== MysqlTokenType::NumberLiteral) {
                $result[] = $token;

                continue;
            }

            $commaIdx = $this->nextNonWhitespace($tokens, $firstIdx);

            if ($commaIdx === null || $tokens[$commaIdx]->type !== MysqlTokenType::Punctuation || $tokens[$commaIdx]->value !== ',') {
                // LIMIT count (no offset) — pass through
                $result[] = $token;

                continue;
            }

            $secondIdx = $this->nextNonWhitespace($tokens, $commaIdx);

            if ($secondIdx === null || $tokens[$secondIdx]->type !== MysqlTokenType::NumberLiteral) {
                $result[] = $token;

                continue;
            }

            $offset = $tokens[$firstIdx]->value;
            $limitCount = $tokens[$secondIdx]->value;

            $result[] = new MysqlToken(MysqlTokenType::Keyword, 'LIMIT', $token->position);
            $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', $token->position);
            $result[] = new MysqlToken(MysqlTokenType::NumberLiteral, $limitCount, $token->position);
            $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', $token->position);
            $result[] = new MysqlToken(MysqlTokenType::Keyword, 'OFFSET', $token->position);
            $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', $token->position);
            $result[] = new MysqlToken(MysqlTokenType::NumberLiteral, $offset, $token->position);

            $i = $secondIdx;

            continue;
        }

        return $result;
    }

    /**
     * Strip ENGINE=... clause from CREATE TABLE.
     *
     * @param list<MysqlToken> $tokens
     *
     * @return list<MysqlToken>
     */
    private function stripEngineClause(array $tokens): array
    {
        $result = [];
        $count = \count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            if ($tokens[$i]->isKeyword('ENGINE')) {
                $next = $this->nextNonWhitespace($tokens, $i);

                if ($next !== null && $tokens[$next]->type === MysqlTokenType::Operator && $tokens[$next]->value === '=') {
                    $valueIdx = $this->nextNonWhitespace($tokens, $next);

                    if ($valueIdx !== null) {
                        $i = $valueIdx;

                        continue;
                    }
                }
            }

            // Also strip DEFAULT CHARSET=... and COLLATE=...
            if ($tokens[$i]->isKeyword('CHARSET') || $tokens[$i]->isKeyword('COLLATE')) {
                $next = $this->nextNonWhitespace($tokens, $i);

                if ($next !== null && $tokens[$next]->type === MysqlTokenType::Operator && $tokens[$next]->value === '=') {
                    $valueIdx = $this->nextNonWhitespace($tokens, $next);

                    if ($valueIdx !== null) {
                        // Also strip preceding DEFAULT keyword
                        if ($result !== [] && $result[\count($result) - 1]->isKeyword('DEFAULT')) {
                            array_pop($result);
                        }

                        // Strip any preceding whitespace
                        while ($result !== [] && $result[\count($result) - 1]->type === MysqlTokenType::Whitespace) {
                            array_pop($result);
                        }

                        $i = $valueIdx;

                        continue;
                    }
                }
            }

            $result[] = $tokens[$i];
        }

        return $result;
    }

    /**
     * Strip UNSIGNED keyword.
     *
     * @param list<MysqlToken> $tokens
     *
     * @return list<MysqlToken>
     */
    private function stripUnsigned(array $tokens): array
    {
        return array_values(array_filter($tokens, fn (MysqlToken $t) => !$t->isKeyword('UNSIGNED')));
    }

    /**
     * Rewrite MySQL data types to SQLite equivalents in CREATE TABLE.
     *
     * @param list<MysqlToken> $tokens
     *
     * @return list<MysqlToken>
     */
    private function rewriteDataTypes(array $tokens): array
    {
        $typeMap = [
            'VARCHAR' => 'TEXT',
            'CHAR' => 'TEXT',
            'TINYTEXT' => 'TEXT',
            'MEDIUMTEXT' => 'TEXT',
            'LONGTEXT' => 'TEXT',
            'DATETIME' => 'TEXT',
            'TIMESTAMP' => 'TEXT',
            'ENUM' => 'TEXT',
            'TINYINT' => 'INTEGER',
            'SMALLINT' => 'INTEGER',
            'MEDIUMINT' => 'INTEGER',
            'INT' => 'INTEGER',
            'BIGINT' => 'INTEGER',
            'FLOAT' => 'REAL',
            'DOUBLE' => 'REAL',
            'DECIMAL' => 'REAL',
            'NUMERIC' => 'REAL',
            'TINYBLOB' => 'BLOB',
            'MEDIUMBLOB' => 'BLOB',
            'LONGBLOB' => 'BLOB',
            'VARBINARY' => 'BLOB',
            'BINARY' => 'BLOB',
        ];

        $result = [];
        $count = \count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];
            $upper = strtoupper($token->value);

            if ($token->type === MysqlTokenType::Keyword && isset($typeMap[$upper])) {
                $result[] = new MysqlToken(MysqlTokenType::Keyword, $typeMap[$upper], $token->position);

                // Skip size specification: (N) or (N,M)
                $next = $this->nextNonWhitespace($tokens, $i);

                if ($next !== null && $tokens[$next]->type === MysqlTokenType::Punctuation && $tokens[$next]->value === '(') {
                    $closeIdx = $this->findClosingParen($tokens, $next);
                    $i = $closeIdx;
                }

                continue;
            }

            $result[] = $token;
        }

        return $result;
    }

    /**
     * @param list<MysqlToken> $tokens
     */
    private function nextNonWhitespace(array $tokens, int $from): ?int
    {
        $count = \count($tokens);

        for ($i = $from + 1; $i < $count; ++$i) {
            if ($tokens[$i]->type !== MysqlTokenType::Whitespace && $tokens[$i]->type !== MysqlTokenType::Comment) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<MysqlToken> $tokens
     */
    private function findClosingParen(array $tokens, int $openIdx): int
    {
        $depth = 1;
        $count = \count($tokens);

        for ($i = $openIdx + 1; $i < $count; ++$i) {
            if ($tokens[$i]->type === MysqlTokenType::Punctuation) {
                if ($tokens[$i]->value === '(') {
                    ++$depth;
                } elseif ($tokens[$i]->value === ')') {
                    --$depth;

                    if ($depth === 0) {
                        return $i;
                    }
                }
            }
        }

        return $count - 1;
    }
}
