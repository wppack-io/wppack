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

use WpPack\Component\Database\Translator\MysqlLexer;
use WpPack\Component\Database\Translator\MysqlToken;
use WpPack\Component\Database\Translator\MysqlTokenType;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Translates MySQL SQL to SQLite SQL.
 *
 * Uses token-based rewriting for lightweight translation without full AST parsing.
 */
final class SqliteQueryTranslator implements QueryTranslatorInterface
{
    /**
     * SQL statements that should be silently ignored (MySQL-specific with no SQLite equivalent).
     */
    private const IGNORED_PATTERNS = [
        '/^\s*SET\s+(SESSION\s+|GLOBAL\s+)?/i',
        '/^\s*SET\s+NAMES\s+/i',
        '/^\s*LOCK\s+TABLES?\s+/i',
        '/^\s*UNLOCK\s+TABLES?\s*/i',
    ];

    /**
     * SHOW statement patterns that need special handling.
     *
     * @var array<string, string>
     */
    private const SHOW_TRANSLATIONS = [
        '/^\s*SHOW\s+TABLES\s*/i' => "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'",
        '/^\s*SHOW\s+FULL\s+TABLES\s*/i' => "SELECT name, 'BASE TABLE' AS Table_type FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'",
    ];

    public function __construct(
        private readonly MysqlLexer $lexer = new MysqlLexer(),
        private readonly SqliteRewriter $rewriter = new SqliteRewriter(),
    ) {}

    public function translate(string $sql): array
    {
        $trimmed = trim($sql);

        // Check for statements to ignore
        foreach (self::IGNORED_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return [];
            }
        }

        // Check for SHOW statements
        foreach (self::SHOW_TRANSLATIONS as $pattern => $replacement) {
            if (preg_match($pattern, $trimmed)) {
                return [$replacement];
            }
        }

        // Handle SHOW COLUMNS FROM `table`
        if (preg_match('/^\s*SHOW\s+(?:FULL\s+)?COLUMNS\s+FROM\s+`?(\w+)`?\s*/i', $trimmed, $m)) {
            return [\sprintf('PRAGMA table_info("%s")', $m[1])];
        }

        // Handle SHOW CREATE TABLE `table`
        if (preg_match('/^\s*SHOW\s+CREATE\s+TABLE\s+`?(\w+)`?\s*/i', $trimmed, $m)) {
            return [\sprintf("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = '%s'", $m[1])];
        }

        // Handle SHOW INDEX FROM `table`
        if (preg_match('/^\s*SHOW\s+(?:INDEX|KEYS?)\s+FROM\s+`?(\w+)`?\s*/i', $trimmed, $m)) {
            return [\sprintf('PRAGMA index_list("%s")', $m[1])];
        }

        // Handle SHOW VARIABLES
        if (preg_match('/^\s*SHOW\s+(?:GLOBAL\s+|SESSION\s+)?VARIABLES\s*/i', $trimmed)) {
            return ["SELECT '' AS Variable_name, '' AS Value WHERE 0"];
        }

        // Handle SELECT ... FOR UPDATE (strip FOR UPDATE)
        $sql = preg_replace('/\s+FOR\s+UPDATE\s*/i', '', $sql) ?? $sql;

        // Token-based rewriting
        $tokens = $this->lexer->tokenize($sql);
        $rewritten = $this->rewriter->rewrite($tokens);

        return [$this->tokensToString($rewritten)];
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
