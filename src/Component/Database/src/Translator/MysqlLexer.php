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

namespace WpPack\Component\Database\Translator;

/**
 * Tokenizes MySQL SQL into a list of MysqlToken objects.
 *
 * This is a lightweight lexer for query translation, not a full MySQL parser.
 * It handles: keywords, identifiers (quoted and unquoted), string literals,
 * number literals, operators, whitespace, comments, punctuation, and placeholders.
 */
final class MysqlLexer
{
    /**
     * MySQL reserved keywords (uppercase). Not exhaustive — covers the set
     * needed for query translation.
     */
    private const KEYWORDS = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'CREATE', 'ALTER',
        'DROP', 'TABLE', 'INDEX', 'VIEW', 'INTO', 'FROM', 'WHERE', 'SET',
        'VALUES', 'AND', 'OR', 'NOT', 'IN', 'IS', 'NULL', 'LIKE', 'BETWEEN',
        'EXISTS', 'AS', 'ON', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER',
        'CROSS', 'UNION', 'ALL', 'DISTINCT', 'ORDER', 'BY', 'GROUP', 'HAVING',
        'LIMIT', 'OFFSET', 'ASC', 'DESC', 'IF', 'ELSE', 'THEN', 'WHEN',
        'CASE', 'END', 'BEGIN', 'START', 'TRANSACTION', 'COMMIT', 'ROLLBACK',
        'PRIMARY', 'KEY', 'UNIQUE', 'FOREIGN', 'REFERENCES', 'CONSTRAINT',
        'DEFAULT', 'AUTO_INCREMENT', 'AUTOINCREMENT', 'ENGINE', 'CHARSET',
        'COLLATE', 'CHARACTER', 'UNSIGNED', 'NOT', 'NULL', 'CASCADE',
        'IGNORE', 'DUPLICATE', 'CONFLICT', 'SHOW', 'TABLES', 'COLUMNS',
        'FULL', 'DATABASES', 'VARIABLES', 'STATUS', 'LOCK', 'UNLOCK',
        'FOR', 'NAMES', 'SESSION', 'GLOBAL', 'INT', 'INTEGER', 'BIGINT',
        'SMALLINT', 'TINYINT', 'MEDIUMINT', 'VARCHAR', 'CHAR', 'TEXT',
        'LONGTEXT', 'MEDIUMTEXT', 'TINYTEXT', 'BLOB', 'LONGBLOB',
        'MEDIUMBLOB', 'TINYBLOB', 'DATETIME', 'TIMESTAMP', 'DATE', 'TIME',
        'FLOAT', 'DOUBLE', 'DECIMAL', 'NUMERIC', 'BOOLEAN', 'ENUM',
        'BINARY', 'VARBINARY', 'JSON', 'SERIAL', 'BIGSERIAL', 'SERIAL',
        'NOW', 'CURDATE', 'RAND', 'CONCAT', 'IFNULL', 'FOUND_ROWS',
        'LAST_INSERT_ID', 'UNIX_TIMESTAMP', 'FROM_UNIXTIME', 'DATE_FORMAT',
        'CAST', 'SIGNED', 'GROUP_CONCAT', 'COUNT', 'SUM', 'AVG', 'MIN', 'MAX',
        'TYPE', 'ADD', 'COLUMN', 'MODIFY', 'CHANGE', 'RENAME', 'AFTER',
        'FIRST', 'TEMPORARY', 'TRUNCATE', 'EXPLAIN', 'DESCRIBE', 'USE',
        'DATABASE', 'SCHEMA', 'GRANT', 'REVOKE', 'SEPARATOR',
    ];

    /** @var array<string, true> */
    private array $keywordMap;

    public function __construct()
    {
        $this->keywordMap = array_fill_keys(self::KEYWORDS, true);
    }

    /**
     * @return list<MysqlToken>
     */
    public function tokenize(string $sql): array
    {
        $tokens = [];
        $len = \strlen($sql);
        $pos = 0;

        while ($pos < $len) {
            $char = $sql[$pos];

            // Whitespace
            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                $start = $pos;

                while ($pos < $len && ($sql[$pos] === ' ' || $sql[$pos] === "\t" || $sql[$pos] === "\n" || $sql[$pos] === "\r")) {
                    ++$pos;
                }

                $tokens[] = new MysqlToken(MysqlTokenType::Whitespace, substr($sql, $start, $pos - $start), $start);

                continue;
            }

            // Single-line comment: -- ...
            if ($char === '-' && $pos + 1 < $len && $sql[$pos + 1] === '-') {
                $start = $pos;
                $pos = strpos($sql, "\n", $pos);
                $pos = $pos === false ? $len : $pos + 1;
                $tokens[] = new MysqlToken(MysqlTokenType::Comment, substr($sql, $start, $pos - $start), $start);

                continue;
            }

            // Block comment: /* ... */
            if ($char === '/' && $pos + 1 < $len && $sql[$pos + 1] === '*') {
                $start = $pos;
                $end = strpos($sql, '*/', $pos + 2);
                $pos = $end === false ? $len : $end + 2;
                $tokens[] = new MysqlToken(MysqlTokenType::Comment, substr($sql, $start, $pos - $start), $start);

                continue;
            }

            // Backtick-quoted identifier
            if ($char === '`') {
                $start = $pos;
                ++$pos;

                while ($pos < $len) {
                    if ($sql[$pos] === '`') {
                        ++$pos;

                        if ($pos < $len && $sql[$pos] === '`') {
                            ++$pos;

                            continue;
                        }

                        break;
                    }

                    ++$pos;
                }

                $tokens[] = new MysqlToken(MysqlTokenType::QuotedIdentifier, substr($sql, $start, $pos - $start), $start);

                continue;
            }

            // String literal: '...' or "..."
            if ($char === "'" || $char === '"') {
                $start = $pos;
                $quote = $char;
                ++$pos;

                while ($pos < $len) {
                    if ($sql[$pos] === '\\') {
                        $pos += 2;

                        continue;
                    }

                    if ($sql[$pos] === $quote) {
                        ++$pos;

                        if ($pos < $len && $sql[$pos] === $quote) {
                            ++$pos;

                            continue;
                        }

                        break;
                    }

                    ++$pos;
                }

                $tokens[] = new MysqlToken(MysqlTokenType::StringLiteral, substr($sql, $start, $pos - $start), $start);

                continue;
            }

            // Number literal
            if ($char >= '0' && $char <= '9') {
                $start = $pos;

                while ($pos < $len && (($sql[$pos] >= '0' && $sql[$pos] <= '9') || $sql[$pos] === '.' || $sql[$pos] === 'e' || $sql[$pos] === 'E')) {
                    ++$pos;
                }

                $tokens[] = new MysqlToken(MysqlTokenType::NumberLiteral, substr($sql, $start, $pos - $start), $start);

                continue;
            }

            // Placeholder: ? or %s, %d, %f
            if ($char === '?') {
                $tokens[] = new MysqlToken(MysqlTokenType::Placeholder, '?', $pos);
                ++$pos;

                continue;
            }

            if ($char === '%' && $pos + 1 < $len && \in_array($sql[$pos + 1], ['s', 'd', 'f'], true)) {
                $tokens[] = new MysqlToken(MysqlTokenType::Placeholder, substr($sql, $pos, 2), $pos);
                $pos += 2;

                continue;
            }

            // Punctuation: ( ) , ; . @
            if ($char === '(' || $char === ')' || $char === ',' || $char === ';' || $char === '.' || $char === '@') {
                $tokens[] = new MysqlToken(MysqlTokenType::Punctuation, $char, $pos);
                ++$pos;

                continue;
            }

            // Operators: = != <> < > <= >= + - * / %
            if (\in_array($char, ['=', '<', '>', '!', '+', '-', '*', '/', '%', '&', '|', '^', '~'], true)) {
                $start = $pos;
                ++$pos;

                if ($pos < $len && \in_array($sql[$pos], ['=', '>'], true)) {
                    ++$pos;
                }

                $tokens[] = new MysqlToken(MysqlTokenType::Operator, substr($sql, $start, $pos - $start), $start);

                continue;
            }

            // Identifier or keyword
            if (ctype_alpha($char) || $char === '_') {
                $start = $pos;

                while ($pos < $len && (ctype_alnum($sql[$pos]) || $sql[$pos] === '_')) {
                    ++$pos;
                }

                $word = substr($sql, $start, $pos - $start);
                $upper = strtoupper($word);
                $type = isset($this->keywordMap[$upper]) ? MysqlTokenType::Keyword : MysqlTokenType::Identifier;

                $tokens[] = new MysqlToken($type, $word, $start);

                continue;
            }

            // Unknown character — emit as operator
            $tokens[] = new MysqlToken(MysqlTokenType::Operator, $char, $pos);
            ++$pos;
        }

        return $tokens;
    }
}
