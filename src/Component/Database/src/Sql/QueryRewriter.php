<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Database\Sql;

use PhpMyAdmin\SqlParser\Token;
use PhpMyAdmin\SqlParser\TokenType;

/**
 * Stateful token-stream rewriter for SQL query translation.
 *
 * Inspired by WP_SQLite_Query_Rewriter from the WordPress SQLite Database
 * Integration plugin. Wraps a phpmyadmin/sql-parser token list and provides
 * consume/skip/add operations for building translated SQL.
 *
 * Key features:
 * - Automatic backtick → double-quote identifier conversion on consume
 * - Parenthesis depth tracking for nested expression context
 * - Function call stack tracking
 * - String literal tokens are consumed as-is (never transformed)
 */
final class QueryRewriter
{
    /** @var list<Token> */
    private readonly array $tokens;
    private readonly int $count;
    private int $index = -1;

    /** @var list<string> */
    private array $output = [];

    private int $depth = 0;

    /** @var list<array{function: string, depth: int}> */
    private array $callStack = [];

    private ?string $lastFunctionCall = null;

    /**
     * @param list<Token> $tokens
     */
    public function __construct(array $tokens, int $count)
    {
        $this->tokens = $tokens;
        $this->count = $count;
    }

    /**
     * Whether there are more tokens to process.
     */
    public function hasMore(): bool
    {
        return $this->index + 1 < $this->count;
    }

    /**
     * Peek at the next semantic (non-whitespace, non-comment, non-delimiter) token
     * without advancing the position.
     */
    public function peek(): ?Token
    {
        for ($i = $this->index + 1; $i < $this->count; $i++) {
            $token = $this->tokens[$i];
            if (!$this->isSemanticVoid($token)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Peek at the Nth semantic token ahead (1-based).
     */
    public function peekNth(int $nth): ?Token
    {
        $found = 0;

        for ($i = $this->index + 1; $i < $this->count; $i++) {
            $token = $this->tokens[$i];
            if (!$this->isSemanticVoid($token)) {
                $found++;
                if ($found === $nth) {
                    return $token;
                }
            }
        }

        return null;
    }

    /**
     * Advance to the next semantic token, adding ALL traversed tokens
     * (including whitespace) to the output.
     *
     * Backtick identifiers are automatically converted to double-quoted.
     *
     * @return Token|null The consumed semantic token, or null if end reached
     */
    public function consume(): ?Token
    {
        while (++$this->index < $this->count) {
            $token = $this->tokens[$this->index];
            $this->updateCallStack($token);

            if ($token->type === TokenType::Delimiter) {
                continue;
            }

            $this->addTokenToOutput($token);

            if (!$this->isSemanticVoid($token)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Consume all remaining tokens to the output.
     */
    public function consumeAll(): void
    {
        while ($this->consume() !== null) {
            // Continue
        }
    }

    /**
     * Skip the next semantic token, discarding it and any intermediate tokens.
     *
     * Preserves one whitespace token to avoid syntax errors.
     *
     * @return Token|null The skipped semantic token
     */
    public function skip(): ?Token
    {
        $hadWhitespace = false;

        while (++$this->index < $this->count) {
            $token = $this->tokens[$this->index];
            $this->updateCallStack($token);

            if ($token->type === TokenType::Whitespace) {
                $hadWhitespace = true;
            }

            if (!$this->isSemanticVoid($token)) {
                if ($hadWhitespace) {
                    $this->output[] = ' ';
                }

                return $token;
            }
        }

        return null;
    }

    /**
     * Add a raw string to the output.
     */
    public function add(string $value): void
    {
        $this->output[] = $value;
    }

    /**
     * Add multiple raw strings to the output.
     */
    public function addMany(string ...$values): void
    {
        foreach ($values as $value) {
            $this->output[] = $value;
        }
    }

    /**
     * Remove and return the last output entry.
     */
    public function dropLast(): ?string
    {
        return array_pop($this->output);
    }

    /**
     * Build the final SQL string from all output entries.
     */
    public function getResult(): string
    {
        return implode('', $this->output);
    }

    /**
     * Get the current parenthesis nesting depth.
     */
    public function getDepth(): int
    {
        return $this->depth;
    }

    /**
     * Get the function call stack (for context-aware transformations).
     *
     * @return list<array{function: string, depth: int}>
     */
    public function getCallStack(): array
    {
        return $this->callStack;
    }

    /**
     * Get the last element of the call stack (innermost function context).
     *
     * @return array{function: string, depth: int}|null
     */
    public function lastCallStackElement(): ?array
    {
        return $this->callStack !== [] ? $this->callStack[\count($this->callStack) - 1] : null;
    }

    private function addTokenToOutput(Token $token): void
    {
        // Backtick identifiers → double-quoted
        if ($token->type === TokenType::Symbol
            && ($token->flags & Token::FLAG_SYMBOL_BACKTICK) !== 0) {
            $this->output[] = '"' . str_replace('"', '""', (string) $token->value) . '"';

            return;
        }

        $this->output[] = $token->token;
    }

    private function isSemanticVoid(Token $token): bool
    {
        return $token->type === TokenType::Whitespace
            || $token->type === TokenType::Comment
            || $token->type === TokenType::Delimiter;
    }

    private function updateCallStack(Token $token): void
    {
        if ($token->type === TokenType::Keyword
            && ($token->flags & Token::FLAG_KEYWORD_FUNCTION) !== 0) {
            $this->lastFunctionCall = $token->keyword;
        }

        if ($token->type === TokenType::Operator) {
            if ($token->token === '(') {
                if ($this->lastFunctionCall !== null) {
                    $this->callStack[] = [
                        'function' => $this->lastFunctionCall,
                        'depth' => $this->depth,
                    ];
                    $this->lastFunctionCall = null;
                }
                $this->depth++;
            } elseif ($token->token === ')') {
                $this->depth--;
                $parent = $this->lastCallStackElement();
                if ($parent !== null && $parent['depth'] === $this->depth) {
                    array_pop($this->callStack);
                }
            }
        }
    }
}
