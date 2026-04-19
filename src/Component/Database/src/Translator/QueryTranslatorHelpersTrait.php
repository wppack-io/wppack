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

namespace WPPack\Component\Database\Translator;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Token;
use PhpMyAdmin\SqlParser\TokenType;
use WPPack\Component\Database\Sql\QueryRewriter;

/**
 * Helpers shared by every engine-specific QueryTranslator.
 *
 * Extracted verbatim from the duplicated bodies that used to live inside
 * SqliteQueryTranslator and PostgreSQLQueryTranslator. Kept as a trait (vs
 * an abstract base) so each translator can keep `final` on the class and
 * retain its own dispatch structure — only the pure utility functions are
 * de-duplicated here.
 */
trait QueryTranslatorHelpersTrait
{
    /**
     * Wrap a fresh QueryRewriter around the parser's underlying token list.
     */
    private function createRewriter(Parser $parser): QueryRewriter
    {
        return new QueryRewriter($parser->list->tokens, $parser->list->count);
    }

    /**
     * Whitespace / comment / delimiter tokens carry no meaning for our
     * expression dispatcher, so they get skipped over during token walks.
     */
    private function isSemanticVoid(Token $token): bool
    {
        return $token->type === TokenType::Whitespace
            || $token->type === TokenType::Comment
            || $token->type === TokenType::Delimiter;
    }

    /**
     * Advance the rewriter through a balanced parenthesised group,
     * consuming tokens until the matching ')'.
     */
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

    /**
     * Join a list of semantic tokens into a single fragment separated by
     * single spaces. extractFunctionArgs() discards whitespace tokens so
     * callers that need to preserve keyword boundaries (DISTINCT, ORDER
     * BY, DESC) reach for this helper to avoid glued identifiers.
     *
     * @param list<Token> $tokens
     */
    private function joinTokensWithSpaces(array $tokens): string
    {
        $parts = [];
        foreach ($tokens as $token) {
            $text = (string) $token->token;
            if ($text === '') {
                continue;
            }
            $parts[] = $text;
        }

        return implode(' ', $parts);
    }

    /**
     * Return the first TokenType::String in a token list, or null if none.
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
}
