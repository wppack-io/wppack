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

use WpPack\Component\Database\Translator\MysqlLexer;
use WpPack\Component\Database\Translator\MysqlToken;
use WpPack\Component\Database\Translator\MysqlTokenType;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Translates MySQL SQL to PostgreSQL SQL.
 *
 * Used for PostgreSQL and Aurora DSQL targets.
 */
final class PostgresqlQueryTranslator implements QueryTranslatorInterface
{
    private const IGNORED_PATTERNS = [
        '/^\s*SET\s+NAMES\s+/i',
        '/^\s*LOCK\s+TABLES?\s+/i',
        '/^\s*UNLOCK\s+TABLES?\s*/i',
    ];

    private const SHOW_TRANSLATIONS = [
        '/^\s*SHOW\s+TABLES\s*/i' => "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'",
        '/^\s*SHOW\s+FULL\s+TABLES\s*/i' => "SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = 'public'",
    ];

    public function __construct(
        private readonly MysqlLexer $lexer = new MysqlLexer(),
    ) {}

    public function translate(string $sql): array
    {
        $trimmed = trim($sql);

        foreach (self::IGNORED_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return [];
            }
        }

        foreach (self::SHOW_TRANSLATIONS as $pattern => $replacement) {
            if (preg_match($pattern, $trimmed)) {
                return [$replacement];
            }
        }

        // SHOW COLUMNS FROM `table`
        if (preg_match('/^\s*SHOW\s+(?:FULL\s+)?COLUMNS\s+FROM\s+`?(\w+)`?\s*/i', $trimmed, $m)) {
            return [\sprintf(
                "SELECT column_name AS \"Field\", data_type AS \"Type\", is_nullable AS \"Null\", column_default AS \"Default\" "
                . "FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '%s' ORDER BY ordinal_position",
                $m[1],
            )];
        }

        // SHOW VARIABLES
        if (preg_match('/^\s*SHOW\s+(?:GLOBAL\s+|SESSION\s+)?VARIABLES\s*/i', $trimmed)) {
            return ["SELECT name AS Variable_name, setting AS Value FROM pg_settings LIMIT 0"];
        }

        // Token-based rewriting
        $tokens = $this->lexer->tokenize($sql);
        $tokens = $this->rewriteIdentifiers($tokens);
        $tokens = $this->rewriteKeywords($tokens);
        $tokens = $this->rewriteFunctions($tokens);
        $tokens = $this->rewriteInsert($tokens);
        $tokens = $this->rewriteLimit($tokens);
        $tokens = $this->stripUnsigned($tokens);

        return [$this->tokensToString($tokens)];
    }

    /**
     * Backtick → double-quote.
     *
     * @param list<MysqlToken> $tokens
     * @return list<MysqlToken>
     */
    private function rewriteIdentifiers(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $token) {
            if ($token->type === MysqlTokenType::QuotedIdentifier) {
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
     * @param list<MysqlToken> $tokens
     * @return list<MysqlToken>
     */
    private function rewriteKeywords(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $token) {
            if ($token->isKeyword('AUTO_INCREMENT')) {
                $result[] = new MysqlToken(MysqlTokenType::Keyword, 'SERIAL', $token->position);
            } else {
                $result[] = $token;
            }
        }

        return $result;
    }

    /**
     * @param list<MysqlToken> $tokens
     * @return list<MysqlToken>
     */
    private function rewriteFunctions(array $tokens): array
    {
        $result = [];
        $count = \count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            if ($token->isKeyword('IFNULL') || (
                $token->type === MysqlTokenType::Identifier && strcasecmp($token->value, 'IFNULL') === 0
            )) {
                $result[] = new MysqlToken(MysqlTokenType::Identifier, 'COALESCE', $token->position);

                continue;
            }

            if ($token->isKeyword('RAND') || (
                $token->type === MysqlTokenType::Identifier && strcasecmp($token->value, 'RAND') === 0
            )) {
                $result[] = new MysqlToken(MysqlTokenType::Identifier, 'random', $token->position);

                continue;
            }

            $result[] = $token;
        }

        return $result;
    }

    /**
     * INSERT IGNORE INTO → INSERT INTO ... ON CONFLICT DO NOTHING
     *
     * @param list<MysqlToken> $tokens
     * @return list<MysqlToken>
     */
    private function rewriteInsert(array $tokens): array
    {
        $result = [];
        $count = \count($tokens);
        $hasIgnore = false;

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            if ($token->isKeyword('INSERT')) {
                $next = $this->nextNonWhitespace($tokens, $i);

                if ($next !== null && $tokens[$next]->isKeyword('IGNORE')) {
                    $result[] = $token;
                    // Skip IGNORE
                    $i = $next;
                    $hasIgnore = true;

                    continue;
                }
            }

            $result[] = $token;
        }

        if ($hasIgnore) {
            // Append ON CONFLICT DO NOTHING before trailing semicolon
            $lastIdx = \count($result) - 1;

            while ($lastIdx >= 0 && ($result[$lastIdx]->type === MysqlTokenType::Whitespace || ($result[$lastIdx]->type === MysqlTokenType::Punctuation && $result[$lastIdx]->value === ';'))) {
                --$lastIdx;
            }

            $tail = \array_splice($result, $lastIdx + 1);
            $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', 0);
            $result[] = new MysqlToken(MysqlTokenType::Keyword, 'ON', 0);
            $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', 0);
            $result[] = new MysqlToken(MysqlTokenType::Keyword, 'CONFLICT', 0);
            $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', 0);
            $result[] = new MysqlToken(MysqlTokenType::Identifier, 'DO', 0);
            $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', 0);
            $result[] = new MysqlToken(MysqlTokenType::Identifier, 'NOTHING', 0);
            array_push($result, ...$tail);
        }

        return $result;
    }

    /**
     * LIMIT offset, count → LIMIT count OFFSET offset
     *
     * @param list<MysqlToken> $tokens
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

            $firstIdx = $this->nextNonWhitespace($tokens, $i);

            if ($firstIdx === null || $tokens[$firstIdx]->type !== MysqlTokenType::NumberLiteral) {
                $result[] = $token;

                continue;
            }

            $commaIdx = $this->nextNonWhitespace($tokens, $firstIdx);

            if ($commaIdx === null || $tokens[$commaIdx]->value !== ',') {
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
            $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', 0);
            $result[] = new MysqlToken(MysqlTokenType::NumberLiteral, $limitCount, 0);
            $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', 0);
            $result[] = new MysqlToken(MysqlTokenType::Keyword, 'OFFSET', 0);
            $result[] = new MysqlToken(MysqlTokenType::Whitespace, ' ', 0);
            $result[] = new MysqlToken(MysqlTokenType::NumberLiteral, $offset, 0);
            $i = $secondIdx;
        }

        return $result;
    }

    /**
     * @param list<MysqlToken> $tokens
     * @return list<MysqlToken>
     */
    private function stripUnsigned(array $tokens): array
    {
        return array_values(array_filter($tokens, fn (MysqlToken $t) => !$t->isKeyword('UNSIGNED')));
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
    private function tokensToString(array $tokens): string
    {
        $sql = '';

        foreach ($tokens as $token) {
            if ($token->type !== MysqlTokenType::Comment) {
                $sql .= $token->value;
            }
        }

        return $sql;
    }
}
